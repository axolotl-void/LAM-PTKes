"""09 · Periode Akreditasi (F-02).

Every table in the instrument labels its columns TS-n / SS-n relative to the
period set here. So the page's real job is: let the user verify that the
relative labels map to the years they expect BEFORE anyone types data.
That is what the preview matrix is for — it is the page, not decoration.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 12120
PX, RW = 280, 344
W_MAIN = 760
RX = PX + W_MAIN + 24

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD = "#067647", "#B54708", "#B42318"
OK_BG, WARN_BG, BAD_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2"

k = Kit()
r, t = k.rect, k.txt

# ── frame ────────────────────────────────────────────────────────────
k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1120,
                         "name": "09 · Periode Akreditasi",
                         "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Dashboard")


def label(x, y, s, w=None):
    t(f, x, y, s, 10, INK3, "Medium", width=w)


def segmented(x, y, opts, active, h=38, gap=6, size=10.5):
    cx = x
    out = []
    for lab, w in opts:
        cur = lab == active
        r(f, cx, y, w, h, fill=IND if cur else SURF, radius=6,
          stroke=None if cur else LINE, name=f"seg {lab}")
        t(f, cx, y + (h - 14) // 2, lab, size, "#FFFFFF" if cur else INK2,
          "Bold" if cur else "Regular", width=w, align="center")
        out.append((cx, w))
        cx += w + gap
    return cx


# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Periode Akreditasi", 24, T1, "Bold", name="page-title")
t(f, PX, 110, "F-02 · menentukan label TS-n dan SS-n di seluruh tabel instrumen", 11, INK3)

r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-save")
t(f, 1296, 91, "Simpan", 11, "#FFFFFF", "Bold", width=112, align="center")

# warning: changing TS relabels 17 tables
WY = 146
r(f, PX, WY, 1128, 58, fill=WARN_BG, radius=8, name="warn-strip")
r(f, PX, WY + 10, 3, 38, fill=WARN, radius=2, name="warn-bar")
t(f, PX + 16, WY + 12, "Periode ini dipakai 38 tabel; 17 di antaranya berformula dan label tahunnya bergeser mengikuti TS.",
  11, "#7A3E00", "Bold")
t(f, PX + 16, WY + 30,
  "Tahun absolut disimpan; label TS-n/SS-n dihitung saat tampil. Mengubah TS tidak menghapus data.",
  10, INK2, width=1000)

# ══ main: periode aktif ═════════════════════════════════════════════
y = 228
t(f, PX, y, "Periode berjalan", 13, T1, "Bold")
t(f, PX + 132, y + 2, "dipakai sebagai acuan semua tabel", 10, INK3)
y += 28

# TS row
label(PX, y, "TS — tahun akademik penuh terakhir")
label(PX + 400, y, "SS — tahun mulai tahap profesi")
y += 18
r(f, PX, y, 380, 44, fill=SURF, radius=6, stroke=LINE, name="input-ts")
t(f, PX + 12, y + 14, "2025/2026", 14, T1, "Bold", family=MONO)
t(f, PX + 380 - 12 - 60, y + 15, "TS", 10, IND, "Bold", family=MONO, width=60,
  align="right")
r(f, PX + 400, y, 380, 44, fill="#FCFCFD", radius=6, stroke=LINE, name="input-ss")
t(f, PX + 412, y + 14, "—", 14, INK3, "Bold", family=MONO)
t(f, PX + 400 + 380 - 12 - 74, y + 15, "tanpa profesi", 9, INK3, width=74, align="right")
y += 56

t(f, PX, y + 2, "D-III tidak punya tahap profesi, jadi SS tidak dipakai. Kolom SS-n di tabel profesi ikut kosong.",
  9.5, INK3, width=W_MAIN)
y += 26

label(PX, y, "Tanggal pengajuan")
label(PX + 400, y, "Batas masa berlaku akreditasi")
y += 18
r(f, PX, y, 380, 44, fill=SURF, radius=6, stroke=LINE, name="input-tgl")
t(f, PX + 12, y + 14, "8 November 2026", 12, T1, "Medium")
r(f, PX + 400, y, 380, 44, fill="#FCFCFD", radius=6, stroke=LINE, name="input-exp")
t(f, PX + 412, y + 14, "12 Agustus 2027 · 277 hari", 12, INK2, "Medium")
y += 60

# ══ preview matrix — the whole point of the page ═════════════════════
r(f, PX, y, 1128, 320, fill=SURF, radius=10, stroke=LINE, name="preview-card")
t(f, PX + 24, y + 18, "Pratinjau label tahun", 13, T1, "Bold")
t(f, PX + 210, y + 20, "dihitung dari TS 2025/2026 — bukan disimpan", 10, INK3)
PX2 = PX + 1128 - 24 - 108
r(f, PX2, y + 14, 108, 26, fill=SURF, radius=5, stroke=LINE, name="toggle")
t(f, PX2, y + 19, "Tabel 5", 10, INK2, "Medium", width=54, align="center")
t(f, PX2 + 54, y + 19, "Tabel 4", 10, INK2, "Medium", width=54, align="center")

r(f, PX + 24, y + 52, 1080, 1, fill=LINE2, name="hr")

# column headers
HX = PX + 24
cols = [("Label", 0, 130), ("Tahun akademik", 150, 170), ("Dipakai di", 340, 700)]
for lab, dx, w in cols:
    t(f, HX + dx, y + 64, lab, 9.5, INK3, "Medium", width=w)

# rows: generated from TS, never hardcoded
def year_label(offset):
    """TS-6 -> 2019/2020 for TS 2025/2026 (each TS step = one academic year)."""
    base = 2025 + offset
    return f"{base}/{base + 1}"

rows = [
    ("TS", 0, "Tabel 2, 3, 5, 9, 10, 17, 21, 33, 34"),
    ("TS-1", -1, "Tabel 2, 3, 5, 10, 17, 21, 33, 34"),
    ("TS-2", -2, "Tabel 2, 3, 5, 7, 13, 17, 21, 33, 34"),
    ("TS-3", -3, "Tabel 5, 10"),
    ("TS-4", -4, "Tabel 5, 10"),
    ("TS-5", -5, "Tabel 5"),
    ("TS-6", -6, "Tabel 5"),
    ("TS-7", -7, "Tabel 2"),
]
ry = y + 88
for i, (lab, off, used) in enumerate(rows):
    if i % 2 == 1:
        r(f, PX + 16, ry - 4, 1096, 26, fill="#FCFCFD", radius=4, name="zebra")
    t(f, HX, ry, lab, 10.5, IND if lab == "TS" else INK2, "Bold", family=MONO, width=60)
    t(f, HX + 150, ry, year_label(off), 10.5, INK2, family=MONO, width=170)
    t(f, HX + 340, ry, used, 9.5, INK3, width=700)
    ry += 26

t(f, PX + 24, y + 300,
  "11 dari 17 tabel berformula tercantum di sini · 8 label TS · 0 label SS · Tabel 2 memakai rentang terpanjang",
  9.5, INK3)

# ══ right rail ══════════════════════════════════════════════════════
RYY = 228
r(f, RX, RYY, RW, 194, fill=SURF, radius=10, stroke=LINE, name="rail-now")
t(f, RX + 20, RYY + 18, "Periode aktif", 12, T1, "Bold")
t(f, RX + 20, RYY + 40, "TS 2025/2026", 18, T1, "Bold", family=MONO)
t(f, RX + 20, RYY + 64, "1 Jul 2025 – 30 Jun 2026", 10, INK3, width=RW - 40)
r(f, RX + 20, RYY + 84, RW - 40, 1, fill=LINE2, name="hr")
stats = [("Tabel berformula", "17"), ("Tabel di pratinjau", "11"),
         ("Label dihitung", "8 TS"), ("Label SS", "0")]
sy = RYY + 96
for lab, val in stats:
    t(f, RX + 20, sy, lab, 10, INK2)
    t(f, RX + 20, sy, val, 10, T1, "Medium", family=MONO, width=RW - 40, align="right")
    sy += 22

r(f, RX, RYY + 208, RW, 152, fill=SURF, radius=10, stroke=LINE, name="rail-hist")
t(f, RX + 20, RYY + 226, "Riwayat periode", 12, T1, "Bold")
hist = [("2025/2026", "aktif · diajukan 2026", OK),
        ("2020/2021", "arsip · B 2022", INK3),
        ("2015/2016", "arsip · B 2017", INK3)]
hy = RYY + 252
for lab, sub, col in hist:
    r(f, RX + 20, hy + 4, 5, 5, fill=col, radius=2, name="dot")
    t(f, RX + 34, hy, lab, 10.5, INK2, "Medium", family=MONO)
    t(f, RX + 34, hy + 14, sub, 9.5, INK3)
    hy += 34

r(f, RX, RYY + 374, RW, 132, fill=ORN_T, radius=10, name="rail-note")
t(f, RX + 20, RYY + 392, "Kenapa bukan tahun absolut", 11, "#7A3E00", "Bold")
t(f, RX + 20, RYY + 412,
  "Instrumen memakai label relatif supaya berkas bisa diajukan ulang tanpa menghitung ulang kolom.",
  9.5, INK2, width=RW - 40, lh=14)
t(f, RX + 20, RYY + 464,
  "Kalau TS berubah, semua label bergeser otomatis.", 9.5, "#7A3E00", "Medium",
  width=RW - 40)

# ══ footer ═════════════════════════════════════════════════════════
t(f, PX, 1058,
  "Mengubah TS memindahkan rentang seluruh tabel. Data tahun lama tidak hilang — hanya label tampilannya yang berubah.",
  9.5, INK3, width=1128)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Periode Akreditasi")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/per.png")
        print("rendered /tmp/per.png")
