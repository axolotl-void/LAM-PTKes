"""11 · Keuangan (F-17) — Tabel 33 & 34.

The page exists because of one rule: persen penggunaan dana per tahun must total
100%. That is a closing constraint, so the design leads with it — three live
percentage bars, one per year, that either close or show their deficit.

Table 33 (penerimaan) sits beside table 34 (penggunaan) because asesor compares
them; the gap between the two is rendered, not left for the reader to subtract.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 15280
PX, W = 280, 1128

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2"

# ── one source of truth ──────────────────────────────────────────────
# Tabel 34: penggunaan per jenis, juta rupiah, tiga tahun
GUNA = [
    ("Pendidikan — gaji dosen", [1180, 1245, 1310]),
    ("Pendidikan — gaji tendik", [420, 445, 470]),
    ("Operasional pembelajaran", [260, 275, 290]),
    ("Operasional kantor", [140, 145, 150]),
    ("Penelitian", [95, 88, 102]),
    ("PkM", [55, 48, 58]),
]
YEARS = ["TS-2", "TS-1", "TS"]
TOTAL_GUNA = [sum(row[i] for _, row in GUNA) for i in range(3)]


def pct_largest_remainder(values, total):
    """Percentages that sum to exactly 100.0 at 1 decimal.

    Rounding each slice on its own leaves a 99.9% tail; the remainder is handed
    to the largest fractional parts so the printed column closes.
    """
    raw = [v / total * 100 for v in values]
    tenths = [int(x * 10) for x in raw]
    rem = [x * 10 - int(x * 10) for x in raw]
    short = 1000 - sum(tenths)
    order = sorted(range(len(values)), key=lambda i: -rem[i])
    for i in order[:short]:
        tenths[i] += 1
    return [t / 10 for t in tenths]


# persentase per jenis per tahun — kolom ini yang harus menutup 100
PCT = [pct_largest_remainder([row[i] for _, row in GUNA], TOTAL_GUNA[i])
       for i in range(3)]          # PCT[year][jenis]

# Tabel 33: penerimaan per sumber
TERIMA = [
    ("Mahasiswa (SPP, UKT)", [1620, 1690, 1755]),
    ("Usaha sendiri", [310, 325, 340]),
    ("Pemerintah pusat", [180, 195, 210]),
    ("Pemerintah daerah", [90, 85, 95]),
    ("Lain-lain", [60, 55, 70]),
]
TOTAL_TERIMA = [sum(row[i] for _, row in TERIMA) for i in range(3)]

for yi in range(3):
    assert abs(sum(PCT[yi]) - 100) < 1e-9, (yi, sum(PCT[yi]))

k = Kit()
r, t = k.rect, k.txt
k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1240,
                         "name": "11 · Keuangan", "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Dashboard")


def money(v):
    return f"{v:,}".replace(",", ".")


# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Keuangan", 24, T1, "Bold", name="page-title")
t(f, PX, 110, "F-17 · Tabel 33 penerimaan & Tabel 34 penggunaan · satuan juta rupiah", 11, INK3)

r(f, 1180, 82, 108, 34, fill=SURF, radius=6, stroke=LINE, name="btn-import")
t(f, 1180, 91, "Impor Excel", 10.5, INK2, "Medium", width=108, align="center")
r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-save")
t(f, 1296, 91, "Simpan", 11, "#FFFFFF", "Bold", width=112, align="center")

# ══ the closing rule, up front ══════════════════════════════════════
CY = 148
r(f, PX, CY, W, 132, fill=SURF, radius=10, stroke=LINE, name="close-card")
t(f, PX + 24, CY + 18, "Aturan penutup Tabel 34", 12.5, T1, "Bold")
t(f, PX + 240, CY + 20, "persentase tiap tahun harus tepat 100%", 10, INK3)
t(f, PX + W - 24 - 150, CY + 16, "blokir simpan bila ≠ 100", 9.5, BAD, "Medium",
  width=150, align="right")

BAR_W = 232
# six distinguishable shades: two neutrals + four brand-adjacent hues, all light
SHADES = ["#101828", "#475467", "#8A94A6", "#2F2A95", "#FF8200", "#C9CDD4"]
for i, yr in enumerate(YEARS):
    bx = PX + 24 + i * 372
    total_pct = round(sum(PCT[i]), 1)
    ok = abs(total_pct - 100) < 0.05
    t(f, bx, CY + 50, yr, 10, INK3, "Bold", family=MONO)
    t(f, bx + 44, CY + 44, f"{total_pct:.1f}%".replace(".", ","), 18,
      OK if ok else BAD, "Bold", family=MONO)
    t(f, bx + BAR_W, CY + 48, f"Rp {money(TOTAL_GUNA[i])} jt", 10, INK3, width=0)
    # segmented bar: each jenis is a slice, so 100% is visible by construction
    sx = bx
    for k_, row in enumerate(GUNA):
        w = round(BAR_W * PCT[i][k_] / 100)
        r(f, sx, CY + 74, max(w, 1), 9, fill=SHADES[k_], radius=0, name=f"slice {k_}")
        sx += w
    if ok:
        r(f, bx, CY + 92, BAR_W, 1, fill=OK, radius=0, name="close-line")
        t(f, bx, CY + 98, "menutup", 9, OK, "Medium")
    else:
        t(f, bx, CY + 98, f"kurang {100 - total_pct:.1f} poin".replace(".", ","), 9,
          BAD, "Medium")

# legend for the six slices — one line under all three bars
lgx = PX + 24
for k_, (nm, _) in enumerate(GUNA):
    short = nm.replace("Pendidikan — ", "").replace("Operasional ", "op. ")
    w = 12 + len(short) * 5.2
    r(f, lgx, CY + 122, 6, 6, fill=SHADES[k_], radius=1, name="leg")
    t(f, lgx + 10, CY + 119, short, 8.5, INK3, width=w)
    lgx += w + 16

# ══ Tabel 34 — penggunaan ═══════════════════════════════════════════
TY = 300
r(f, PX, TY, 660, 424, fill=SURF, radius=10, stroke=LINE, name="t34")
t(f, PX + 24, TY + 18, "Tabel 34 · Penggunaan dana", 12.5, T1, "Bold")
t(f, PX + 24, TY + 36, "per jenis · juta rupiah · persen dihitung sistem", 9.5, INK3)

COL = [24, 250, 340, 430, 520, 590]
heads = ["Jenis penggunaan", "TS-2", "TS-1", "TS", "Total"]
hy = TY + 62
r(f, PX + 24, hy, 612, 1, fill=LINE, name="hr")
for j, h in enumerate(heads):
    al = "left" if j == 0 else "right"
    t(f, PX + COL[j], hy + 8, h, 9, INK3, "Medium",
      width=90 if j else 200, align=al)
r(f, PX + 24, hy + 24, 612, 1, fill=LINE, name="hr")

ry = hy + 30
for k_, (nm, vals) in enumerate(GUNA):
    tot = sum(vals)
    t(f, PX + COL[0], ry + 4, nm, 10, INK2, width=210)
    for j, v in enumerate(vals):
        t(f, PX + COL[j + 1] + 62, ry + 4, money(v), 9.5, INK2, family=MONO,
          width=62, align="right")
    t(f, PX + COL[4] + 40, ry + 4, money(tot), 9.5, T1, "Medium", family=MONO,
      width=90, align="right")
    ry += 30

r(f, PX + 24, ry + 2, 612, 1, fill=LINE, name="hr")
t(f, PX + COL[0], ry + 12, "Total", 10.5, T1, "Bold")
for j, v in enumerate(TOTAL_GUNA):
    t(f, PX + COL[j + 1] + 62, ry + 12, money(v), 10.5, T1, "Bold", family=MONO,
      width=62, align="right")
t(f, PX + COL[4] + 40, ry + 12, money(sum(TOTAL_GUNA)), 10.5, IND, "Bold",
  family=MONO, width=90, align="right")

t(f, PX + 24, TY + 388,
  "Persentase per jenis sudah tertuang sebagai lebar segmen di kartu penutup; "
  "angka rupiah di sini adalah sumbernya.", 9, INK3, width=612)

# ══ Tabel 33 — penerimaan ═══════════════════════════════════════════
TX = PX + 684
r(f, TX, TY, 444, 424, fill=SURF, radius=10, stroke=LINE, name="t33")
t(f, TX + 24, TY + 18, "Tabel 33 · Penerimaan dana", 12.5, T1, "Bold")
t(f, TX + 24, TY + 36, "per sumber · juta rupiah", 9.5, INK3)

hy2 = TY + 62
r(f, TX + 24, hy2, 396, 1, fill=LINE, name="hr")
for j, h in enumerate(["Sumber", "TS-2", "TS-1", "TS"]):
    al = "left" if j == 0 else "right"
    t(f, TX + 24 + (0 if j == 0 else 150 + (j - 1) * 84), hy2 + 8, h, 9, INK3,
      "Medium", width=140 if j == 0 else 84, align=al)
r(f, TX + 24, hy2 + 24, 396, 1, fill=LINE, name="hr")

ry = hy2 + 30
for nm, vals in TERIMA:
    t(f, TX + 24, ry + 4, nm, 10, INK2, width=150)
    for j, v in enumerate(vals):
        t(f, TX + 174 + j * 84, ry + 4, money(v), 9.5, INK2, family=MONO, width=70,
          align="right")
    ry += 30

r(f, TX + 24, ry + 2, 396, 1, fill=LINE, name="hr")
t(f, TX + 24, ry + 12, "Total", 10.5, T1, "Bold")
for j, v in enumerate(TOTAL_TERIMA):
    t(f, TX + 174 + j * 84, ry + 12, money(v), 10.5, T1, "Bold", family=MONO,
      width=70, align="right")

# 33 vs 34 comparison — the cross-table rule, rendered
GY = ry + 46
for i, yr in enumerate(YEARS):
    gap = TOTAL_TERIMA[i] - TOTAL_GUNA[i]
    bad = gap < 0
    r(f, TX + 24, GY + i * 44, 396, 38, fill=BAD_BG if bad else OK_BG, radius=6,
      name=f"gap {yr}")
    t(f, TX + 36, GY + i * 44 + 8, yr, 9.5, INK3, "Bold", family=MONO)
    t(f, TX + 36, GY + i * 44 + 21,
      f"terima {money(TOTAL_TERIMA[i])} · guna {money(TOTAL_GUNA[i])}", 8.5, INK2,
      width=280)
    t(f, TX + 340, GY + i * 44 + 13, f"{'+' if gap >= 0 else ''}{money(gap)}",
      11, OK if not bad else BAD, "Bold", family=MONO, width=60, align="right")

t(f, TX + 24, GY + 140, "Selisih penerimaan − penggunaan. Negatif berarti defisit.", 9,
  INK3, width=396)

# ══ footer ══════════════════════════════════════════════════════════
t(f, PX, 1150,
  "Tabel 34 diblokir simpan bila salah satu tahun tidak berjumlah 100%. "
  "Selisih 33 vs 34 memunculkan peringatan konsistensi, bukan blokir.", 9.5, INK3,
  width=W)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Keuangan")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/keu.png")
        print("rendered")
