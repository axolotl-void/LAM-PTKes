"""Dashboard v4 — 'lembar penilaian' (assessment dossier), not a SaaS card grid.

Bold moves, each replacing an AI-slop pattern:
  * no cards at all — paper with ruled divisions, like an official document
  * real scale contrast: numerals at 108 / 56 / 34 px (v3 was all 10-12 px)
  * one vertical column chart (7 criteria) instead of thin horizontal bars
  * one solid indigo block as the focal point (the blockers)
  * section numerals 01/02/03 in mono, editorial masthead with a 2px rule
  * warm paper (#F7F5F0) instead of SaaS grey (#F5F6F9)
"""
from kit import Kit, ORN, MONO, UI
from live_client import L

FRAME = 2
CONTENT_X0 = 256

PAPER = "#F7F5F0"
INK = "#14110E"
INK2 = "#57534E"
INK3 = "#8A857E"
RULE = "#DAD4C8"
RULE2 = "#EDE9E0"
IND = "#2F2A95"
IND_SOFT = "#EAE8F6"
OK, WARN, BAD = "#0F6B45", "#A85A0A", "#A82219"

# ── the one source of truth ───────────────────────────────────────────
KRIT = [
    ("K1", "Visi & Misi", 0, 0),
    ("K2", "Kurikulum", 1, 1),
    ("K3", "Penilaian", 7, 4),
    ("K4", "Mahasiswa", 6, 5),
    ("K5", "Dosen & Tendik", 14, 13),
    ("K6", "Sarana & Dana", 6, 5),
    ("K7", "Penjaminan Mutu", 3, 3),
    ("K8", "Tata Kelola", 1, 1),
]
TOTAL = sum(t for *_, t, d in KRIT)
DONE = sum(d for *_, t, d in KRIT)
BELUM = TOTAL - DONE
assert (TOTAL, DONE, BELUM) == (38, 32, 6), (TOTAL, DONE, BELUM)


def clear_content():
    node = L.call("get_node", id=FRAME, depth=3)
    dead = []

    def walk(n, depth=0):
        for c in n.get("children", []):
            x, y = c.get("x") or 0, c.get("y") or 0
            if depth == 0 and x >= CONTENT_X0 and y >= 80:
                dead.append(c["id"])
            else:
                walk(c, depth + 1)

    walk(node)
    if dead:
        L.batch([{"command": "edit.delete", "params": {"ids": dead}}], label="Clear v3")
    return len(dead)


k = Kit()
r, t = k.rect, k.txt
f = FRAME
L_ = 280                      # left margin of content
R_ = 1408                     # right edge
W_ = R_ - L_


def rule(y, w=1, color=RULE):
    r(f, L_, y, W_, w, fill=color, name="rule")


def vrule(x, y, h, color=RULE):
    r(f, x, y, 1, h, fill=color, name="vrule")


def sec(n, label, y):
    t(f, L_, y, n, 10, ORN, "Bold", family=MONO)
    t(f, L_ + 26, y, label, 10, INK3, "Medium")


# ══ masthead ══════════════════════════════════════════════════════════
r(f, 0, 56, 248, 968, fill=PAPER, name="sidebar-paper")   # blend sidebar into paper
t(f, L_, 78, "LAM-PTKes · Program Studi Kebidanan · D-III · TS 2025/2026", 10, INK3,
  "Medium", name="eyebrow")
t(f, L_, 96, "Kesiapan Akreditasi", 34, INK, "Bold", name="masthead")
t(f, L_, 138, "Enam tabel dan dua blokir memisahkan berkas ini dari ekspor.", 12, INK2)

# export sits at the baseline of the masthead, no box
t(f, R_ - 118, 100, "Ekspor berkas", 11, INK, "Medium", width=118, align="right")
t(f, R_ - 118, 116, "PDF · XLSX", 10, INK3, width=118, align="right")
rule(168, 2, INK)

# ══ ledger: three figures, divided by rules, no boxes ═════════════════
sec("01", "STATUS BERKAS", 182)
LY, LH = 202, 132
vrule(L_ + 372, LY, LH)
vrule(L_ + 748, LY, LH)

# — peringkat
t(f, L_, LY, "Peringkat", 10, INK3, "Medium")
t(f, L_, LY + 14, "B", 108, INK, "Bold", name="rank-big")
sx = L_ + 96
for s, w in [("C", 34), ("B", 34), ("A", 34), ("Unggul", 54)]:
    cur = s == "B"
    r(f, sx, LY + 26, w, 22, fill=INK if cur else None, radius=0,
      stroke=None if cur else RULE, name=f"sc {s}")
    t(f, sx, LY + 31, s, 9.5, PAPER if cur else INK3, "Bold" if cur else "Medium",
      width=w, align="center")
    sx += w + 4
t(f, sx + 8, LY + 31, "277 hari", 10, INK2)
t(f, L_, LY + 112, "Baik Sekali · berlaku s.d. 12 Agu 2027", 10, INK2)

# — tabel
t(f, L_ + 396, LY, "Data dukung", 10, INK3, "Medium")
t(f, L_ + 396, LY + 10, str(DONE), 56, INK, "Bold", family=MONO, name="num-done")
t(f, L_ + 396 + 74, LY + 34, f"/ {TOTAL} tabel", 12, INK3)
t(f, L_ + 396, LY + 88, f"{BELUM} belum lengkap", 11, BAD, "Medium")
t(f, L_ + 396, LY + 106, "4 sebagian · 2 kosong", 10, INK3)

# — dokumen
t(f, L_ + 772, LY, "Dokumen bukti", 10, INK3, "Medium")
t(f, L_ + 772, LY + 10, "41", 56, INK, "Bold", family=MONO, name="num-docs")
t(f, L_ + 772 + 64, LY + 34, "/ 79 butir", 12, INK3)
t(f, L_ + 772, LY + 88, "38 belum diunggah", 11, INK2, "Medium")
t(f, L_ + 772, LY + 106, "7 kedaluwarsa", 10, INK3)

# — kesiapan, jujur
t(f, L_ + 1128 - 96, LY, "Siap ekspor", 10, INK3, "Medium", width=96, align="right")
t(f, L_ + 1128 - 96, LY + 18, "Belum", 26, BAD, "Bold", width=96, align="right")
t(f, L_ + 1128 - 96, LY + 56, "2 blokir", 11, INK2, "Medium", width=96, align="right")
t(f, L_ + 1128 - 96, LY + 74, "1 peringatan", 10, INK3, width=96, align="right")

rule(348, 1, RULE)

# ══ 02 · column chart, 8 criteria ═════════════════════════════════════
sec("02", "TABEL LENGKAP PER KRITERIA", 366)
t(f, L_, 364, "skala 0–100%", 10, INK3, width=W_, align="right")

CH_Y, CH_H = 400, 176          # chart baseline area
COL_W, GAP = 46, 40
n = len(KRIT)
chart_w = n * COL_W + (n - 1) * GAP
start_x = L_ + (W_ - chart_w) // 2
rule(CH_Y + CH_H, 1, INK)       # baseline

for i, (kid, nama, tot, done) in enumerate(KRIT):
    x = start_x + i * (COL_W + GAP)
    if tot == 0:
        t(f, x, CH_Y + CH_H - 40, "—", 14, INK3, "Bold", width=COL_W, align="center")
        t(f, x, CH_Y + CH_H + 12, "tanpa", 9, INK3, width=COL_W, align="center")
        t(f, x, CH_Y + CH_H + 24, "tabel", 9, INK3, width=COL_W, align="center")
        t(f, x, CH_Y + CH_H + 44, kid, 9, INK3, "Bold", family=MONO, width=COL_W,
          align="center")
        continue
    h_full = CH_H - 8
    r(f, x, CH_Y, COL_W, h_full, fill=RULE2, name="col-track")
    h = round(h_full * done / tot)
    full = done == tot
    r(f, x, CH_Y + (h_full - h), COL_W, h, fill=INK if full else IND,
      name=f"col {kid}")
    t(f, x, CH_Y + (h_full - h) - 22, f"{done}/{tot}", 11, INK if full else IND, "Bold",
      family=MONO, width=COL_W, align="center")
    t(f, x, CH_Y + CH_H + 12, nama, 10, INK, "Medium", width=COL_W, align="center")
    t(f, x, CH_Y + CH_H + 26, "lengkap" if full else f"sisa {tot - done}", 9, INK3,
      width=COL_W, align="center")
    t(f, x, CH_Y + CH_H + 44, kid, 9, INK3, "Bold", family=MONO, width=COL_W,
      align="center")

rule(648, 1, RULE)

# ══ 03 · tindakan + blokir ════════════════════════════════════════════
sec("03", "YANG MENGHALANGI", 664)
BX, BW = 1010, 398
r(f, BX, 690, BW, 246, fill=IND, radius=0, name="indigo-block")
t(f, BX + 28, 714, "BLOKIR EKSPOR", 9, "#B9B5E8", "Bold", family=MONO)
t(f, BX + 28, 728, "2", 60, "#FFFFFF", "Bold", family=MONO, name="blk-n")
t(f, BX + 76, 758, "berkas tidak bisa", 11, "#C9C6EC")
t(f, BX + 76, 772, "dikirim hari ini", 11, "#C9C6EC")
r(f, BX + 28, 806, BW - 56, 1, fill="#4A44B0", name="blk-rule")
t(f, BX + 28, 820, "Tabel 15 dan 16 tidak sinkron", 11.5, "#FFFFFF", "Medium", width=BW - 56)
t(f, BX + 28, 838, "Dosen 24 vs 23 nama · buka Tabel 15", 10, "#B9B5E8", width=BW - 56)
t(f, BX + 28, 864, "Tabel 34 persen bukan 100%", 11.5, "#FFFFFF", "Medium", width=BW - 56)
t(f, BX + 28, 882, "TS-1 = 96,4% · buka Tabel 34", 10, "#B9B5E8", width=BW - 56)

# aksi list, left of the block
acts = [
    ("Penilaian", "Varian Tabel 5 belum dipilih", "Pilih varian", "3"),
    ("Mahasiswa", "1 lulusan belum terlacak", "Isi tracer", "1"),
    ("Dosen", "Tabel 15 vs 16 selisih 1 nama", "Rapikan nama", "1"),
    ("Sarana", "Persentase Tabel 34 belum 100%", "Hitung ulang", "1"),
]
ay = 692
for i, (krit, note, act, sisa) in enumerate(acts):
    t(f, L_, ay, f"0{i + 1}", 10, INK3, "Bold", family=MONO)
    t(f, L_ + 34, ay - 2, krit, 13, INK, "Bold")
    t(f, L_ + 34, ay + 16, note, 10.5, INK2)
    t(f, L_ + 470, ay + 2, act + " →", 10.5, IND, "Medium")
    t(f, L_ + 620, ay - 2, sisa, 18, INK, "Bold", family=MONO, width=40, align="right")
    t(f, L_ + 620, ay + 20, "sisa", 9, INK3, width=40, align="right")
    ay += 60
    if i < len(acts) - 1:
        rule(ay - 12, 1, RULE2)

rule(954, 1, RULE)

# ══ footer ledger: paling lambat, bukan tabel penuh ═══════════════════
t(f, L_, 968, "Data terbaru", 10, INK3, "Medium")
latest = [("T-01", "Substansi Kurikulum", "Lengkap", OK),
          ("T-03", "IPK Lulusan", "Lengkap", OK),
          ("T-17", "Aktivitas Dosen", "Terkunci", "#175CD3"),
          ("T-05", "Lulusan Tepat Waktu", "Sebagian", WARN)]
lx = L_ + 120
for tag, nama, status, col in latest:
    r(f, lx - 10, 966, 3, 14, fill=col, name="tick")
    t(f, lx, 966, tag, 9.5, INK3, family=MONO, width=32)
    t(f, lx + 36, 966, nama, 10.5, INK2, width=140)
    t(f, lx + 36, 982, status, 9, col, "Medium", width=90)
    lx += 200
t(f, R_ - 96, 966, "Semua 38 →", 10.5, INK, "Medium", width=96, align="right")

if __name__ == "__main__":
    removed = clear_content()
    # paper background for the whole frame
    L.batch([{"command": "node.set",
              "params": {"ids": [FRAME], "fill": PAPER}}], label="Paper bg")
    res = L.batch(k.ops, label="Dashboard v4")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"removed {removed}, drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:160])
    L.call("export", format="png", scale=2, ids=[FRAME], path="/tmp/v4.png")
    print("rendered /tmp/v4.png")
