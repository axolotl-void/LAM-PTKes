#!/usr/bin/env python3
"""SQLite untuk simulasi pengisian LAM-PTKes.

Kenapa SQLite: satu file, nol server, nol dependency (sqlite3 di stdlib).
Tujuannya bukan produksi — tujuannya supaya tombol "Isi" benar-benar
mengubah angka, dan status tabel diturunkan dari data, bukan dari konstanta
yang diketik di JS.

ATURAN ANGKA: status tabel TIDAK disimpan sebagai teks bebas. Kolom
`field_terisi` / `field_total` adalah sumber kebenaran; status dihitung dari
perbandingan keduanya. Jadi mustahil ada baris "lengkap" dengan 3 dari 6 field
terisi — persis kelas bug yang sudah kejadian di halaman Data Dukung.
"""
import json
import os
import sqlite3

DB = os.path.join(os.path.dirname(os.path.abspath(__file__)), "lamptkes.db")

SKEMA = """
PRAGMA journal_mode=WAL;

CREATE TABLE IF NOT EXISTS meta (
  kunci TEXT PRIMARY KEY,
  nilai TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS kriteria (
  kode TEXT PRIMARY KEY,
  nama TEXT NOT NULL,
  singkat TEXT,
  butir TEXT,
  temuan TEXT
);

CREATE TABLE IF NOT EXISTS tabel (
  kode          TEXT PRIMARY KEY,
  judul         TEXT NOT NULL,
  kode_kriteria TEXT NOT NULL REFERENCES kriteria(kode),
  field_terisi  INTEGER NOT NULL DEFAULT 0,
  field_total   INTEGER NOT NULL DEFAULT 0,
  berlaku       INTEGER NOT NULL DEFAULT 1,   -- 0 = khusus tahap profesi
  dikunci_oleh  TEXT,                          -- nama verifikator, NULL = terbuka
  dikunci_pada  TEXT,
  diubah_pada   TEXT,
  CHECK (field_terisi >= 0 AND field_terisi <= field_total)
);

CREATE TABLE IF NOT EXISTS dokumen (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  nama           TEXT NOT NULL,
  kode_kriteria  TEXT NOT NULL REFERENCES kriteria(kode),
  kode_tabel     TEXT,
  berlaku_sampai TEXT,
  diunggah       INTEGER NOT NULL DEFAULT 0,
  diunggah_pada  TEXT,
  catatan        TEXT
);

CREATE TABLE IF NOT EXISTS pengguna (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  nama           TEXT NOT NULL UNIQUE,
  peran          TEXT NOT NULL,
  prodi          TEXT,
  status         TEXT NOT NULL DEFAULT 'aktif',
  aktif_terakhir TEXT
);

CREATE TABLE IF NOT EXISTS catatan (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  waktu     TEXT NOT NULL,
  oleh      TEXT NOT NULL,
  aksi      TEXT NOT NULL,
  entitas   TEXT NOT NULL,
  kode      TEXT,
  sebelum   TEXT,
  sesudah   TEXT
);
"""

# status diturunkan, tidak pernah disimpan. Ini yang mencegah angka bohong.
STATUS_SQL = """
CASE
  WHEN tabel.berlaku = 0                                    THEN 'na'
  WHEN tabel.dikunci_oleh IS NOT NULL                       THEN 'terkunci'
  WHEN tabel.field_total = 0                                THEN 'kosong'
  WHEN tabel.field_terisi >= tabel.field_total              THEN 'lengkap'
  WHEN tabel.field_terisi = 0                               THEN 'kosong'
  ELSE 'sebagian'
END
"""


def conn():
    c = sqlite3.connect(DB)
    c.row_factory = sqlite3.Row
    c.execute("PRAGMA foreign_keys=ON")
    return c


def init(seed_path=None):
    """Buat skema + isi seed. Idempoten: tidak menimpa data yang sudah ada."""
    c = conn()
    c.executescript(SKEMA)
    ada = c.execute("SELECT COUNT(*) FROM tabel").fetchone()[0]
    if ada:
        c.close()
        return {"seeded": False, "tabel": ada}
    if not seed_path or not os.path.exists(seed_path):
        c.close()
        return {"seeded": False, "tabel": 0, "error": "seed tidak ditemukan"}

    d = json.load(open(seed_path))
    inst = d["instrumen"]
    for k, v in {
        "total_tabel": inst["total_tabel"],
        "total_dokumen": inst["total_dokumen"],
        "kriteria": inst["kriteria"],
        "tabel_na": ",".join(inst["tabel_na"]),
        "snapshot": "12 Agu 2026",
    }.items():
        c.execute("INSERT OR REPLACE INTO meta VALUES (?,?)", (k, str(v)))

    c.executemany(
        "INSERT INTO kriteria VALUES (?,?,?,?,?)",
        [(k["kode"], k["nama"], k["singkat"], k["butir"], k["temuan"]) for k in d["kriteria"]],
    )
    c.executemany(
        """INSERT INTO tabel
           (kode, judul, kode_kriteria, field_terisi, field_total, berlaku)
           VALUES (?,?,?,?,?,?)""",
        [(t["kode"], t["judul"], t["kode_kriteria"], t["field_terisi"],
          t["field_total"], t["berlaku"]) for t in d["tabel"]],
    )
    c.executemany(
        """INSERT INTO dokumen (nama, kode_kriteria, berlaku_sampai, diunggah)
           VALUES (?,?,?,1)""",
        [(x["nama"], x["kode_kriteria"], x["berlaku_sampai"]) for x in d["kedaluwarsa"]],
    )
    c.executemany(
        "INSERT INTO pengguna (nama, peran, prodi, status, aktif_terakhir) VALUES (?,?,?,?,?)",
        [(p["nama"], p["peran"], p["prodi"], p["status"], p["aktif_terakhir"])
         for p in d["pengguna"]],
    )
    c.execute("INSERT INTO catatan (waktu, oleh, aksi, entitas, kode, sesudah) "
              "VALUES (?,?,?,?,?,?)",
              ("2026-11-09T09:00", "sistem", "seed", "database", None, "seed awal"))
    c.commit()
    c.close()
    return {"seeded": True, "tabel": len(d["tabel"])}


def ringkasan():
    """Angka turunan yang dipakai dashboard. Dihitung dari tabel, bukan konstanta."""
    c = conn()
    rows = c.execute(f"""
        SELECT tabel.kode, tabel.kode_kriteria, tabel.field_terisi, tabel.field_total,
               tabel.berlaku, {STATUS_SQL} AS status
        FROM tabel ORDER BY CAST(tabel.kode AS INTEGER), tabel.kode
    """).fetchall()
    wajib = [r for r in rows if r["berlaku"]]
    per_status = {}
    for r in wajib:
        per_status[r["status"]] = per_status.get(r["status"], 0) + 1

    per_krit = {}
    for r in wajib:
        e = per_krit.setdefault(r["kode_kriteria"], {"total": 0, "lengkap": 0})
        e["total"] += 1
        if r["status"] in ("lengkap", "terkunci"):
            e["lengkap"] += 1

    dok = c.execute("SELECT COUNT(*) AS n, SUM(diunggah) AS up FROM dokumen").fetchone()
    c.close()

    total_wajib = len(wajib)
    lengkap = per_status.get("lengkap", 0) + per_status.get("terkunci", 0)
    return {
        "tabel_total": len(rows),
        "tabel_wajib": total_wajib,
        "tabel_na": len(rows) - total_wajib,
        "lengkap": lengkap,
        "sebagian": per_status.get("sebagian", 0),
        "kosong": per_status.get("kosong", 0),
        "terkunci": per_status.get("terkunci", 0),
        "belum": total_wajib - lengkap,
        "dokumen_total": dok["n"],
        "dokumen_terunggah": dok["up"] or 0,
        "per_kriteria": per_krit,
    }


def daftar_tabel():
    c = conn()
    rows = c.execute(f"""
        SELECT tabel.*, {STATUS_SQL} AS status
        FROM tabel ORDER BY CAST(tabel.kode AS INTEGER), tabel.kode
    """).fetchall()
    c.close()
    return [dict(r) for r in rows]


def isi_tabel(kode, terisi, oleh="operator"):
    """Isi field tabel. Status dihitung ulang otomatis oleh ringkasan().

    Ditolak kalau tabel sedang dikunci — verifikasi yang mengunci, bukan isian.
    """
    c = conn()
    r = c.execute("SELECT * FROM tabel WHERE kode=?", (kode,)).fetchone()
    if not r:
        c.close()
        return None, 404, "tabel tidak ada"
    if r["dikunci_oleh"]:
        c.close()
        return None, 409, f"dikunci oleh {r['dikunci_oleh']}"
    if r["berlaku"] == 0:
        c.close()
        return None, 409, "tabel khusus tahap profesi, tidak berlaku untuk D-III"
    total = r["field_total"]
    n = max(0, min(int(terisi), total))
    c.execute("UPDATE tabel SET field_terisi=?, diubah_pada=datetime('now','localtime') "
              "WHERE kode=?", (n, kode))
    c.execute("INSERT INTO catatan (waktu, oleh, aksi, entitas, kode, sebelum, sesudah) "
              "VALUES (datetime('now','localtime'),?,?,?,?,?,?)",
              (oleh, "isi", "tabel", kode, str(r["field_terisi"]), str(n)))
    c.commit()
    baru = c.execute(f"SELECT tabel.*, {STATUS_SQL} AS status FROM tabel WHERE kode=?",
                     (kode,)).fetchone()
    c.close()
    return dict(baru), 200, None


def kunci_tabel(kode, oleh="verifikator"):
    c = conn()
    r = c.execute("SELECT field_terisi, field_total, berlaku FROM tabel WHERE kode=?",
                  (kode,)).fetchone()
    if not r:
        c.close()
        return None, 404, "tabel tidak ada"
    if r["field_terisi"] < r["field_total"]:
        c.close()
        return None, 409, f"belum lengkap: {r['field_terisi']}/{r['field_total']}"
    c.execute("UPDATE tabel SET dikunci_oleh=?, dikunci_pada=datetime('now','localtime') "
              "WHERE kode=?", (oleh, kode))
    c.execute("INSERT INTO catatan (waktu, oleh, aksi, entitas, kode) "
              "VALUES (datetime('now','localtime'),?,?,?,?)",
              (oleh, "kunci", "tabel", kode))
    c.commit()
    c.close()
    return {"kode": kode, "dikunci_oleh": oleh}, 200, None


def buka_kunci(kode, oleh="admin"):
    c = conn()
    c.execute("UPDATE tabel SET dikunci_oleh=NULL, dikunci_pada=NULL WHERE kode=?", (kode,))
    c.execute("INSERT INTO catatan (waktu, oleh, aksi, entitas, kode) "
              "VALUES (datetime('now','localtime'),?,?,?,?)",
              (oleh, "buka kunci", "tabel", kode))
    c.commit()
    c.close()
    return {"kode": kode, "terbuka": True}, 200, None


# ── akun & hak akses ─────────────────────────────────────────────────
# Aturan pemisahan tugas. Ini yang diperiksa asesor: orang yang mengisi tidak
# boleh menyetujui pekerjaannya sendiri. Ditegakkan di sini supaya UI tidak bisa
# "menyembunyikan" tombol lalu mengirim POST yang sama.
PERAN = ("Operator Prodi", "Verifikator", "Admin UPPS", "Pimpinan")
PERAN_BOLEH_GABUNG = {("Admin UPPS",)}   # admin memang memegang semua


def daftar_pengguna():
    c = conn()
    rows = c.execute("SELECT * FROM pengguna ORDER BY id").fetchall()
    c.close()
    return [dict(r) for r in rows]


def _konflik(peran):
    """True kalau kombinasi peran melanggar pemisahan tugas.

    Peran bisa ditulis panjang ("Operator Prodi") atau pendek ("Operator") —
    data lama memakai bentuk pendek, jadi cocokkan kata kuncinya, jangan
    string persisnya.
    """
    bagian = [p.strip().lower() for p in peran.split("+")]
    if any("admin" in b for b in bagian):
        return False
    punya_isi = any("operator" in b for b in bagian)
    punya_verif = any("verifikator" in b for b in bagian)
    return punya_isi and punya_verif


def ubah_pengguna(pid, peran=None, status=None, prodi=None, oleh="admin"):
    c = conn()
    r = c.execute("SELECT * FROM pengguna WHERE id=?", (pid,)).fetchone()
    if not r:
        c.close()
        return None, 404, "akun tidak ada"

    peran_baru = peran if peran is not None else r["peran"]
    if peran is not None and not all(
        p.strip() in PERAN for p in peran.split("+")
    ):
        c.close()
        return None, 400, f"peran tidak dikenal. Pilihan: {', '.join(PERAN)}"

    # Peran ganda Operator+Verifikator tidak ditolak (data lama bisa begitu),
    # tapi statusnya dipaksa "konflik" supaya ekspor tetap terkunci dan
    # halaman Pengguna menyorotnya. Yang menolak keras: peran tak dikenal.
    status_baru = status if status is not None else r["status"]
    if _konflik(peran_baru):
        status_baru = "konflik"
    elif status_baru == "konflik":
        status_baru = "aktif"

    c.execute(
        "UPDATE pengguna SET peran=?, status=?, prodi=? WHERE id=?",
        (peran_baru, status_baru, prodi if prodi is not None else r["prodi"], pid),
    )
    c.execute(
        "INSERT INTO catatan (waktu, oleh, aksi, entitas, kode, sebelum, sesudah) "
        "VALUES (datetime('now','localtime'),?,?,?,?,?,?)",
        (oleh, "ubah akun", "pengguna", str(pid),
         f"{r['peran']}/{r['status']}", f"{peran_baru}/{status_baru}"),
    )
    c.commit()
    baru = c.execute("SELECT * FROM pengguna WHERE id=?", (pid,)).fetchone()
    c.close()
    return dict(baru), 200, None


def pisahkan_peran(pid, oleh="admin"):
    """Pecah akun konflik: Operator+Verifikator -> Verifikator saja.

    Yang dipertahankan adalah Verifikator, karena peran itulah yang menyetujui —
    mempertahankan Operator akan menghapus jejak siapa yang menyetujui data lama.
    Operator tetap ada di akun lain, jadi tidak ada pekerjaan yang hilang.
    """
    c = conn()
    r = c.execute("SELECT * FROM pengguna WHERE id=?", (pid,)).fetchone()
    if not r:
        c.close()
        return None, 404, "akun tidak ada"
    if not _konflik(r["peran"]):
        c.close()
        return None, 409, "akun ini tidak punya konflik peran"
    c.execute("UPDATE pengguna SET peran='Verifikator', status='aktif' WHERE id=?", (pid,))
    c.execute(
        "INSERT INTO catatan (waktu, oleh, aksi, entitas, kode, sebelum, sesudah) "
        "VALUES (datetime('now','localtime'),?,?,?,?,?,?)",
        (oleh, "pisahkan peran", "pengguna", str(pid),
         r["peran"], "Verifikator"),
    )
    c.commit()
    baru = c.execute("SELECT * FROM pengguna WHERE id=?", (pid,)).fetchone()
    c.close()
    return dict(baru), 200, None


def ringkasan_akun():
    c = conn()
    rows = c.execute("SELECT peran, status FROM pengguna").fetchall()
    c.close()
    aktif = [r for r in rows if r["status"] == "aktif"]
    konflik = [r for r in rows if r["status"] == "konflik"]
    return {
        "total": len(rows),
        "aktif_tanpa_konflik": len(aktif),
        "konflik": len(konflik),
        "nonaktif": len([r for r in rows if r["status"] == "nonaktif"]),
        "peran": len({p.strip() for r in rows for p in r["peran"].split("+")}),
    }


def riwayat(batas=50):
    c = conn()
    rows = c.execute("SELECT * FROM catatan ORDER BY id DESC LIMIT ?", (batas,)).fetchall()
    c.close()
    return [dict(r) for r in rows]


def reset(seed_path):
    """Kembalikan ke kondisi awal — untuk demo ulang dari nol."""
    if os.path.exists(DB):
        os.remove(DB)
    for suf in ("-wal", "-shm"):
        if os.path.exists(DB + suf):
            os.remove(DB + suf)
    return init(seed_path)


if __name__ == "__main__":
    import sys
    seed = os.path.join(os.path.dirname(os.path.abspath(__file__)), "seed.json")
    if len(sys.argv) > 1 and sys.argv[1] == "reset":
        print(reset(seed))
    else:
        print(init(seed))
    print(json.dumps(ringkasan(), indent=1))
