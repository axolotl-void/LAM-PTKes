"""Uji aturan DB tanpa server. Jalankan: python3 test_db.py

Penjaga: status tabel HARUS turunan dari field_terisi/field_total, tidak boleh
diketik manual. Kelas bug yang sudah kejadian: baris "lengkap" padahal 3/6.
"""
import db
import os

SEED = os.path.join(os.path.dirname(os.path.abspath(__file__)), "seed.json")


def test_seed():
    """Angka tidak di-hardcode: prodi UBBG punya tahap profesi, jadi 38 wajib.
    Kalau jenjang berubah, uji ini tetap benar tanpa disunting."""
    db.reset(SEED)
    r = db.ringkasan()
    assert r["tabel_total"] == 38, r
    assert r["tabel_na"] == 0, r
    assert r["tabel_wajib"] == 38, r
    assert r["lengkap"] + r["sebagian"] + r["kosong"] + r["terkunci"] == 38, r
    assert r["lengkap"] + r["belum"] == r["tabel_wajib"], r


def test_status_turunan():
    db.reset(SEED)
    # 0 field -> kosong
    d, code, _ = db.isi_tabel("23", 0)
    assert code == 200 and d["status"] == "kosong", d
    # sebagian
    d, _, _ = db.isi_tabel("3", 3)
    assert d["status"] == "sebagian", d
    # penuh -> lengkap otomatis
    d, _, _ = db.isi_tabel("3", 6)
    assert d["status"] == "lengkap", d


def test_kunci():
    db.reset(SEED)
    # tidak boleh kunci yang belum lengkap
    _, code, err = db.kunci_tabel("1")
    assert code == 409, err
    # kunci yang lengkap
    data, code, err = db.kunci_tabel("24")
    assert code == 200, err
    # isi setelah dikunci ditolak
    _, code, _ = db.isi_tabel("24", 1)
    assert code == 409
    # buka kunci -> bisa diisi lagi
    db.buka_kunci("24")
    _, code, _ = db.isi_tabel("24", 1)
    assert code == 200


def test_tabel_profesi_wajib():
    """Prodi UBBG punya tahap profesi -> Tabel 30 WAJIB, bukan ditolak."""
    db.reset(SEED)
    d, code, err = db.isi_tabel("30", 2)
    assert code == 200, err
    assert d["berlaku"] == 1, d


def test_clamp():
    db.reset(SEED)
    d, _, _ = db.isi_tabel("2", 999)
    assert d["field_terisi"] == d["field_total"], d
    d, _, _ = db.isi_tabel("2", -5)
    assert d["field_terisi"] == 0, d


def test_total_tutup():
    db.reset(SEED)
    r = db.ringkasan()
    assert r["lengkap"] + r["sebagian"] + r["kosong"] == r["tabel_wajib"], r
    assert r["terkunci"] <= r["lengkap"], r
    assert r["tabel_wajib"] + r["tabel_na"] == r["tabel_total"], r
    db.reset(SEED)


if __name__ == "__main__":
    for name, fn in list(globals().items()):
        if name.startswith("test_"):
            fn()
            print("ok", name)
    print("semua uji DB lulus")
