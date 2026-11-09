"""Screens 05-07: Ringkasan 8 Kriteria, Dokumen Pendukung, Login (redesign).
Runs against the LIVE FigCraft app over MCP (--control 7979)."""
import sys
from kit import Kit, IND, IND_D, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, ST, UI, MONO
from mcp_client import FigMCP

k = Kit()
r, t, f_ = k.rect, k.txt, k.frame

# =========================================================
# 05 · Ringkasan 8 Kriteria
# =========================================================
f = f_(6000, 0, 1440, 1080, "05 · Ringkasan 8 Kriteria", fill=BG)
k.topbar(f)
k.sidebar(f, aktif="Ringkasan")

t(f, 280, 84, "Ringkasan 8 Kriteria", 24, T1, "Bold")
t(f, 280, 114, "Penilaian instrumen LAM-PTKes · Kebidanan · D-III · TS 2025/2026", 11, T2)
r(f, 1180, 84, 108, 34, fill=SURF, radius=6, stroke=BD, name="btn-filter")
t(f, 1180, 92, "Semua kriteria", 10.5, "#344054", "Medium", width=108, align="center")
r(f, 1296, 84, 112, 34, fill=ORN, radius=6, name="btn-ekspor")
t(f, 1296, 92, "Ekspor", 11, "#FFFFFF", "Bold", width=112, align="center")

# summary strip
strip = [("38", "tabel", T1), ("32", "lengkap", ST["lengkap"][0]),
         ("4", "sebagian", ST["sebagian"][0]), ("2", "belum", ST["kosong"][0]),
         ("6", "tak berlaku", T2)]
sx = 280
for i, (num, lab, col) in enumerate(strip):
    t(f, sx, 148, num, 22, col, "Bold")
    t(f, sx, 176, lab, 10, T2)
    if i < len(strip) - 1:
        k.vline(f, sx + 92, 150, 34, BD)
    sx += 116

y = 208
krit = [
    ("1", "Visi, Misi, Tujuan & Strategi", 1, 1, 4, ["Tabel 1 bukan cakupan kriteria ini"],
     "Dokumen renstra & notulen rapat perumusan visi sudah lengkap."),
    ("2", "Kurikulum", 1, 1, 9, ["Tabel 1 · impor kurikulum 239 baris"],
     "RPS dan peta kompetensi tersedia; tinggal unggah ke repositori."),
    ("3", "Penilaian", 4, 7, 8, ["Tabel 5 · varian belum dipilih", "Tabel 7 & 8 · butuh hasil UKOM Bidan"],
     "4 tabel mendesak: dua di antaranya khusus tahap profesi sehingga ditandai tak berlaku untuk D-III."),
    ("4", "Mahasiswa", 5, 6, 11, ["Tabel 13 · 3 lulusan belum terlacak"],
     "Data tracer study baru mencakup 41 dari 44 lulusan TS-2."),
    ("5", "Dosen, Tendik, Penelitian & PkM", 11, 14, 15, ["Tabel 15 vs 16 selisih 1 nama", "Tabel 24 · 2 artikel di luar 3 tahun"],
     "Kriteria terberat: 14 tabel, 15 butir dokumen, dan 1 aturan lintas tabel gagal."),
    ("6", "Sarana, Prasarana & Keuangan", 5, 6, 14, ["Tabel 34 · persen TS-1 = 96,4%"],
     "Dua wahana praktik belum punya dokumen kerja sama yang berlaku."),
    ("7", "Penjaminan Mutu", 2, 3, 9, ["Tabel 35 · audit mutu 1 prodi belum diisi"],
     "Laporan audit 2025 baru tersedia untuk Keperawatan."),
    ("8", "Tata Kelola & Administrasi", 1, 1, 8, [], "Lengkap dan siap dikunci."),
]
for i, (no, nama, done, tot, docs, issues, note) in enumerate(krit):
    ch = 104
    r(f, 280, y, 1128, ch, fill=SURF, radius=10, stroke=BD, name=f"krit {no}")
    # nomor
    r(f, 300, y + 22, 34, 34, fill=IND_T, radius=8, name="krit-no")
    t(f, 300, y + 30, no, 15, IND, "Bold", width=34, align="center")
    # judul + dokumen
    t(f, 350, y + 20, nama, 13, T1, "Bold", width=380)
    t(f, 350, y + 40, note, 10, T2, width=420, lh=15)
    # tabel
    t(f, 790, y + 18, "Tabel", 9, T3, "Bold")
    t(f, 790, y + 34, f"{done}/{tot}", 17, T1, "Bold", family=MONO)
    barw = 120
    r(f, 790, y + 64, barw, 6, fill=DV, radius=3, name="bar-bg")
    r(f, 790, y + 64, int(barw * done / tot), 6,
      fill=ST["lengkap"][0] if done == tot else ORN, radius=3, name="bar")
    # dokumen
    t(f, 940, y + 18, "Dokumen", 9, T3, "Bold")
    t(f, 940, y + 34, f"{docs} butir", 12, T1, "Medium")
    t(f, 940, y + 54, "kriteria ini", 9.5, T2)
    # status
    k.badge(f, 1050, y + 20, "lengkap" if done == tot else ("sebagian" if done else "kosong"))
    # isu
    ix = 1150
    if issues:
        for j, isu in enumerate(issues[:2]):
            t(f, ix, y + 22 + j * 18, "· " + isu, 9.5, "#B54708", width=250)
    else:
        t(f, ix, y + 22, "· tidak ada temuan", 9.5, ST["lengkap"][0], width=250)
    t(f, ix, y + 62, "Buka kriteria", 9.5, IND, "Medium", width=120)
    y += ch + 10

# =========================================================
# 06 · Dokumen Pendukung
# =========================================================
f = f_(7520, 0, 1440, 1080, "06 · Dokumen Pendukung", fill=BG)
k.topbar(f)
k.sidebar(f, aktif="Dashboard")

t(f, 280, 84, "Dokumen Pendukung", 24, T1, "Bold")
t(f, 280, 114, "79 butir dokumen bukti dalam 8 kriteria · 41 sudah diunggah · penanggung jawab per butir", 11, T2)
r(f, 1180, 84, 108, 34, fill=SURF, radius=6, stroke=BD, name="btn-upload-all")
t(f, 1180, 92, "Unduh rekap", 10.5, "#344054", "Medium", width=108, align="center")
r(f, 1296, 84, 112, 34, fill=IND, radius=6, name="btn-upload")
t(f, 1296, 92, "Unggah", 11, "#FFFFFF", "Bold", width=112, align="center")

# left: kriteria list
r(f, 280, 152, 288, 812, fill=SURF, radius=10, stroke=BD, name="panel-kriteria")
t(f, 300, 168, "Per kriteria", 12, T1, "Bold")
t(f, 300, 186, "41 dari 79 butir terunggah", 10, T2)
k.hline(f, 300, 208, 248, DV)
kl = [("1", "Visi, Misi", 4, 4), ("2", "Kurikulum", 6, 9), ("3", "Penilaian", 5, 8),
      ("4", "Mahasiswa", 7, 11), ("5", "Dosen & Tendik", 8, 15), ("6", "Sarana & Dana", 7, 14),
      ("7", "Penjaminan Mutu", 3, 9), ("8", "Tata Kelola", 1, 8)]
ky = 222
for i, (no, nama, done, tot) in enumerate(kl):
    act = i == 2
    if act:
        r(f, 292, ky - 6, 264, 38, fill=IND_T, radius=6, name="krit-active")
    t(f, 304, ky, no, 10, IND if act else T3, "Bold", family=MONO)
    t(f, 324, ky, nama, 11, IND if act else "#344054", "Semibold" if act else "Regular", width=150)
    t(f, 452, ky, f"{done}/{tot}", 10, T2, family=MONO, width=44, align="right")
    r(f, 324, ky + 18, 148, 4, fill=DV, radius=2, name="kb")
    r(f, 324, ky + 18, int(148 * done / tot), 4,
      fill=IND if done == tot else ORN, radius=2, name="kf")
    ky += 44
k.hline(f, 300, 590, 248, DV)
t(f, 300, 606, "Penanggung jawab", 9, T3, "Bold")
for j, (nm, cnt) in enumerate([("Operator Kebidanan · Rina", 22), ("Admin UPPS · Yogi", 14),
                               ("Pimpinan · Wakil Dekan I", 5)]):
    t(f, 300, 628 + j * 20, nm, 10, "#344054", width=220)
    t(f, 500, 628 + j * 20, str(cnt), 10, T2, family=MONO, width=28, align="right")

# right: dokumen of kriteria 3
r(f, 588, 152, 820, 812, fill=SURF, radius=10, stroke=BD, name="panel-dokumen")
t(f, 608, 168, "Kriteria 3 · Penilaian", 12, T1, "Bold")
t(f, 608, 186, "8 butir · 5 terunggah · 3 belum", 10, T2)
r(f, 1210, 166, 178, 28, fill=SURF, radius=6, stroke=BD, name="search")
t(f, 1222, 173, "Cari dokumen", 10, T3)
k.hline(f, 608, 208, 780, DV)

docs = [
    ("3.1", "Prosedur operasional standar penilaian", "ok", "PDF · 1,2 MB · 12 Feb 2026", "Rina K."),
    ("3.2", "Buku catatan mahasiswa (logbook) & dokumen revisi strategi pengajaran", "ok", "PDF · 4,8 MB · 20 Feb 2026", "Rina K."),
    ("3.3", "Mekanisme remedial dan konseling", "ok", "PDF · 640 KB · 3 Mar 2026", "Yogi P."),
    ("3.4", "Cetak biru (blueprint) penilaian", "sebagian", "versi 2024 · kedaluwarsa 2025", "Yogi P."),
    ("3.5", "Prosedur mekanisme banding", "kosong", "belum ada berkas", "—"),
    ("3.6", "Dokumen sistem Penjaminan Mutu: perencanaan dan pelaksanaan", "ok", "PDF · 2,1 MB · 8 Apr 2026", "Rina K."),
    ("3.7", "Kebijakan dan prosedur penilaian sesuai tempat pembelajaran", "kosong", "belum ada berkas", "—"),
    ("3.8", "Lampiran hasil Uji Kompetensi CBT dan OSCE", "sebagian", "CBT ada · OSCE belum", "Yogi P."),
]
dy = 222
for no, judul, kind, meta, pic in docs:
    ch = 84
    if kind == "kosong":
        r(f, 600, dy, 796, ch, fill="#FCFCFD", radius=8, stroke="#EAECF0", name="doc")
    else:
        r(f, 600, dy, 796, ch, fill=SURF, radius=8, stroke=BD, name="doc")
    # ikon file
    r(f, 618, dy + 20, 30, 38, fill=IND_T if kind != "kosong" else DV, radius=5, name="file-ico")
    t(f, 618, dy + 30, "PDF", 7.5, IND if kind != "kosong" else T3, "Bold", width=30, align="center")
    t(f, 662, dy + 16, no, 9, T3, "Bold", family=MONO)
    t(f, 662, dy + 32, judul, 11, T1 if kind != "kosong" else T2, "Medium", width=470)
    t(f, 662, dy + 56, meta, 9.5, T3 if kind == "kosong" else T2, width=380)
    k.badge(f, 1148, dy + 16, kind)
    t(f, 1148, dy + 44, pic, 9.5, T2, width=120)
    t(f, 1290, dy + 20, "Unggah" if kind == "kosong" else "Ganti", 9.5, IND, "Medium",
      width=90, align="right")
    dy += ch + 8

# =========================================================
# 07 · Login (redesign — rail + kartu, bukan split SaaS)
# =========================================================
f = f_(9040, 0, 1440, 900, "07 · Login", fill=BG)
# rail kiri
r(f, 0, 0, 104, 900, fill=IND, name="rail")
r(f, 32, 40, 40, 40, fill="#FFFFFF", radius=9, name="logo-plate")
k.ell(f, 42, 50, 10, 10, IND, "larc-l")
k.ell(f, 52, 50, 10, 10, IND, "larc-r")
r(f, 46, 60, 14, 7, fill=ORN, radius=2, name="lnib")
r(f, 42, 69, 22, 4, fill=ORN, radius=2, name="lbook")
# wordmark vertikal (rotate text layer)
wm = t(f, -196, 470, "LAM-PTKes  ·  UBBG", 13, "#B8B4E4", "Bold", name="rail-wordmark")
k.ops[-1]["params"]["props"]["rotation"] = -90
k.ops[-1]["params"]["props"]["width"] = 300
r(f, 51, 820, 2, 40, fill="#4A44B0", name="rail-line")

# kanan: kartu form
CX, CY, CW = 420, 168, 480
t(f, CX, 96, "Masuk", 30, T1, "Bold", name="title")
t(f, CX, 138, "Akun kampus UBBG. Peran ditentukan admin UPPS.", 12, T2, width=CW, lh=18)

r(f, CX, CY, CW, 452, fill=SURF, radius=12, stroke=BD, name="card")
# field email
t(f, CX + 28, CY + 30, "Email", 10.5, "#344054", "Medium")
r(f, CX + 28, CY + 50, CW - 56, 44, fill=SURF, radius=7, stroke=BD, name="f-email")
t(f, CX + 42, CY + 64, "nama@ubbg.ac.id", 12, T3)
# field password (focus)
t(f, CX + 28, CY + 110, "Kata sandi", 10.5, "#344054", "Medium")
t(f, CX + CW - 128, CY + 110, "Lupa sandi?", 10.5, IND, "Medium", width=100, align="right")
r(f, CX + 28, CY + 130, CW - 56, 44, fill=SURF, radius=7, stroke=IND, sw=2, name="f-pass")
for i in range(11):
    k.ell(f, CX + 44 + i * 9, CY + 149, 6, 6, T1, "dot-pass")
r(f, CX + CW - 76, CY + 140, 36, 26, fill=IND_T, radius=5, name="eye-btn")
t(f, CX + CW - 76, CY + 145, "lihat", 8.5, IND, "Bold", width=36, align="center")
# remember
r(f, CX + 28, CY + 192, 16, 16, fill=SURF, radius=4, stroke="#98A2B3", name="chk")
t(f, CX + 52, CY + 193, "Ingat perangkat ini 30 hari", 11, "#344054")
# tombol
r(f, CX + 28, CY + 228, CW - 56, 46, fill=IND, radius=7, name="btn-masuk")
t(f, CX + 28, CY + 242, "Masuk", 13.5, "#FFFFFF", "Bold", width=CW - 56, align="center")
k.hline(f, CX + 28, CY + 300, CW - 56, DV)
# konteks akses
t(f, CX + 28, CY + 314, "Yang bisa kamu akses", 9, T3, "Bold")
for i, (nm, role) in enumerate([("Operator prodi", "isi 38 tabel · unggah dokumen"),
                                ("Admin UPPS", "master data · hak akses"),
                                ("Pimpinan / asesor", "periksa · kunci · ekspor")]):
    k.ell(f, CX + 30, CY + 338 + i * 20, 5, 5, IND if i == 0 else T3, "role-dot")
    t(f, CX + 42, CY + 332 + i * 20, nm, 10, "#344054", "Medium", width=110)
    t(f, CX + 156, CY + 332 + i * 20, role, 10, T2, width=250)
# footer kiri bawah
t(f, CX, 668, "Belum punya akun? Minta ke admin UPPS · admin.upps@ubbg.ac.id", 10.5, T2, width=480)
t(f, CX, 700, "Sistem Akreditasi LAM-PTKes v1.0 · Fakultas Ilmu Kesehatan UBBG", 9.5, T3, width=480)

# kanan: panel "status instrumen" — kenapa login ini bukan SaaS generik
PX = 1000
r(f, PX, 168, 380, 536, fill=SURF, radius=12, stroke=BD, name="side-card")
t(f, PX + 28, 196, "Instrumen yang dipakai", 12, T1, "Bold")
t(f, PX + 28, 216, "LAM-PTKes · data dukung 38 tabel", 10, T2)
k.hline(f, PX + 28, 242, 324, DV)
rows = [("38", "tabel data dukung"), ("79", "butir dokumen bukti"),
        ("8", "kriteria penilaian"), ("2", "program studi aktif")]
for i, (num, lab) in enumerate(rows):
    t(f, PX + 28, 260 + i * 46, num, 20, IND, "Bold", family=MONO, width=48)
    t(f, PX + 82, 266 + i * 46, lab, 11, "#344054", width=240)
    if i < 3:
        k.hline(f, PX + 28, 296 + i * 46, 324, DV)
k.hline(f, PX + 28, 452, 324, DV)
t(f, PX + 28, 470, "Periode berjalan", 9, T3, "Bold")
t(f, PX + 28, 488, "TS 2025/2026", 13, T1, "Bold")
t(f, PX + 28, 510, "Batas pengajuan 12 Agustus 2027", 10, T2)
k.badge(f, PX + 28, 536, "sebagian", "41/79 terunggah")
t(f, PX + 28, 574, "Prodi berjalan", 9, T3, "Bold")
r(f, PX + 28, 592, 152, 26, fill=ORN_T, radius=5, name="pc-a")
t(f, PX + 28, 598, "Kebidanan · D-III", 10, "#B54708", "Medium", width=152, align="center")
r(f, PX + 188, 592, 164, 26, fill=IND_T, radius=5, name="pc-b")
t(f, PX + 188, 598, "Keperawatan · S1 + Ners", 10, IND, "Medium", width=164, align="center")

if __name__ == "__main__":
    m = FigMCP(attach="7979")
    try:
        m.call("open_file", **{"path": "/Users/yogiprasetyasadewa/Documents/03_Proyek/LAM-PTKES/desain/mockup.figcraft"})
        res = m.call("batch", **{"commands": k.ops, "onError": "continue", "label": "Screens 05-07"})
        bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)] if isinstance(res, list) else []
        print("steps:", len(k.ops), "failed:", len(bad))
        for b in bad[:5]:
            print("  ", b)
        print(m.call("save_file", **{"path": "/Users/yogiprasetyasadewa/Documents/03_Proyek/LAM-PTKES/desain/mockup.figcraft"}))
    finally:
        m.close()
