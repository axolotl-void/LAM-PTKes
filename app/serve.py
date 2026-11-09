#!/usr/bin/env python3
"""Server app LAM-PTKes: file statis + API JSON kecil di atas SQLite.

Kenapa tidak `python3 -m http.server` saja: WebKit (Safari) menyimpan ES module
di cache memori yang TIDAK ikut terhapus oleh hard-reload, dan SimpleHTTPRequest-
Handler tidak mengirim Cache-Control. Server ini mengirim `no-store`, jadi setiap
reload selalu mengambil file terbaru.

API (semua JSON):
  GET  /api/ringkasan            angka turunan untuk dashboard
  GET  /api/tabel                semua 38 tabel + status
  POST /api/tabel/<kode>/isi     {"terisi": n, "oleh": "nama"}
  POST /api/tabel/<kode>/kunci   {"oleh": "nama"}
  POST /api/tabel/<kode>/buka    {}
  GET  /api/riwayat?batas=50     log perubahan
  POST /api/reset                kembalikan ke kondisi awal (demo ulang)

Pakai:  python3 serve.py [port]
"""
import http.server
import json
import os
import socketserver
import sys
from urllib.parse import urlparse, parse_qs

import db

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8123
ROOT = os.path.dirname(os.path.abspath(__file__))
SEED = os.path.join(ROOT, "seed.json")


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ROOT, **kwargs)

    # ── util ────────────────────────────────────────────────────────
    def _json(self, obj, kode=200):
        body = json.dumps(obj, ensure_ascii=False).encode()
        self.send_response(kode)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _body(self):
        n = int(self.headers.get("Content-Length") or 0)
        if not n:
            return {}
        try:
            return json.loads(self.rfile.read(n) or b"{}")
        except json.JSONDecodeError:
            return {}

    def end_headers(self):
        if not self.path.startswith("/api/"):
            self.send_header("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
            self.send_header("Pragma", "no-cache")
            self.send_header("Expires", "0")
        super().end_headers()

    # ── api ─────────────────────────────────────────────────────────
    def do_GET(self):
        u = urlparse(self.path)
        if not u.path.startswith("/api/"):
            return super().do_GET()
        if u.path == "/api/ringkasan":
            return self._json(db.ringkasan())
        if u.path == "/api/tabel":
            return self._json(db.daftar_tabel())
        if u.path == "/api/pengguna":
            return self._json({
                "daftar": db.daftar_pengguna(),
                "ringkasan": db.ringkasan_akun(),
                "peran": list(db.PERAN),
            })
        if u.path == "/api/riwayat":
            batas = int((parse_qs(u.query).get("batas") or ["50"])[0])
            return self._json(db.riwayat(batas))
        return self._json({"error": "endpoint tidak ada"}, 404)

    def do_POST(self):
        u = urlparse(self.path)
        if not u.path.startswith("/api/"):
            return self._json({"error": "hanya API yang menerima POST"}, 405)
        b = self._body()
        oleh = (b.get("oleh") or "operator").strip() or "operator"
        bagian = [p for p in u.path.strip("/").split("/") if p]  # api, tabel, kode, aksi

        if u.path == "/api/reset":
            return self._json(db.reset(SEED))

        # /api/pengguna/<id>/peran   {"peran": "...", "status": "..."}
        # /api/pengguna/<id>/pisah   {}
        if len(bagian) == 4 and bagian[1] == "pengguna":
            try:
                pid = int(bagian[2])
            except ValueError:
                return self._json({"error": "id harus angka"}, 400)
            aksi = bagian[3]
            if aksi == "peran":
                data, kode, err = db.ubah_pengguna(
                    pid, peran=b.get("peran"), status=b.get("status"), oleh=oleh)
                return self._json({"error": err} if err else data, kode)
            if aksi == "pisah":
                data, kode, err = db.pisahkan_peran(pid, oleh)
                return self._json({"error": err} if err else data, kode)

        if len(bagian) == 4 and bagian[1] == "tabel":
            kode, aksi = bagian[2], bagian[3]
            if aksi == "isi":
                if "terisi" not in b:
                    return self._json({"error": "butuh field 'terisi'"}, 400)
                data, kode_status, err = db.isi_tabel(kode, b["terisi"], oleh)
                return self._json({"error": err} if err else data, kode_status)
            if aksi == "kunci":
                data, kode_status, err = db.kunci_tabel(kode, oleh)
                return self._json({"error": err} if err else data, kode_status)
            if aksi == "buka":
                data, kode_status, err = db.buka_kunci(kode, oleh)
                return self._json({"error": err} if err else data, kode_status)
        return self._json({"error": "endpoint tidak ada"}, 404)

    def log_message(self, fmt, *args):
        sys.stderr.write("%s %s\n" % (self.command, self.path))


class ReuseServer(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == "__main__":
    os.chdir(ROOT)
    hasil = db.init(SEED)
    print("db:", hasil)
    with ReuseServer(("127.0.0.1", PORT), Handler) as httpd:
        print(f"LAM-PTKes app → http://127.0.0.1:{PORT}  (SQLite + no-store)")
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print("\nberhenti")
