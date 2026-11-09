"""12 · Kerja Sama (F-19) — Tabel 38 + penopang Tabel 30.

Not a CRUD list. The reason this page exists inside an accreditation system:
Tabel 30 (wahana praktik) demands every practice site has a valid kerja sama
document, and Tabel 38 counts partnerships by country/activity/period.

So the page answers one question first — "which practice sites are uncovered?" —
then lists the documents behind them.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 16920
PX, W = 280, 1128

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# ── source of truth: 6 wahana in Tabel 30, each needs a document ─────
# nama, jenis, kota, negara, mulai, berakhir, kegiatan, punya_dokumen, laporan
MITRA = [
    ("RSUD dr. Zainoel Abidin", "RS Pendidikan Utama", "Banda Aceh", "Dalam", "2023-01", "2028-01",
     ["Pendidikan", "Penelitian"], True, True),
    ("RSIA Banda Aceh", "RS Bersalin", "Banda Aceh", "Dalam", "2024-03", "2027-03",
     ["Pendidikan"], True, False),
    ("Puskesmas Kuta Alam", "Puskesmas", "Banda Aceh", "Dalam", "2022-08", "2027-08",
     ["Pendidikan", "PkM"], True, True),
    ("PMB Nurhasanah, S.ST", "Praktik Mandiri Bidan", "Aceh Besar", "Dalam", "2025-02", "2028-02",
     ["Pendidikan"], True, False),
    ("RSJ Banda Aceh", "RS Jiwa", "Banda Aceh", "Dalam", "2021-06", "2024-06",
     ["Pendidikan"], False, False),
    ("Balai Kesehatan Ibu & Anak", "Balai", "Aceh Besar", "Dalam", "2026-01", "2029-01",
     ["Pendidikan", "PkM"], True, False),
]
TAHUN = ["TS-2", "TS-1", "TS"]

k = Kit()
r, t = k.rect, k.txt
k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1180,
                         "name": "12 · Kerja Sama", "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Dashboard")

# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Kerja Sama", 24, T1, "Bold", name="page-title")
t(f, PX, 110, "F-19 · Tabel 38 · 6 mitra menopang Tabel 30 wahana praktik", 11, INK3)

r(f, 1180, 82, 108, 34, fill=SURF, radius=6, stroke=LINE, name="btn-import")
t(f, 1180, 91, "Impor", 10.5, INK2, "Medium", width=108, align="center")
r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-add")
t(f, 1296, 91, "+ Mitra", 11, "#FFFFFF", "Bold", width=112, align="center")

# ══ the cross-table gate: Tabel 30 demands a document ═══════════════
GY = 148
uncovered = [m for m in MITRA if not m[7]]
r(f, PX, GY, W, 96, fill=BAD_BG if uncovered else OK_BG, radius=10,
  stroke=None, name="gate")
r(f, PX, GY + 14, 3, 68, fill=BAD if uncovered else OK, radius=2, name="gate-bar")

if uncovered:
    t(f, PX + 20, GY + 16, "Wahana praktik dengan dokumen kedaluwarsa", 12.5, BAD, "Bold")
    t(f, PX + 20, GY + 36,
      f"{len(uncovered)} dari {len(MITRA)} mitra tidak punya dokumen berlaku. "
      "Tabel 30 tetap bisa diisi, tetapi ekspor memunculkan peringatan.", 10, INK2,
      width=700)
    # the offender, named
    names = ", ".join(m[0] for m in uncovered)
    t(f, PX + 20, GY + 62, names + " — berlaku sampai Jun 2024, lewat TS", 10, BAD,
      "Medium", width=640)
    r(f, PX + W - 24 - 148, GY + 30, 148, 32, fill=SURF, radius=6, stroke=BAD,
      name="btn-fix")
    t(f, PX + W - 24 - 148, GY + 39, "Perbarui dokumen", 10.5, BAD, "Medium", width=148,
      align="center")

# ══ coverage strip ══════════════════════════════════════════════════
SY = 262
r(f, PX, SY, W, 76, fill=SURF, radius=10, stroke=LINE, name="strip")
stats = [("6", "mitra aktif", T1), ("5", "dokumen berlaku", OK),
         ("1", "dokumen kedaluwarsa", BAD), ("6", "dalam negeri", INK2),
         ("0", "luar negeri", INK3), ("2", "laporan pelaksanaan", WARN)]
sx = PX + 24
for i, (val, lab, col) in enumerate(stats):
    t(f, sx, SY + 16, val, 20, col, "Bold", family=MONO)
    t(f, sx, SY + 42, lab, 9.5, INK3)
    if i < len(stats) - 1:
        r(f, sx + 168, SY + 14, 1, 44, fill=LINE2, name="vr")
    sx += 184

# ══ Tabel 38 — the list ═════════════════════════════════════════════
TY = 358
r(f, PX, TY, W, 520, fill=SURF, radius=10, stroke=LINE, name="t38")
t(f, PX + 24, TY + 18, "Tabel 38 · Kerja sama UPPS", 12.5, T1, "Bold")
t(f, PX + 268, TY + 20, "urut berakhir terdekat", 10, INK3)

r(f, PX + 24, TY + 44, W - 48, 1, fill=LINE, name="hr")
heads = [("Instansi", 24, 300), ("Jenis", 330, 180), ("Kegiatan", 520, 170),
         ("Kurun waktu", 700, 150), ("Dokumen", 860, 110)]
for lab, dx, w in heads:
    t(f, PX + dx, TY + 54, lab, 9.5, INK3, "Medium", width=w)
t(f, PX + W - 24 - 96, TY + 54, "Laporan", 9.5, INK3, "Medium", width=96, align="right")
r(f, PX + 24, TY + 72, W - 48, 1, fill=LINE, name="hr")

# sort by expiry, soonest first — the page's job is to surface what lapses
def end_key(m):
    return m[5]

rows = sorted(MITRA, key=end_key)
ry = TY + 78
for i, (nama, jenis, kota, neg, mulai, akhir, keg, dok, lap) in enumerate(rows):
    rh = 66
    if i % 2 == 1:
        r(f, PX + 25, ry, W - 50, rh, fill="#FCFCFD", name="zebra")

    # expired/expiring marker on the left rail
    expired = akhir < "2026-11"
    rail = BAD if expired else OK
    r(f, PX + 25, ry + 12, 3, rh - 24, fill=rail, radius=2, name="rail")

    t(f, PX + 24 + 12, ry + 12, nama, 11.5, T1, "Medium", width=290)
    t(f, PX + 24 + 12, ry + 30, kota + " · " + neg, 9.5, INK3, width=290)

    t(f, PX + 330, ry + 12, jenis, 10, INK2, width=180)
    # kegiatan chips
    kx = PX + 520
    for kk in keg:
        w = 10 + len(kk) * 5.4
        r(f, kx, ry + 10, w, 18, fill=LINE2, radius=4, name="keg-chip")
        t(f, kx, ry + 13, kk, 8.5, INK2, "Medium", width=w, align="center")
        kx += w + 4

    t(f, PX + 700, ry + 12, f"{mulai} → {akhir}", 10, BAD if expired else INK2,
      "Medium" if expired else "Regular", family=MONO, width=150)
    if expired:
        t(f, PX + 700, ry + 28, "kedaluwarsa", 9, BAD, "Medium", width=150)

    if dok:
        r(f, PX + 860, ry + 11, 20, 20, fill=OK_BG, radius=4, name="dok-ok")
        t(f, PX + 860, ry + 15, "✓", 10, OK, "Bold", width=20, align="center")
        t(f, PX + 886, ry + 15, "MoU", 9.5, INK2, family=MONO)
    else:
        r(f, PX + 860, ry + 11, 20, 20, fill=BAD_BG, radius=4, name="dok-no")
        t(f, PX + 860, ry + 15, "!", 10, BAD, "Bold", width=20, align="center")
        t(f, PX + 886, ry + 15, "MoU · kedaluwarsa", 9.5, BAD, "Medium")

    t(f, PX + W - 24 - 96, ry + 14, "ada" if lap else "belum", 10,
      INK2 if lap else INK3, "Medium" if lap else "Regular", width=96, align="right")

    r(f, PX + 24, ry + rh, W - 48, 1, fill=LINE2, name="row-hr")
    ry += rh

# ══ Tabel 38 rekap — what the instrument actually counts ═════════════
BY = TY + 538
r(f, PX, BY, 660, 176, fill=SURF, radius=10, stroke=LINE, name="rekap38")
t(f, PX + 24, BY + 18, "Yang dihitung Tabel 38", 12, T1, "Bold")
t(f, PX + 24, BY + 38, "kolom COUNTA per kategori, bukan daftar bebas", 9.5, INK3)

rk = [("Dalam negeri", 6, OK), ("Luar negeri", 0, INK3),
      ("Pendidikan", 6, INK2), ("Penelitian", 1, INK2), ("PkM", 2, INK2)]
SCALE = max(v for _, v, _ in rk)     # all bars share one scale, 6 = full width
ry2 = BY + 64
for lab, val, col in rk:
    t(f, PX + 24, ry2, lab, 10, INK2, width=200)
    r(f, PX + 232, ry2 + 4, 300, 6, fill=LINE2, radius=3, name="bar-bg")
    if val > 0:
        r(f, PX + 232, ry2 + 4, max(round(300 * val / SCALE), 3), 6, fill=col,
          radius=3, name="bar")
    else:
        t(f, PX + 232 + 8, ry2 - 2, "tidak ada", 9, INK3)
    t(f, PX + 546, ry2 - 1, str(val), 10, T1, "Bold", family=MONO, width=30,
      align="right")
    ry2 += 21

# ══ right: dokumen yang kurang ══════════════════════════════════════
DX = PX + 684
r(f, DX, BY, 444, 176, fill=SURF, radius=10, stroke=LINE, name="todo")
t(f, DX + 24, BY + 18, "Yang perlu dilengkapi", 12, T1, "Bold")
items = [("RSJ Banda Aceh", "MoU kedaluwarsa Jun 2024 · laporan kosong", BAD),
         ("RSIA Banda Aceh", "laporan pelaksanaan kosong", WARN),
         ("PMB Nurhasanah", "laporan pelaksanaan kosong", WARN),
         ("Balai KIA", "laporan pelaksanaan kosong", WARN)]
iy = BY + 44
for nm, why, col in items:
    r(f, DX + 24, iy + 5, 5, 5, fill=col, radius=2, name="dot")
    t(f, DX + 38, iy, nm, 10.5, INK2, "Medium", width=180)
    t(f, DX + 38, iy + 14, why, 9.5, INK3, width=280)
    iy += 32

# ══ footer ══════════════════════════════════════════════════════════
t(f, PX, 1096,
  "Tabel 30 memakai daftar ini sebagai syarat: wahana tanpa dokumen sah memunculkan "
  "peringatan konsistensi, bukan blokir.", 9.5, INK3, width=W)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Kerja Sama")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/kerjasama.png")
        print("rendered")
