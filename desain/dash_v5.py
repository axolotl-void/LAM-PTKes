"""Dashboard v5 — 'lembar penilaian', fixed after review.

Fixes vs v4:
  * sidebar no longer covered (the paper rect was painted over it)
  * rank glyph sized so the validity line never collides
  * chart columns wide enough for single-line labels; K1 gets a real empty slot
  * section 03 numerals on one right-aligned column
  * navy block padded, its copy no longer ragged
  * one meaning per colour: black = lengkap, indigo = belum, no legend needed
"""
from kit import Kit, ORN, MONO
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
IND_D = "#241F73"
OK, WARN, BAD = "#0F6B45", "#A85A0A", "#A82219"

KRIT = [
    ("K1", "Visi", 0, 0),
    ("K2", "Kurikulum", 1, 1),
    ("K3", "Penilaian", 7, 4),
    ("K4", "Mahasiswa", 6, 5),
    ("K5", "Dosen", 14, 13),
    ("K6", "Sarana", 6, 5),
    ("K7", "Mutu", 3, 3),
    ("K8", "Tata Kelola", 1, 1),
]
TOTAL = sum(t for *_, t, d in KRIT)
DONE = sum(d for *_, t, d in KRIT)
BELUM = TOTAL - DONE
assert (TOTAL, DONE, BELUM) == (38, 32, 6)


def clear_content():
    """Delete only what this dashboard drew — never the sidebar."""
    node = L.call("get_node", id=FRAME, depth=3)
    dead = []

    def walk(n, depth=0):
        for c in n.get("children", []):
            x, y = c.get("x") or 0, c.get("y") or 0
            w = c.get("width") or 0
            if depth != 0:
                walk(c, depth + 1)
                continue
            # topbar widgets stay; sidebar (x<250) stays
            if x >= CONTENT_X0 and y >= 70:
                dead.append(c["id"])
            elif x < 250 and y < 60:
                continue
            else:
                walk(c, depth + 1)

    walk(node)
    if dead:
        L.batch([{"command": "edit.delete", "params": {"ids": dead}}], label="Clear")
    return len(dead)


k = Kit()
r, t = k.rect, k.txt
f = FRAME
L_, R_ = 280, 1408
W_ = R_ - L_


def rule(y, w=1, color=RULE):
    r(f, L_, y, W_, w, fill=color, name="rule")


def vrule(x, y, h, color=RULE):
    r(f, x, y, 1, h, fill=color, name="vrule")


def sec(n, label, y):
    t(f, L_, y, n, 10, ORN, "Bold", family=MONO)
    t(f, L_ + 28, y, label, 10, INK3, "Medium")


# ══ masthead ══════════════════════════════════════════════════════════
t(f, L_, 76, "LAM-PTKes · Kebidanan · D-III · TS 2025/2026", 10, INK3, "Medium",
  name="eyebrow")
t(f, L_, 94, "Kesiapan Akreditasi", 34, INK, "Bold", name="masthead")
t(f, L_, 136, "Enam tabel dan dua blokir memisahkan berkas ini dari ekspor.", 12, INK2)

t(f, R_ - 130, 96, "Ekspor berkas", 11, INK, "Medium", width=130, align="right")
t(f, R_ - 130, 112, "PDF · XLSX", 10, INK3, width=130, align="right")
rule(166, 2, INK)

# ══ 01 · status ═══════════════════════════════════════════════════════
sec("01", "STATUS BERKAS", 180)
LY, LH = 200, 138
vrule(L_ + 380, LY, LH)
vrule(L_ + 756, LY, LH)
vrule(L_ + 1100, LY, LH)

# peringkat
t(f, L_, LY, "Peringkat", 10, INK3, "Medium")
t(f, L_, LY + 16, "B", 84, INK, "Bold", name="rank-big")
sx = L_ + 78
for s, w in [("C", 32), ("B", 32), ("A", 32), ("Unggul", 52)]:
    cur = s == "B"
    r(f, sx, LY + 22, w, 20, fill=INK if cur else None, radius=0,
      stroke=None if cur else RULE, name=f"sc {s}")
    t(f, sx, LY + 26, s, 9, PAPER if cur else INK3, "Bold" if cur else "Medium",
      width=w, align="center")
    sx += w + 4
t(f, L_ + 78, LY + 52, "277 hari lagi", 10, INK2)
t(f, L_ + 78, LY + 70, "Baik Sekali", 11, INK, "Medium")
t(f, L_ + 78, LY + 86, "berlaku s.d. 12 Agu 2027", 10, INK3)

# data dukung
t(f, L_ + 404, LY, "Data dukung", 10, INK3, "Medium")
t(f, L_ + 404, LY + 12, str(DONE), 54, INK, "Bold", family=MONO, name="num-done")
t(f, L_ + 404, LY + 76, f"dari {TOTAL} tabel", 11, INK3)
t(f, L_ + 404, LY + 98, f"{BELUM} belum lengkap", 11.5, BAD, "Medium")
t(f, L_ + 404, LY + 116, "4 sebagian · 2 kosong", 10, INK3)

# dokumen
t(f, L_ + 780, LY, "Dokumen bukti", 10, INK3, "Medium")
t(f, L_ + 780, LY + 12, "41", 54, INK, "Bold", family=MONO, name="num-docs")
t(f, L_ + 780, LY + 76, "dari 79 butir", 11, INK3)
t(f, L_ + 780, LY + 98, "38 belum diunggah", 11.5, INK2, "Medium")
t(f, L_ + 780, LY + 116, "7 dari 41 yang ada perlu diperbarui", 10, INK3)

# status ekspor — right edge, safe padding
CW = 200
COLX = R_ - CW          # right-aligned inside the frame: 1208..1408
t(f, COLX, LY, "Siap ekspor", 10, INK3, "Medium", width=CW)
t(f, COLX, LY + 16, "Belum", 24, BAD, "Bold", width=CW)
r(f, COLX, LY + 54, CW, 1, fill=RULE2, name="mini-rule")
t(f, COLX, LY + 64, "2 blokir", 11, INK2, "Medium", width=CW)
t(f, COLX, LY + 82, "1 peringatan", 10, INK3, width=CW)

rule(346, 1, RULE)

# ══ 02 · chart ════════════════════════════════════════════════════════
sec("02", "TABEL LENGKAP PER KRITERIA", 362)
LCAP_W = 300
t(f, R_ - LCAP_W, 362, "tiap kolom = 1 kriteria · skala 0–100%", 10, INK3,
  width=LCAP_W, align="right")

CH_Y, CH_H = 396, 168
COL_W, GAP = 78, 34
n = len(KRIT)
chart_w = n * COL_W + (n - 1) * GAP
start_x = L_ + (W_ - chart_w) // 2
r(f, L_, CH_Y + CH_H, W_, 1, fill=INK, name="baseline")

for i, (kid, nama, tot, done) in enumerate(KRIT):
    x = start_x + i * (COL_W + GAP)
    if tot == 0:
        r(f, x, CH_Y, COL_W, CH_H - 1, fill="none" if False else PAPER, radius=0,
          stroke=RULE, name="col empty")
        t(f, x, CH_Y + CH_H // 2 - 8, "0/0", 11, INK3, "Bold", family=MONO,
          width=COL_W, align="center")
    else:
        h_full = CH_H - 12
        full = done == tot
        r(f, x, CH_Y, COL_W, h_full, fill=RULE2, name="track")
        h = max(round(h_full * done / tot), 3)
        r(f, x, CH_Y + (h_full - h), COL_W, h, fill=INK if full else IND,
          name=f"col {kid}")
        t(f, x, CH_Y + (h_full - h) - 20, f"{done}/{tot}", 11,
          INK if full else IND, "Bold", family=MONO, width=COL_W, align="center")
    t(f, x, CH_Y + CH_H + 10, nama, 10.5, INK, "Medium", width=COL_W, align="center")
    t(f, x, CH_Y + CH_H + 26, kid, 9, INK3, "Bold", family=MONO, width=COL_W,
      align="center")

# legend sits under the chart baseline, right-aligned, one row
LGY = CH_Y + CH_H + 44
lx = R_
for lab, col in [("belum", IND), ("lengkap", INK)]:
    w = 12 + len(lab) * 5.4
    lx -= w
    t(f, lx, LGY, lab, 9.5, INK3, width=w, align="left")
    r(f, lx - 11, LGY + 4, 6, 6, fill=col, radius=1, name="leg")
    lx -= 22

rule(646, 1, RULE)

# ══ 03 · tindakan + blokir ════════════════════════════════════════════
sec("03", "YANG MENGHALANGI", 662)
t(f, L_ + 230, 662, "4 kriteria · 6 tabel sisa", 10, INK3)
BX, BW, BH = 1012, 396, 250
r(f, BX, 688, BW, BH, fill=IND, radius=0, name="indigo")
t(f, BX + 30, 712, "BLOKIR EKSPOR", 9, "#B9B5E8", "Bold", family=MONO)
t(f, BX + 30, 726, "2", 54, "#FFFFFF", "Bold", family=MONO, name="blk-n")
t(f, BX + 72, 748, "aturan lintas tabel gagal —", 11, "#C9C6EC", width=BW - 100)
t(f, BX + 72, 764, "ekspor terkunci", 11, "#C9C6EC", width=BW - 100)
r(f, BX + 30, 802, BW - 60, 1, fill="#4A44B0", name="blk-rule")
t(f, BX + 30, 816, "15 · 16  Tabel dosen tidak sinkron", 11, "#FFFFFF", "Medium",
  width=BW - 60)
t(f, BX + 30, 834, "24 dosen di Tabel 15, 23 nama di Tabel 16", 9.5, "#B9B5E8",
  width=BW - 60)
t(f, BX + 30, 858, "34  Persentase bukan 100%", 11, "#FFFFFF", "Medium", width=BW - 60)
t(f, BX + 30, 876, "TS-1 berjumlah 96,4%, selisih 3,6 poin", 9.5, "#B9B5E8",
  width=BW - 60)
t(f, BX + 30, 906, "Buka berkas blokir →", 10, "#FFFFFF", "Medium", width=BW - 60)

acts = [
    ("Penilaian", "Varian Tabel 5 belum dipilih", "Pilih varian", 3),
    ("Mahasiswa", "1 lulusan belum terlacak", "Isi tracer", 1),
    ("Dosen", "Tabel 15 vs 16 selisih 1 nama", "Rapikan nama", 1),
    ("Sarana", "Persentase Tabel 34 belum 100%", "Hitung ulang", 1),
]
NUM_X, NUM_W = 1012 - 176, 56      # right-aligned column, clear of the block
ay = 690
for i, (krit, note, act, sisa) in enumerate(acts):
    t(f, L_, ay, f"0{i + 1}", 9.5, INK3, "Bold", family=MONO)
    t(f, L_ + 32, ay - 3, krit, 13, INK, "Bold", width=180)
    t(f, L_ + 32, ay + 15, note, 10.5, INK2, width=330)
    t(f, L_ + 382, ay + 1, act + " →", 10.5, IND, "Medium", width=120)
    t(f, NUM_X, ay - 8, str(sisa), 20, INK, "Bold", family=MONO, width=NUM_W,
      align="right")
    t(f, NUM_X, ay + 14, "sisa", 9, INK3, width=NUM_W, align="right")
    ay += 62
    if i < len(acts) - 1:
        rule(ay - 14, 1, RULE2)

rule(950, 1, RULE)

# ══ footer ════════════════════════════════════════════════════════════
t(f, L_, 966, "Data terbaru", 10, INK3, "Medium")
latest = [("T-01", "Substansi Kurikulum", "Lengkap", OK),
          ("T-03", "IPK Lulusan", "Lengkap", OK),
          ("T-17", "Aktivitas Dosen", "Terkunci", "#175CD3"),
          ("T-05", "Lulusan Tepat Waktu", "Sebagian", WARN)]
lx = L_ + 116
for tag, nama, status, col in latest:
    r(f, lx, 966, 3, 26, fill=col, name="tick")
    t(f, lx + 12, 963, tag, 9, INK3, family=MONO, width=34)
    t(f, lx + 12, 977, nama, 10.5, INK2, width=150)
    t(f, lx + 12, 991, status, 9, col, "Medium", width=90)
    lx += 196
t(f, R_ - 100, 966, "Semua 38 →", 10.5, INK, "Medium", width=100, align="right")

if __name__ == "__main__":
    removed = clear_content()
    L.batch([{"command": "node.set", "params": {"ids": [FRAME], "fill": PAPER}}],
            label="Paper")
    res = L.batch(k.ops, label="Dashboard v5")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"removed {removed}, drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:150])
    L.call("export", format="png", scale=2, ids=[FRAME], path="/tmp/v5.png")
    print("rendered /tmp/v5.png")
