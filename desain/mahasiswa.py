"""13 · Mahasiswa (F-05).

The page type differs from Dosen on purpose: 425 people cannot be a row list the
way 24 lecturers can. So this page is cohort-based — one row per angkatan, with
the status composition that six other tables divide up.

Its real job is data quality: Tabel 2, 3, 5, 9, 10, 11 all derive from this
master, so a broken row here silently corrupts six answers. Hence the import
gate leads the page, not the table.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 18440
PX, W = 280, 1128

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# ── ONE source of truth ──────────────────────────────────────────────
# angkatan, tahun akademik, masuk, lulus, do, cuti, ipk_rata
ANGKATAN = [
    ("TS-6", "2019/2020", 60, 55, 3, 0, 3.28),
    ("TS-5", "2020/2021", 58, 54, 2, 0, 3.31),
    ("TS-4", "2021/2022", 62, 58, 2, 0, 3.35),
    ("TS-3", "2022/2023", 55, 48, 2, 0, 3.38),
    ("TS-2", "2023/2024", 64, 41, 1, 0, 3.44),
    ("TS-1", "2024/2025", 60, 0, 1, 0, 3.47),
    ("TS", "2025/2026", 66, 0, 0, 0, 3.52),
]

MASUK = sum(a[2] for a in ANGKATAN)
LULUS = sum(a[3] for a in ANGKATAN)
DO = sum(a[4] for a in ANGKATAN)
CUTI = sum(a[5] for a in ANGKATAN)
AKTIF = MASUK - LULUS - DO - CUTI
assert MASUK == 425, MASUK
assert LULUS == 256 and DO == 11 and CUTI == 0, (LULUS, DO, CUTI)
assert AKTIF == 158, AKTIF

# masalah kualitas data — tiap baris menyebut tabel mana yang rusak
MASALAH = [
    ("8 NIM ganda", "Tabel 9 dan 10 menghitung mahasiswa dobel", BAD,
     "blokir simpan"),
    ("3 IPK kosong", "Tabel 3 tidak bisa hitung rata-rata", WARN, "peringatan"),
    ("1 tanggal lulus kosong", "Tabel 5 tidak bisa hitung tepat waktu", WARN,
     "peringatan"),
]

k = Kit()
r, t = k.rect, k.txt
k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1200,
                         "name": "13 · Mahasiswa", "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Dashboard")

# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Mahasiswa", 24, T1, "Bold", name="page-title")
t(f, PX, 110,
  f"F-05 · {MASUK} mahasiswa mengisi 6 tabel · didaftar per angkatan, bukan per orang",
  11, INK3)

r(f, 1168, 82, 120, 34, fill=SURF, radius=6, stroke=LINE, name="btn-import")
t(f, 1168, 91, "Impor Excel", 10.5, INK2, "Medium", width=120, align="center")
r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-add")
t(f, 1296, 91, "+ Mahasiswa", 11, "#FFFFFF", "Bold", width=112, align="center")

# ══ import gate — data quality leads, because 6 tables depend on it ══
GY = 148
r(f, PX, GY, W, 112, fill=BAD_BG, radius=10, name="gate")
r(f, PX, GY + 14, 3, 84, fill=BAD, radius=2, name="gate-bar")
t(f, PX + 20, GY + 14, "Impor terakhir 6 Nov 2026 · 12 baris perlu diperbaiki", 12.5,
  BAD, "Bold")
t(f, PX + 20, GY + 34,
  "Data ini sumber Tabel 2, 3, 5, 9, 10, dan 11. Satu NIM ganda membuat dua tabel "
  "menghitung orang yang sama dua kali.", 10, INK2, width=660)

iy = GY + 58
ix = PX + 20
for label, why, col, sev in MASALAH:
    r(f, ix, iy + 5, 5, 5, fill=col, radius=2, name="dot")
    t(f, ix + 12, iy, label, 10, col, "Medium", width=112)
    t(f, ix + 130, iy, why, 9.5, INK3, width=210)
    ix += 352

r(f, PX + W - 24 - 150, GY + 40, 150, 32, fill=BAD, radius=6, name="btn-fix")
t(f, PX + W - 24 - 150, GY + 49, "Perbaiki 12 baris", 10.5, "#FFFFFF", "Bold",
  width=150, align="center")

# ══ composition strip ═══════════════════════════════════════════════
SY = 278
r(f, PX, SY, W, 82, fill=SURF, radius=10, stroke=LINE, name="strip")
t(f, PX + 24, SY + 16, "Komposisi seluruh angkatan", 10, INK3, "Medium")

# one stacked bar: the whole population at a glance
BAR_X, BAR_Y, BAR_W, BAR_H = PX + 24, SY + 38, 700, 14
segs = [(LULUS, OK, "lulus"), (AKTIF, IND, "aktif"), (DO, BAD, "DO"),
        (CUTI, "#98A2B3", "cuti")]
bx = BAR_X
for val, col, lab in segs:
    if val == 0:
        continue
    w = round(BAR_W * val / MASUK)
    r(f, bx, BAR_Y, max(w - 2, 2), BAR_H, fill=col, radius=2, name=f"seg {lab}")
    bx += w

# inline legend with numbers, right of the bar
lx = PX + 750
for val, col, lab in segs:
    t(f, lx, SY + 32, str(val), 15, col if val else INK3, "Bold", family=MONO,
      width=44)
    t(f, lx, SY + 54, lab, 9, INK3, width=44)
    lx += 66

t(f, PX + 24, SY + 62, f"{MASUK} total masuk · 7 angkatan", 9, INK3)

# ══ cohort table ════════════════════════════════════════════════════
TY = 376
r(f, PX, TY, W, 536, fill=SURF, radius=10, stroke=LINE, name="cohort")
t(f, PX + 24, TY + 18, "Per angkatan", 12.5, T1, "Bold")
t(f, PX + 140, TY + 20,
  "satu baris per angkatan — angka ini yang dibagi ke Tabel 2, 3, 5, 9, 10",
  10, INK3)

CX = {"ang": 24, "tahun": 110, "masuk": 232, "lulus": 320, "do": 400,
      "aktif": 470, "ipk": 560, "bar": 650, "tag": 880}
r(f, PX + 24, TY + 44, W - 48, 1, fill=LINE, name="hr")
heads = [("Angkatan", "ang", 80), ("Tahun akademik", "tahun", 120),
         ("Masuk", "masuk", 70), ("Lulus", "lulus", 70), ("DO", "do", 60),
         ("Aktif", "aktif", 70), ("IPK rata", "ipk", 80)]
for lab, key, w in heads:
    al = "left" if key in ("ang", "tahun") else "right"
    t(f, PX + CX[key], TY + 54, lab, 9.5, INK3, "Medium", width=w, align=al)
t(f, PX + CX["tag"], TY + 54, "Dipakai di", 9.5, INK3, "Medium", width=200)
r(f, PX + 24, TY + 72, W - 48, 1, fill=LINE, name="hr")

FEEDS = {"TS-2": ["2", "3", "5", "9", "10"], "TS-1": ["2", "5", "9", "10"],
         "TS": ["5", "10"], "TS-3": ["2", "5", "10"], "TS-4": ["2", "5"],
         "TS-5": ["2"], "TS-6": ["2", "3"]}

ry = TY + 78
for i, (ang, tahun, masuk, lulus, do_, cuti, ipk) in enumerate(ANGKATAN):
    rh = 58
    if i % 2 == 1:
        r(f, PX + 25, ry, W - 50, rh, fill="#FCFCFD", name="zebra")
    aktif = masuk - lulus - do_ - cuti

    t(f, PX + CX["ang"], ry + 18, ang, 11, IND if ang == "TS" else INK2, "Bold",
      family=MONO, width=80)
    t(f, PX + CX["tahun"], ry + 18, tahun, 10.5, INK2, family=MONO, width=120)

    for key, val, col in [("masuk", masuk, INK2), ("lulus", lulus, OK),
                          ("do", do_, BAD if do_ else INK3),
                          ("aktif", aktif, IND if aktif else INK3)]:
        t(f, PX + CX[key], ry + 17, str(val), 11,
          col if key in ("lulus", "do", "aktif") else INK2,
          "Medium" if key != "masuk" else "Regular", family=MONO,
          width=70, align="right")

    t(f, PX + CX["ipk"], ry + 17, f"{ipk:.2f}".replace(".", ","), 11, INK2,
      family=MONO, width=80, align="right")

    # composition mini-bar: shows at a glance which cohort is still "open"
    MBX, MBW = PX + CX["bar"], 180
    r(f, MBX, ry + 26, MBW, 5, fill=LINE2, radius=3, name="mb-bg")
    if masuk:
        wl = round(MBW * lulus / masuk)
        wa = round(MBW * aktif / masuk)
        r(f, MBX, ry + 26, max(wl, 0), 5, fill=OK, radius=2, name="mb-lulus")
        if wa > 0:
            r(f, MBX + wl, ry + 26, max(wa, 2), 5, fill=IND, radius=2, name="mb-aktif")

    # which tables this cohort feeds
    tx = PX + CX["tag"]
    for tag in FEEDS.get(ang, []):
        r(f, tx, ry + 18, 22, 18, fill=LINE2, radius=4, name="tag")
        t(f, tx, ry + 21, tag, 8.5, INK2, family=MONO, width=22, align="center")
        tx += 26

    r(f, PX + 24, ry + rh, W - 48, 1, fill=LINE2, name="row-hr")
    ry += rh

# total row
r(f, PX + 24, ry + 2, W - 48, 1, fill=LINE, name="hr")
t(f, PX + CX["ang"], ry + 14, "Total", 11, T1, "Bold", width=80)
for key, val in [("masuk", MASUK), ("lulus", LULUS), ("do", DO), ("aktif", AKTIF)]:
    t(f, PX + CX[key], ry + 14, str(val), 11,
      BAD if key == "do" else T1, "Bold", family=MONO, width=70, align="right")
t(f, PX + CX["ipk"], ry + 14, "3,39", 11, IND, "Bold", family=MONO, width=80,
  align="right")

# the caveat belongs to the column, not to the total row
t(f, PX + CX["ipk"] - 44, TY + 54, "rata-rata sederhana", 8, INK3, width=124,
  align="right")

# ══ right/bottom: what breaks when a cohort is incomplete ═══════════
BY = 934
r(f, PX, BY, 660, 168, fill=SURF, radius=10, stroke=LINE, name="breaks")
t(f, PX + 24, BY + 18, "Kalau satu angkatan tidak lengkap", 12, T1, "Bold")
rows = [
    ("Tabel 2", "keberhasilan studi per tahun", "butuh lulus, DO, dan aktif"),
    ("Tabel 3", "IPK lulusan akademik", "butuh IPK tiap lulusan"),
    ("Tabel 5", "lulusan tepat waktu", "butuh tanggal masuk dan lulus"),
    ("Tabel 9 · 10", "total mahasiswa", "butuh NIM unik dan status"),
    ("Tabel 11", "mahasiswa tahap profesi", "tidak berlaku untuk D-III"),
]
byy = BY + 44
for tbl, what, need in rows:
    na = "tidak berlaku" in need
    t(f, PX + 24, byy, tbl, 10, INK3 if na else IND, "Bold", family=MONO, width=86)
    t(f, PX + 114, byy, what, 10, INK3 if na else INK2, width=200)
    t(f, PX + 320, byy, need, 9.5, INK3, width=310)
    if na:
        r(f, PX + 320, byy + 3, 310, 1, fill=LINE2, name="strike")
    byy += 24

# ══ import pipeline ═════════════════════════════════════════════════
DX = PX + 684
r(f, DX, BY, 444, 168, fill=SURF, radius=10, stroke=LINE, name="pipe")
t(f, DX + 24, BY + 18, "Cara isi", 12, T1, "Bold")
steps = [("1", "Unduh template", "kolom mengikuti master, 14 field"),
         ("2", "Isi atau ekspor dari SIAKAD", "NIM, angkatan, jalur, tahap, IPK"),
         ("3", "Unggah dan periksa", "12 baris ditolak → perbaiki → unggah ulang")]
sy = BY + 44
for no, title, sub in steps:
    r(f, DX + 24, sy + 2, 18, 18, fill=IND_T, radius=4, name="step-no")
    t(f, DX + 24, sy + 5, no, 9, IND, "Bold", width=18, align="center")
    t(f, DX + 52, sy, title, 10.5, T1, "Medium", width=350)
    t(f, DX + 52, sy + 15, sub, 9.5, INK3, width=360)
    sy += 40

# ══ footer ══════════════════════════════════════════════════════════
t(f, PX, 1120,
  "Satu baris = satu mahasiswa. Mahasiswa yang pindah tahap akademik → profesi "
  "memakai baris yang sama dengan riwayat tahap, bukan baris baru.", 9.5, INK3, width=W)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Mahasiswa")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/mhs.png")
        print("rendered")
