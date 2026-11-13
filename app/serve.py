#!/usr/bin/env python3
"""Server app LAM-PTKes: file statis + API JSON kecil di atas SQLite.

Kenapa tidak `python3 -m http.server` saja: WebKit (Safari) menyimpan ES module
di cache memori yang TIDAK ikut terhapus oleh hard-reload, dan SimpleHTTPRequest-
Handler tidak mengirim Cache-Control. Server ini mengirim `no-store`, jadi setiap
reload selalu mengambil file terbaru.

SESI: identitas diambil dari cookie `sesi`, bukan dari body. Dulu `oleh` dikirim
klien, artinya siapa pun bisa mengaku Admin. Sekarang peran & prodi hanya datang
dari token; prodi tabel di luar sesi ditolak 403.

API (semua JSON):
  POST /api/masuk               {"nama","sandi","prodi"} → sesi + Set-Cookie
  POST /api/keluar
  GET  /api/saya                sesi aktif + matriks akses
  GET  /api/ringkasan            angka turunan untuk dashboard
  GET  /api/tabel                semua 38 tabel + status
  POST /api/tabel/<kode>/isi     {"terisi": n}           butuh akses "Isi"
  POST /api/tabel/<kode>/kunci                          butuh "Verifikasi"
  POST /api/tabel/<kode>/buka                           butuh "Buka kunci"
  GET  /api/riwayat?batas=50    log perubahan
  POST /api/reset               kembalikan ke kondisi awal (demo ulang)

Pakai:  python3 serve.py [port]

AKUN DEMO (semua sandi `demo1234`, dibuat di db.init):
  Siti Rahmawati, S.ST., M.Keb        Operator Prodi  · Kebidanan
  Nurhayati, S.Kep., M.Kep            Operator Prodi  · Keperawatan
  Dr. Maimunah, M.Keb                 Verifikator     · Kebidanan
  Yusrizal, S.Kep., Ns., M.Kep        Verifikator     · Keperawatan
  Dr. Ir. Zulfikar, M.Pd              Admin UPPS
  Prof. Dr. Cut Zahriah, M.Pd         Pimpinan (read-only)
  Marlina, S.ST., M.Keb               Operator + Verifikator → status "konflik"
  Hasnawati, A.Md.Keb                 Operator · nonaktif (tidak bisa masuk)
"""
import http.server
import json
import os
import socketserver
import sys
from http.cookies import SimpleCookie
from urllib.parse import urlparse, parse_qs

import db

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8123
ROOT = os.path.dirname(os.path.abspath(__file__))
SEED = os.path.join(ROOT, "seed.json")
COOKIE = "sesi"


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ROOT, **kwargs)

    # ── util ────────────────────────────────────────────────────────
    def _json(self, obj, kode=200, cookies=None):
        body = json.dumps(obj, ensure_ascii=False).encode()
        self.send_response(kode)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        for c in cookies or []:
            self.send_header("Set-Cookie", c)
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

    def _token(self):
        raw = self.headers.get("Cookie") or ""
        c = SimpleCookie()
        try:
            c.load(raw)
        except Exception:
            return None
        m = c.get(COOKIE)
        return m.value if m else None

    def _sesi(self):
        return db.sesi_dari(self._token())

    def _tolak(self, gerbang):
        if not gerbang:
            return False
        kode, pesan = gerbang
        self._json({"error": pesan}, kode)
        return True

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
        sesi = self._sesi()
        if u.path == "/api/saya":
            return self._json({"sesi": sesi, "aksi": list(db.AKSI)})
        if u.path == "/api/ringkasan":
            return self._json(db.ringkasan())
        if u.path == "/api/tabel":
            return self._json(db.daftar_tabel())
        if u.path == "/api/pengguna":
            if self._tolak(db.wajib(sesi, "Kelola pengguna")):
                return
            return self._json({
                "daftar": db.daftar_pengguna(),
                "ringkasan": db.ringkasan_akun(),
                "peran": list(db.PERAN),
            })
        if u.path == "/api/riwayat":
            if not sesi:
                return self._json({"error": "belum masuk"}, 401)
            batas = int((parse_qs(u.query).get("batas") or ["50"])[0])
            return self._json(db.riwayat(batas))
        return self._json({"error": "endpoint tidak ada"}, 404)

    def do_POST(self):
        u = urlparse(self.path)
        if not u.path.startswith("/api/"):
            return self._json({"error": "hanya API yang menerima POST"}, 405)
        b = self._body()

        if u.path == "/api/masuk":
            nama = (b.get("nama") or "").strip()
            sesi, kode, err = db.masuk(nama, b.get("sandi") or "", b.get("prodi"))
            if err:
                return self._json({"error": err}, kode)
            cookie = f"{COOKIE}={sesi['token']}; Path=/; HttpOnly; SameSite=Lax; Max-Age=28800"
            return self._json({"sesi": sesi}, 200, [cookie])

        if u.path == "/api/keluar":
            db.keluar(self._token())
            return self._json({"keluar": True}, 200,
                              [f"{COOKIE}=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0"])

        sesi = self._sesi()
        if u.path == "/api/reset":
            if self._tolak(db.wajib(sesi, "Kelola pengguna")):
                return
            return self._json(db.reset(SEED))

        bagian = [p for p in u.path.strip("/").split("/") if p]  # api, tabel, kode, aksi

        # /api/pengguna/<id>/peran   {"peran": "...", "status": "..."}
        # /api/pengguna/<id>/pisah   {}
        if len(bagian) == 4 and bagian[1] == "pengguna":
            if self._tolak(db.wajib(sesi, "Kelola pengguna")):
                return
            try:
                pid = int(bagian[2])
            except ValueError:
                return self._json({"error": "id harus angka"}, 400)
            aksi = bagian[3]
            if aksi == "peran":
                data, kode, err = db.ubah_pengguna(
                    pid, peran=b.get("peran"), status=b.get("status"), oleh=sesi["nama"])
                return self._json({"error": err} if err else data, kode)
            if aksi == "pisah":
                data, kode, err = db.pisahkan_peran(pid, sesi["nama"])
                return self._json({"error": err} if err else data, kode)

        if len(bagian) == 4 and bagian[1] == "tabel":
            kode_tabel, aksi = bagian[2], bagian[3]
            izin = {"isi": "Isi", "kunci": "Verifikasi", "buka": "Buka kunci"}.get(aksi)
            if not izin:
                return self._json({"error": "aksi tidak dikenal"}, 404)
            if self._tolak(db.wajib(sesi, izin)):
                return
            # ponytail: belum ada cakupan per-prodi (tabel belum punya kolom prodi).
            # Tambah begitu seed menyimpan kode_tabel -> prodi.
            if aksi == "isi":
                if "terisi" not in b:
                    return self._json({"error": "butuh field 'terisi'"}, 400)
                data, kode_status, err = db.isi_tabel(kode_tabel, b["terisi"], sesi["nama"])
                return self._json({"error": err} if err else data, kode_status)
            if aksi == "kunci":
                data, kode_status, err = db.kunci_tabel(kode_tabel, sesi["nama"])
                return self._json({"error": err} if err else data, kode_status)
            if aksi == "buka":
                data, kode_status, err = db.buka_kunci(kode_tabel, sesi["nama"])
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
