"""Aturan kelompok field: mana yang pilih-satu, mana yang boleh banyak.

KENAPA FILE INI ADA
Instrumen memakai tanda √ untuk dua hal berbeda:

  (a) pilih satu dari beberapa kolom
      Tabel 22 kol 6-8: penelitian terjadi pada TS-2 ATAU TS-1 ATAU TS.
      Mencentang dua tahun berarti satu penelitian berjalan dua tahun.

  (b) boleh lebih dari satu
      Tabel 16 catatan 5): "Beri tanda √ pada kolom akademik atau profesi,
      dan DAPAT DIBERI TANDA √ PADA KEDUA-DUANYA." Dosen bisa mengajar di
      dua tahap. Memaksa pilih-satu di sini salah.

  (c) benar-benar multi
      Tabel 01: satu mata kuliah boleh memuat sikap DAN pengetahuan DAN
      keterampilan. Tabel 38: satu kerja sama bisa mencakup pendidikan
      dan penelitian sekaligus.

Karena itu tipenya tidak bisa ditebak dari label. Tiap kelompok diputuskan
manual di sini, dengan alasan tertulis. Kalau ragu, JANGAN masukkan ke daftar
INI_SATU — biarkan `banyak`, karena memaksa pilih-satu menghilangkan data yang
sah, sedangkan membiarkan banyak paling buruk menghasilkan isian berlebih yang
kelihatan saat asesmen.

Dipakai oleh: gen_skema_tabel.py (menulis kolom `kelompok` dan `pilih`).
"""

# KENAPA ADA PEMISAHAN: satu label instrumen kadang menaungi BEBERAPA kolom.
# Tabel 22 menulis satu baris "Tahun Penelitian (3)" di atas kolom TS-2 | TS-1
# | TS. Kalau field disimpan dengan label yang sama, tiga kolom itu tidak bisa
# dibedakan satu sama lain — dan aturan "pilih satu" jadi tidak bisa diterapkan.
# Karena itu field-nya dipecah di sini, bukan dibiarkan menumpuk.
#
# Struktur: (kode, awalan_label) -> daftar [(akhiran_label, nama_kelompok, pilih)]
# `akhiran` = '' berarti label dipakai apa adanya (tidak dipecah).
PECAH = {
    ("22", "Tahun penelitian TS-2 / TS-1 / TS"): [
        ("TS-2", "tahun_penelitian", "satu"),
        ("TS-1", "tahun_penelitian", "satu"),
        ("TS", "tahun_penelitian", "satu"),
    ],
    ("22", "Tingkat: internasional / nasional / wilayah"): [
        ("Internasional", "tingkat_penelitian", "satu"),
        ("Nasional", "tingkat_penelitian", "satu"),
        ("Wilayah", "tingkat_penelitian", "satu"),
    ],
    ("22", "Kesesuaian roadmap: sesuai / kurang / tidak"): [
        ("Sesuai", "kesesuaian_roadmap", "satu"),
        ("Kurang sesuai", "kesesuaian_roadmap", "satu"),
        ("Tidak sesuai", "kesesuaian_roadmap", "satu"),
    ],
    ("23", "Tahun TS-2 / TS-1 / TS"): [
        ("TS-2", "tahun_pkm", "satu"),
        ("TS-1", "tahun_pkm", "satu"),
        ("TS", "tahun_pkm", "satu"),
    ],
    ("23", "Kesesuaian roadmap: sesuai / kurang / tidak"): [
        ("Sesuai", "kesesuaian_pkm", "satu"),
        ("Kurang sesuai", "kesesuaian_pkm", "satu"),
        ("Tidak sesuai", "kesesuaian_pkm", "satu"),
    ],
    ("24", "Tingkat: lokal / nasional / internasional"): [
        ("Lokal", "tingkat_karya", "satu"),
        ("Nasional", "tingkat_karya", "satu"),
        ("Internasional", "tingkat_karya", "satu"),
    ],
    ("28", "Tingkat: provinsi / nasional / internasional"): [
        ("Provinsi", "tingkat_karya_mhs", "satu"),
        ("Nasional", "tingkat_karya_mhs", "satu"),
        ("Internasional", "tingkat_karya_mhs", "satu"),
    ],
    ("37", "Hasil nasional kuantitatif: minimum / A-Unggul / B-Baik Sekali / C-Baik"): [
        ("Minimum", "hasil_kuantitatif", "satu"),
        ("A-Unggul", "hasil_kuantitatif", "satu"),
        ("B-Baik Sekali", "hasil_kuantitatif", "satu"),
        ("C-Baik", "hasil_kuantitatif", "satu"),
    ],
    ("37", "Hasil nasional kualitatif: sementara / terakreditasi / unggul / kadaluwarsa"): [
        ("Sementara", "hasil_kualitatif", "satu"),
        ("Terakreditasi", "hasil_kualitatif", "satu"),
        ("Unggul", "hasil_kualitatif", "satu"),
        ("Kadaluwarsa", "hasil_kualitatif", "satu"),
    ],
    # Tabel 38 — satu instansi berada di satu negara. Instrumen menulis
    # "kolom yang relevan (kolom 3-7)", tapi lokasi geografis tidak bisa dua.
    # Tabel 38 jenis kegiatan: satu label menaungi 3 kolom (pendidikan,
    # penelitian, PkM). Dipecah supaya tiap kolom punya baris sendiri; satu
    # kerja sama BOLEH mencakup ketiganya sekaligus.
    ("38", "Jenis kegiatan: pendidikan / penelitian / PkM"): [
        ("Pendidikan", "jenis_kegiatan", "banyak"),
        ("Penelitian", "jenis_kegiatan", "banyak"),
        ("PkM", "jenis_kegiatan", "banyak"),
    ],
    ("38", "Dalam negeri"): [("", "lokasi_mitra", "satu")],
    ("38", "Luar negeri"): [("", "lokasi_mitra", "satu")],
    # Tabel 18 — pada satu kegiatan, orang berperan sebagai penyaji ATAU
    # peserta, bukan keduanya sekaligus.
    ("18", "Sebagai penyaji"): [("", "peran_kegiatan", "satu")],
    ("18", "Sebagai peserta"): [("", "peran_kegiatan", "satu")],
}

# Kelompok yang SENGAJA boleh banyak. Dicatat supaya keputusannya kelihatan
# dan tidak "diperbaiki" orang lain di kemudian hari.
BOLEH_BANYAK = {
    ("16", "Tahap:"): "Dosen boleh mengajar di tahap akademik DAN profesi "
                      "(instrumen Tabel 16 catatan 5).",
    ("01", "Aspek penilaian"): "Satu mata kuliah memuat lebih dari satu aspek.",
}


def pecah_label(kode: str, label: str) -> list[tuple[str, str, str]]:
    """Pecah satu label instrumen jadi beberapa field.

    Kembalikan [(label_final, kelompok, pilih), ...]. Label yang tidak dipecah
    dikembalikan apa adanya dengan kelompok/pilih dari BOLEH_BANYAK atau kosong.
    """
    for (k, awalan), bagian in PECAH.items():
        if k == kode and label.strip() == awalan:
            # Nama kelompok jadi awalan label, BUKAN salinan label panjang.
            # Tanpa ini hasilnya "Tahun penelitian TS-2 — TS-2" (kolomnya
            # disebut dua kali) dan labelnya jadi tidak terbaca.
            JUDUL = {
                "tahun_penelitian": "Tahun penelitian",
                "tahun_pkm": "Tahun PkM",
                "tingkat_penelitian": "Tingkat penelitian",
                "kesesuaian_roadmap": "Kesesuaian roadmap",
                "kesesuaian_pkm": "Kesesuaian PkM",
                "tingkat_karya": "Tingkat karya",
                "tingkat_karya_mhs": "Tingkat karya mahasiswa",
                "hasil_kuantitatif": "Hasil akreditasi kuantitatif",
                "hasil_kualitatif": "Hasil akreditasi kualitatif",
            }
            hasil = []
            for akhiran, kel, pilih in bagian:
                nama = JUDUL.get(kel, kel)
                hasil.append((f"{nama}: {akhiran}" if akhiran else label, kel, pilih))
            return hasil
    for (k, awalan) in BOLEH_BANYAK:
        if k == kode and label.startswith(awalan):
            return [(label, awalan.rstrip(":").lower().replace(" ", "_"), "banyak")]
    return [(label, "", "")]
