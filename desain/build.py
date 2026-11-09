#!/usr/bin/env python3
"""Generate FigCraft script for LAM-PTKes mockup (UBBG brand theme)."""
import json, sys
from icons import ICONS as ICON_PATH

S = []  # steps
_n = [0]

def nid(prefix):
    _n[0] += 1
    return f"{prefix}{_n[0]}"

# ---------- tokens (UBBG) ----------
IND = "#2F2A95"   # indigo logo
IND_D = "#211D6B"
IND_T = "#EEEDF8"  # tint
ORN = "#FF8200"   # orange logo
ORN_T = "#FFF3E6"
BG = "#F5F6F9"
SURF = "#FFFFFF"
BD = "#E4E7EC"
DV = "#F2F4F7"
T1 = "#101828"
T2 = "#667085"
T3 = "#98A2B3"
ST = {
    "kosong":   ("#98A2B3", "#F2F4F7"),
    "sebagian": ("#B54708", "#FFFAEB"),
    "lengkap":  ("#067647", "#ECFDF3"),
    "terkunci": ("#175CD3", "#EFF8FF"),
    "na":       ("#667085", "#F9FAFB"),
    "gagal":    ("#B42318", "#FEF3F2"),
}
UI = "Helvetica Neue"
MONO = "Menlo"

def node(cmd, parent=None, **p):
    d = dict(p)
    if parent:
        d["parent"] = {"$ref": parent}
    s = {"command": cmd, "as": nid("s"), "params": d}
    S.append(s)
    return s["as"]

def frame(x, y, w, h, name, fill=SURF, clip=True):
    return node("frame.create", x=x, y=y, width=w, height=h, name=name,
                props={"fill": fill, "clip": clip})

def rect(parent, x, y, w, h, fill=None, radius=0, stroke=None, sw=1, name="rect"):
    props = {"name": name, "cornerRadius": radius}
    if fill: props["fill"] = fill
    if stroke: props["stroke"] = stroke; props["strokeWeight"] = sw
    return node("shape.rectangle", parent=parent, x=x, y=y, width=w, height=h, props=props)

def txt(parent, x, y, s, size=13, color=T1, weight="Regular", width=None, h=None,
        align=None, family=UI, name=None):
    props = {"fontSize": size, "fontFamily": family, "fontStyle": weight, "fill": color}
    if width: props["width"] = width
    if align: props["textAlign"] = align
    return node("text.create", parent=parent, x=x, y=y, text=s, name=name or s[:28], props=props)

def badge(parent, x, y, label, kind="kosong", w=None, size=10):
    fg, bgc = ST[kind]
    w = w or (14 + len(label) * (size * 0.62))
    rect(parent, x, y, w, 20, fill=bgc, radius=5, stroke=fg if kind == "na" else None,
         name=f"badge {label}")
    txt(parent, x, y + 4, label, size=size, color=fg, weight="Medium", width=w, align="center")
    return x + w

def hline(parent, x, y, w, color=BD):
    rect(parent, x, y, w, 1, fill=color, name="divider")

def vline(parent, x, y, h, color=BD):
    rect(parent, x, y, 1, h, fill=color, name="divider-v")

# ---------- shell ----------
def topbar(f, sel="Kebidanan"):
    rect(f, 0, 0, 1440, 56, fill=SURF, name="topbar")
    hline(f, 0, 55, 1440)
    rect(f, 20, 15, 26, 26, fill=IND, radius=7, name="logo-bg")
    rect(f, 26, 21, 14, 14, fill=ORN, radius=4, name="logo-acc")
    txt(f, 58, 19, "Sistem Akreditasi LAM-PTKes", 14, T1, "Bold")
    txt(f, 58, 34, "Universitas Bina Bangsa Getsempena", 10, T2)
    txt(f, 900, 20, "Fakultas Ilmu Kesehatan  ·  UPPS FIK", 11, T2, width=380, align="right")
    rect(f, 1308, 14, 28, 28, fill=IND_T, radius=14, name="avatar")
    txt(f, 1308, 20, "YP", 10, IND, "Bold", width=28, align="center")

def sidebar(f, prodi="Kebidanan", accent=ORN, jenjang="D-III", profesi=False,
            aktif="Dashboard", y0=56, h=968):
    x0 = 0
    rect(f, x0, y0, 248, h, fill=SURF, name="sidebar")
    vline(f, 248, y0, h)

    # switch prodi
    rect(f, 16, y0+20, 216, 34, fill="#F9FAFB", radius=8, stroke=BD, name="switch")
    # active segment
    rect(f, 17, y0+21, 107, 32, fill=accent, radius=7, name="switch-active")
    txt(f, 17, y0+29, prodi, 11, "#FFFFFF", "Bold", width=107, align="center")
    other = "Keperawatan" if prodi == "Kebidanan" else "Kebidanan"
    txt(f, 124, y0+29, other, 11, T2, "Medium", width=107, align="center")

    # periode
    rect(f, 16, y0+64, 216, 30, fill=SURF, radius=6, stroke=BD, name="periode")
    txt(f, 26, y0+71, f"TS 2025/2026", 11, T1, "Medium")
    txt(f, 100, y0+71, f"· {jenjang}", 11, T2)
    txt(f, 200, y0+71, "▾", 10, T2, width=24, align="center")

    y = y0 + 112
    def label(s, color=T3):
        nonlocal y
        txt(f, 20, y, s, 9, color, "Bold")
        y += 20

    def ic(parent, x, yy, key, color):
        node("vector.create", parent=parent, x=x, y=yy, path=ICON_PATH[key],
             name=f"ico {key}",
             props={"stroke": color, "strokeWeight": 1.6, "strokeCap": "round",
                    "strokeJoin": "round", "fill": "none"})

    def row(s, badge_txt=None, active=False, dot=None, badge_kind=None, key=None):
        nonlocal y
        if active:
            rect(f, 8, y - 5, 232, 28, fill=IND_T, radius=6, name=f"nav-active {s}")
            rect(f, 8, y - 2, 3, 22, fill=IND, radius=2, name="nav-active-bar")
        icolor = IND if active else (accent if dot else "#475467")
        if key:
            ic(f, 20, y + 1, key, icolor)
        tx = 44 if key else (34 if dot else 22)
        if dot and not key:
            rect(f, 22, y + 3, 6, 6, fill=dot, radius=3, name=f"dot {s}")
        txt(f, tx, y, s, 11, IND if active else "#344054", "Semibold" if active else "Regular", width=150)
        if badge_txt:
            if badge_kind:
                fg, bgc = ST[badge_kind]
                bw = 8 + len(badge_txt) * 6
                rect(f, 232 - bw, y - 1, bw, 18, fill=bgc, radius=4, name="badge")
                txt(f, 232 - bw, y + 2, badge_txt, 9, fg, "Medium", width=bw, align="center")
            else:
                txt(f, 22, y, "", 9)
                txt(f, 200, y + 1, badge_txt, 9, T3, family=MONO, width=32, align="right")
        y += 30

    label("MENU")
    row("Dashboard", active=(aktif == "Dashboard"), key="dashboard")
    row("Ringkasan 8 Kriteria", active=(aktif == "Ringkasan"), key="summary")

    y += 8
    label("BERSAMA UPPS")
    row("Identitas UPPS & Prodi", "F-01", key="identity")
    row("Periode Akreditasi", "F-02", key="period")
    row("Dosen & Tendik", "F-06", key="lecturer")
    row("Keuangan", "F-17", key="finance")
    row("Kerja Sama", "F-19", key="partner")
    row("Pengguna & Hak Akses", "F-22", key="access")

    y += 8
    label(f"PRODI: {prodi.upper()}", accent)
    row("Mahasiswa", "F-05", dot=accent, key="student")
    row("Kurikulum & MK", "F-07", dot=accent, key="curriculum", active=(aktif == "Kurikulum"))
    row("Sarana & Wahana", "F-15", dot=accent, key="facility")
    row("Pustaka & Jurnal", "F-16", dot=accent, key="library")
    row("Uji Kompetensi", "F-09", dot=accent, key="competency")
    row("Survei & Tracer", "F-10", dot=accent, key="survey")

    y += 6
    hline(f, 16, y, 216, DV)
    y += 14
    row("Data Dukung 38 Tabel", "32/38", badge_kind="sebagian", key="archive")
    row("Dokumen Pendukung", "41/79", badge_kind="sebagian", key="document")
    row("Konsistensi", "2 gagal", badge_kind="gagal", key="consistency")

    # export
    y += 10
    txt(f, 20, y, "EKSPOR PRODI INI", 9, T3, "Bold")
    y += 20
    rect(f, 16, y, 104, 30, fill=ORN, radius=6, name="btn-pdf")
    txt(f, 16, y + 8, "PDF", 11, "#FFFFFF", "Bold", width=104, align="center")
    rect(f, 128, y, 104, 30, fill=SURF, radius=6, stroke=BD, name="btn-xlsx")
    txt(f, 128, y + 8, "XLSX", 11, "#344054", "Medium", width=104, align="center")

# ---------- frame 1: dashboard ----------
f1 = frame(0, 0, 1440, 1024, "01 · Dashboard", fill=BG)
topbar(f1)
sidebar(f1)

CX = 280
rect(f1, CX, 88, 700, 40, fill=BG, name="hdr")
txt(f1, CX, 86, "Dashboard Akreditasi", 24, T1, "Bold")
txt(f1, CX, 116, "Kebidanan · D-III · TS 2025/2026  ·  diperbarui 8 Nov 2026, 21:04 WIB", 11, T2)
rect(f1, 1272, 86, 136, 36, fill=ORN, radius=7, name="btn-ekspor")
txt(f1, 1272, 95, "Ekspor PDF", 12, "#FFFFFF", "Bold", width=136, align="center")

# KPI cards
kpis = [
    ("KELENGKAPAN DATA DUKUNG", "32 / 38", "6 tabel belum lengkap", 0.84, IND),
    ("DOKUMEN PENDUKUNG", "41 / 79", "38 butir belum diunggah", 0.52, IND),
    ("KONSISTENSI LINTAS TABEL", "8 / 10", "2 aturan gagal", 0.80, ST["gagal"][0]),
    ("PERINGKAT AKREDITASI", "B", "Baik Sekali · berakhir 12 Agu 2027", None, "#067647"),
]
cw = (1128 - 3 * 16) // 4
for i, (lab, val, sub, prog, col) in enumerate(kpis):
    cx = CX + i * (cw + 16)
    rect(f1, cx, 152, cw, 106, fill=SURF, radius=10, stroke=BD, name=f"kpi {i}")
    txt(f1, cx + 16, 166, lab, 9, T3, "Bold")
    txt(f1, cx + 16, 184, val, 26, T1, "Bold")
    if prog is not None:
        rect(f1, cx + 16, 222, cw - 32, 5, fill=DV, radius=3, name="bar-bg")
        rect(f1, cx + 16, 222, int((cw - 32) * prog), 5, fill=col, radius=3, name="bar")
    txt(f1, cx + 16, 234, sub, 10, T2)

# 8 kriteria card
rect(f1, CX, 278, 748, 332, fill=SURF, radius=10, stroke=BD, name="card-kriteria")
txt(f1, CX + 20, 296, "Progres 8 Kriteria", 14, T1, "Bold")
txt(f1, CX + 20, 316, "Jumlah tabel lengkap per kriteria penilaian instrumen", 10, T2)
txt(f1, CX + 748 - 120, 298, "32 dari 38 tabel", 10, T2, width=100, align="right")
hline(f1, CX + 20, 336, 708, DV)

krit = [
    ("1", "Visi, Misi, Tujuan & Strategi", 1, 1),
    ("2", "Kurikulum", 1, 1),
    ("3", "Penilaian", 4, 7),
    ("4", "Mahasiswa", 5, 6),
    ("5", "Dosen, Tendik, Penelitian & PkM", 11, 14),
    ("6", "Sarana, Prasarana & Keuangan", 5, 6),
    ("7", "Penjaminan Mutu", 2, 3),
    ("8", "Tata Kelola & Administrasi", 1, 1),
]
for i, (no, nama, done, tot) in enumerate(krit):
    ry = 350 + i * 32
    rect(f1, CX + 20, ry, 22, 22, fill=IND_T, radius=5, name="krit-no")
    txt(f1, CX + 20, ry + 5, no, 10, IND, "Bold", width=22, align="center")
    txt(f1, CX + 52, ry + 4, nama, 11, "#344054")
    rect(f1, CX + 400, ry + 8, 260, 6, fill=DV, radius=3, name="krit-bar-bg")
    colr = ST["lengkap"][1] and (IND if done == tot else ORN)
    rect(f1, CX + 400, ry + 8, int(260 * done / tot), 6, fill=colr, radius=3, name="krit-bar")
    txt(f1, CX + 672, ry + 4, f"{done}/{tot}", 10, T2, family=MONO, width=50, align="right")

# right column: konsistensi + tindakan
RX = CX + 764
rect(f1, RX, 278, 364, 332, fill=SURF, radius=10, stroke=BD, name="card-tindakan")
txt(f1, RX + 20, 296, "Perlu Tindakan", 14, T1, "Bold")
txt(f1, RX + 20, 316, "Dihitung dari aturan validasi & konsistensi", 10, T2)
hline(f1, RX + 20, 336, 324, DV)

acts = [
    ("Tabel 15 vs 16 tidak sinkron", "Jumlah dosen tetap 24, daftar nama 23", "gagal"),
    ("Tabel 34 persen ≠ 100%", "TS-1 total 96,4%", "gagal"),
    ("Tabel 5 varian belum dipilih", "Jenjang D-III belum dikonfirmasi", "sebagian"),
]
ay = 348
for t, s, kind in acts:
    fg, bgc = ST[kind]
    rect(f1, RX + 20, ay, 324, 62, fill=bgc, radius=8, name="act")
    rect(f1, RX + 20, ay, 3, 62, fill=fg, radius=2, name="act-bar")
    txt(f1, RX + 34, ay + 12, t, 11, T1, "Medium", width=296)
    txt(f1, RX + 34, ay + 32, s, 10, T2, width=296)
    ay += 70

# bottom: checklist preview
rect(f1, CX, 630, 1128, 358, fill=SURF, radius=10, stroke=BD, name="card-checklist")
txt(f1, CX + 20, 648, "Checklist Data Dukung", 14, T1, "Bold")
txt(f1, CX + 200, 652, "khusus prodi Kebidanan · tabel 4, 6, 7, 8, 11, 30 ditandai tidak berlaku", 10, T2)
txt(f1, CX + 1128 - 140, 650, "Lihat semua 38 tabel", 11, IND, "Medium", width=120, align="right")
hline(f1, CX + 20, 676, 1088, DV)

rows = [
    ("01", "Substansi Kurikulum (kuliah/praktikum/praktik)", "Req", "lengkap", "239 baris"),
    ("02", "Persentase Keberhasilan Studi", "Req", "sebagian", "3 dari 7 tahun kosong"),
    ("03", "Data IPK Lulusan Tahap Akademik", "Req", "lengkap", "otomatis dari master"),
    ("05", "Data Lulusan Tepat Waktu — varian Sarjana", "Req", "sebagian", "varian belum dipilih"),
    ("07", "Uji Kompetensi (UKMPS-CBT)", "Profesi", "na", "tidak berlaku untuk D-III"),
    ("17", "Aktivitas Dosen Tetap", "Req", "terkunci", "dikunci 6 Nov 2026"),
]
for i, (no, judul, tag, kind, note) in enumerate(rows):
    ry = 690 + i * 46
    if i % 2 == 0:
        rect(f1, CX + 12, ry, 1104, 44, fill="#FCFCFD", radius=6, name="row")
    txt(f1, CX + 24, ry + 8, f"T-{no}", 11, T2, family=MONO)
    txt(f1, CX + 74, ry + 7, judul, 11, T1, "Medium", width=560)
    txt(f1, CX + 74, ry + 24, note, 9, T3, width=420)
    if tag == "Profesi":
        rect(f1, CX + 660, ry + 12, 54, 20, fill="#F2F4F7", radius=5, name="tag")
        txt(f1, CX + 660, ry + 16, "Profesi", 9, T2, "Medium", width=54, align="center")
    fg, bgc = ST[kind]
    blab = {"lengkap": "Lengkap", "sebagian": "Sebagian", "na": "Tak berlaku",
            "terkunci": "Terkunci"}[kind]
    bw = 24 + len(blab) * 5.6
    rect(f1, CX + 760, ry + 12, bw, 20, fill=bgc, radius=5, stroke=fg if kind == "na" else None,
         name="badge")
    txt(f1, CX + 760, ry + 16, {"lengkap":"Lengkap","sebagian":"Sebagian","na":"Tak berlaku","terkunci":"Terkunci"}[kind], 9, fg, "Medium", width=bw, align="center")
    txt(f1, CX + 1000, ry + 14, "Isi tabel →", 10, IND, "Medium", width=104, align="right")

# ---------- frame 2: data dukung ----------
f2 = frame(1520, 0, 1440, 1024, "02 · Data Dukung", fill=BG)
topbar(f2)
sidebar(f2, aktif="Dashboard")

txt(f2, 280, 86, "Data Dukung — 38 Tabel", 24, T1, "Bold")
txt(f2, 280, 116, "Pilih tabel untuk mulai mengisi. Status dihitung dari validasi field wajib dan aturan lintas tabel.", 11, T2)

# filter chips
chips = ["Semua (38)", "K1 · Visi Misi (1)", "K2 · Kurikulum (1)", "K3 · Penilaian (7)",
         "K4 · Mahasiswa (6)", "K5 · Dosen (14)", "K6 · Sarana (6)", "K7 · Mutu (3)", "K8 · Tata Kelola (1)"]
cx = 280
for i, c in enumerate(chips):
    w = 14 + len(c) * 6.2
    active = i == 0
    rect(f2, cx, 146, w, 30, fill=IND if active else SURF, radius=15,
         stroke=None if active else BD, name="chip")
    txt(f2, cx, 154, c, 10.5, "#FFFFFF" if active else "#344054",
        "Medium", width=w, align="center")
    cx += w + 8

# table grid 3 x 3
cards = [
    ("01", "Substansi Kurikulum (kuliah/praktikum/praktik) di Tahap Akademik dan Tahap Profesi", "K2", "lengkap", 100, "239 baris · impor Excel"),
    ("02", "Persentase Keberhasilan Studi", "K3", "sebagian", 57, "3 dari 7 tahun kosong"),
    ("03", "Data IPK Lulusan Tahap Akademik di Program Studi", "K3", "lengkap", 100, "otomatis dari master mahasiswa"),
    ("04", "Data IPK Lulusan Tahap Profesi", "K3", "na", 0, "khusus profesi · D-III"),
    ("05", "Data Lulusan Tepat Waktu Tahap Akademik", "K3", "sebagian", 40, "varian belum dipilih"),
    ("06", "Data Lulusan Tepat Waktu Tahap Profesi", "K3", "na", 0, "khusus profesi · D-III"),
    ("07", "Uji Kompetensi Mahasiswa (UKMPS-CBT)", "K3", "na", 0, "khusus profesi · D-III"),
    ("08", "Uji Kompetensi Mahasiswa (UKMPS-OSCE)", "K3", "na", 0, "khusus profesi · D-III"),
    ("09", "Data Total Mahasiswa pada Unit Pengelola Program Studi", "K4", "terkunci", 100, "dikunci 6 Nov 2026"),
]
cw2 = (1128 - 2 * 16) // 3
for i, (no, judul, krit, kind, pct, note) in enumerate(cards):
    r, c = divmod(i, 3)
    x = 280 + c * (cw2 + 16)
    y = 196 + r * 176
    rect(f2, x, y, cw2, 160, fill=SURF, radius=10, stroke=BD, name=f"card {no}")
    rect(f2, x + 16, y + 16, 34, 22, fill=IND_T, radius=5, name="no")
    txt(f2, x + 16, y + 20, f"T-{no}", 10, IND, "Bold", family=MONO, width=34, align="center")
    txt(f2, x + 58, y + 20, krit, 9, T3, "Bold")
    # badge kanan atas
    lab = {"lengkap": "Lengkap", "sebagian": "Sebagian", "na": "Tak berlaku",
           "terkunci": "Terkunci"}[kind]
    fg, bgc = ST[kind]
    bw = 24 + len(lab) * 5.6
    rect(f2, x + cw2 - bw - 16, y + 16, bw, 20, fill=bgc, radius=5,
         stroke=fg if kind == "na" else None, name="badge")
    txt(f2, x + cw2 - bw - 16, y + 20, lab, 9, fg, "Medium", width=bw, align="center")
    txt(f2, x + 16, y + 52, judul, 11, T1, "Medium", width=cw2 - 32)
    txt(f2, x + 16, y + 100, note, 9.5, T2, width=cw2 - 32)
    if kind != "na":
        rect(f2, x + 16, y + 122, cw2 - 32, 5, fill=DV, radius=3, name="bar-bg")
        rect(f2, x + 16, y + 122, int((cw2 - 32) * pct / 100), 5,
             fill=IND if kind != "sebagian" else ORN, radius=3, name="bar")
        txt(f2, x + cw2 - 60, y + 132, f"{pct}%", 9, T2, family=MONO, width=44, align="right")
    else:
        txt(f2, x + 16, y + 124, "Tidak diwajibkan untuk prodi tanpa tahap profesi", 9, T3, width=cw2 - 32)


# ---------- frame 3: form tabel 5 ----------
f3 = frame(3040, 0, 1440, 1024, "03 · Form Tabel 5", fill=BG)
topbar(f3)
sidebar(f3, aktif="Kurikulum")

txt(f3, 280, 82, "Data Dukung", 11, T2)
txt(f3, 280, 94, "Tabel 5 · Data Lulusan Tepat Waktu Tahap Akademik", 22, T1, "Bold")
labx = 280
for lbl, kind in [("Kebidanan", None), ("K3 · Penilaian", None), ("Varian: Sarjana (TS-6..TS)", None)]:
    w = 14 + len(lbl) * 6.2
    rect(f3, labx, 126, w, 22, fill="#F2F4F7", radius=5, name="chip")
    txt(f3, labx, 131, lbl, 9.5, T2, "Medium", width=w, align="center")
    labx += w + 8
rect(f3, 1268, 84, 140, 34, fill=SURF, radius=6, stroke=BD, name="btn-simpan")
txt(f3, 1268, 92, "Simpan draf", 11, "#344054", "Medium", width=140, align="center")
rect(f3, 1268, 126, 140, 34, fill=IND, radius=6, name="btn-kunci")
txt(f3, 1268, 134, "Ajukan kunci", 11, "#FFFFFF", "Bold", width=140, align="center")

# table card
TX, TY = 280, 166
rect(f3, TX, TY, 780, 620, fill=SURF, radius=10, stroke=BD, name="table-card")

# columns
colw = [46, 96] + [64] * 7 + [150]
colx = [TX]
for w in colw[:-1]:
    colx.append(colx[-1] + w)
TW = sum(colw)
HDR1 = 48
HDR2 = 28

# header level 1
rect(f3, TX, TY, TW, HDR1, fill=IND, radius=0, name="hdr1")
txt(f3, TX + 8, TY + 16, "No", 10, "#FFFFFF", "Bold", width=30, align="center")
txt(f3, TX + 54, TY + 16, "Tahun Masuk", 10, "#FFFFFF", "Bold", width=80, align="center")
txt(f3, TX + 142, TY + 16, "Jumlah Mahasiswa Reguler per Tahun Masuk", 10, "#FFFFFF", "Bold",
    width=7 * 64, align="center")
txt(f3, TX + 590, TY + 12, "Jumlah Lulusan", 10, "#FFFFFF", "Bold", width=150, align="center")
txt(f3, TX + 590, TY + 26, "s.d. TS (dari jumlah masuk)", 8, "#C9C6EC", width=150, align="center")

# header level 2
rect(f3, TX, TY + HDR1, TW, HDR2, fill=IND_T, name="hdr2")
for i, lab in enumerate(["TS-6", "TS-5", "TS-4", "TS-3", "TS-2", "TS-1", "TS"]):
    x = TX + 142 + i * 64
    txt(f3, x, TY + HDR1 + 8, lab, 10, IND, "Bold", family=MONO, width=64, align="center")
# border under header
hline(f3, TX, TY + HDR1 + HDR2, TW, IND)

data = {
    "TS-6": [40, 38, 34, 31, 29, 27, 25],
    "TS-5": [44, 41, 38, 35, 33, 30, 28],
    "TS-4": [46, 44, 40, 37, 34, 31, 29],
    "TS-3": [50, 47, 43, 39, 36, 33, 30],
    "TS-2": [52, 49, 45, 41, 37, 34, 32],
    "TS-1": [54, 51, 47, 43, 39, 36, 33],
    "TS":   [56, 53, 49, 45, 41, 38, 35],
}
RH = 40
rowlabels = ["TS-6", "TS-5", "TS-4", "TS-3", "TS-2", "TS-1", "TS"]
penanda = {(0, 0): "a", (0, 6): "b", (3, 3): "d", (3, 6): "e"}
for r, rl in enumerate(rowlabels):
    y = TY + HDR1 + HDR2 + r * RH
    if r % 2 == 1:
        rect(f3, TX, y, TW, RH, fill="#FCFCFD", name="row-alt")
    hline(f3, TX, y + RH, TW, DV)
    txt(f3, TX + 8, y + 13, str(r + 1), 10, T2, family=MONO, width=30, align="center")
    txt(f3, TX + 54, y + 12, rl, 10.5, T1, "Medium", family=MONO, width=80, align="center")
    for c, val in enumerate(data[rl]):
        x = TX + 142 + c * 64
        filled = r <= c
        txt(f3, x, y + 12, str(val) if filled else "—", 10.5,
            T1 if filled else T3, family=MONO, width=64, align="center")
        if (r, c) in penanda:
            rect(f3, x + 3, y + 4, 16, 15, fill=ORN_T, radius=3, name="penanda-bg")
            txt(f3, x + 3, y + 6, penanda[(r, c)], 8.5, "#B54708", "Bold", width=16,
                align="center")
    # computed column
    total = sum(data[rl][r:])
    tx = TX + 590
    rect(f3, tx, y, 150, RH, fill=IND_T, name="col-hitung")
    txt(f3, tx, y + 12, str(total), 10.5, IND, "Bold", family=MONO, width=150, align="center")
    if r in (0, 3, 6):
        pk = {0: "c", 3: "f", 6: "g"}[r]
        rect(f3, tx + 4, y + 4, 16, 15, fill=ORN_T, radius=3, name="penanda-bg")
        txt(f3, tx + 4, y + 6, pk, 8.5, "#B54708", "Bold", width=16, align="center")

# verticals
for x in colx[1:] + [TX + TW]:
    vline(f3, x, TY, HDR1 + HDR2 + RH * 7, BD)

# footer note + total
FY = TY + HDR1 + HDR2 + RH * 7
rect(f3, TX, FY, TW, 44, fill="#F9FAFB", name="table-total")
txt(f3, TX + 54, FY + 15, "Total", 10.5, T1, "Bold", width=80, align="center")
colsum = [sum(data[k][i] for k in data if k != "x") for i in range(7)]
for i, v in enumerate(colsum):
    txt(f3, TX + 142 + i * 64, FY + 15, str(v), 10.5, T1, "Bold", family=MONO, width=64,
        align="center")
txt(f3, TX + 590, FY + 15, str(sum(sum(data[k][r] for r in range(7)) for k in data)), 10.5, IND,
    "Bold", family=MONO, width=150, align="center")
txt(f3, TX, FY + 60, "Penanda (a)–(g) wajib tetap tercantum pada tabel saat dicetak — tidak boleh dihapus.", 9.5, T2)
txt(f3, TX, FY + 76, "Sel biru dihitung sistem dari master mahasiswa (tahun masuk, tanggal lulus). Sel abu bernilai — belum ada angkatan.", 9.5, T2)

# right panel: aturan
PX = 1080
rect(f3, PX, TY, 328, 300, fill=SURF, radius=10, stroke=BD, name="panel-aturan")
txt(f3, PX + 18, TY + 18, "Aturan & Sumber Angka", 13, T1, "Bold")
hline(f3, PX + 18, TY + 42, 292, DV)
rules = [
    ("Kolom TS-6 … TS", "digeser otomatis dari tahun_ts = 2025/2026"),
    ("Jumlah masuk", "master mahasiswa · angkatan + jalur reguler"),
    ("Lulusan s.d. TS", "hitung dari tanggal_lulus ≤ TS"),
    ("Varian tabel", "dipilih dari jenjang prodi, bukan manual"),
    ("Penanda a–g", "wajib tercetak, ikut ke ekspor PDF"),
]
ry = TY + 56
for t, s in rules:
    txt(f3, PX + 18, ry, t, 10.5, T1, "Medium", width=292)
    txt(f3, PX + 18, ry + 15, s, 9.5, T2, width=292)
    ry += 44

rect(f3, PX, TY + 316, 328, 148, fill=ST["sebagian"][1], radius=10, name="panel-status")
rect(f3, PX, TY + 316, 3, 148, fill=ST["sebagian"][0], radius=2, name="bar")
txt(f3, PX + 18, TY + 332, "Status: Sebagian", 12, ST["sebagian"][0], "Bold")
txt(f3, PX + 18, TY + 354, "4 dari 10 field wajib kosong", 10, T2, width=290)
txt(f3, PX + 18, TY + 372, "• Varian tabel belum dipilih", 10, "#344054", width=290)
txt(f3, PX + 18, TY + 388, "• Angkatan TS-6 kolom TS-4..TS kosong", 10, "#344054", width=290)
txt(f3, PX + 18, TY + 404, "• Jumlah lulusan belum cocok dengan Tabel 3", 10, "#344054", width=290)
txt(f3, PX + 18, TY + 428, "Tabel ini tidak bisa dikunci sebelum 3 poin di atas selesai.", 9.5, T2, width=290)


# ---------- frame 4: sidebar states ----------
f4 = frame(4560, 0, 620, 1024, "04 · Sidebar State", fill=BG)
txt(f4, 0, 20, "Sidebar — state per prodi (Kebidanan)", 18, T1, "Bold")
txt(f4, 0, 44, "Zona Bersama UPPS tidak berubah saat switch.", 11, T2)

# prodi A
rect(f4, 0, 82, 272, 900, fill=SURF, radius=12, stroke=BD, name="panel-a")
sidebar(f4, prodi="Kebidanan", accent=ORN, jenjang="D-III", profesi=False,
        y0=82, h=900)
txt(f4, 0, 996, "Kebidanan · D-III · 32 tabel wajib · 6 tabel tidak berlaku", 10, T2)

f5 = frame(4730, 0, 620, 1024, "04b · Sidebar Keperawatan", fill=BG)
rect(f5, 0, 20, 272, 900, fill=SURF, radius=12, stroke=BD, name="panel-b")
sidebar(f5, prodi="Keperawatan", accent=IND, jenjang="S1 + Ners", profesi=True,
        y0=20, h=900)
txt(f5, 0, 940, "Keperawatan · S1 + Ners · 38 tabel wajib · aksen indigo", 10, T2)
txt(f5, 0, 958, "Zona prodi lebih panjang: Uji Kompetensi + Mahasiswa Profesi aktif.", 10, T2)

# ---------- output ----------
print(json.dumps(S))
