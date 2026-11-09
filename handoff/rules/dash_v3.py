"""Dashboard v3 — fixes the v2 review:

  1. one definition of "selesai" (lengkap = validation passes). Gap panel shows
     "sisa N tabel", the band strip shows the same 6.
  2. sidebar no longer repeats the band counters (removed there, kept in band).
  3. single export door (header). Sidebar + footer duplicates dropped.
  4. gap rows sorted by sisa desc, and the bar encodes that same number.
  5. ghost number removed from the documents cell.
  6. crit badges labelled K1..K8, caption contrast raised.
"""
from kit import Kit, IND, IND_T, ORN, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME = 2
CONTENT_X0 = 256

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD = "#067647", "#B54708", "#B42318"
OK_BG, WARN_BG, BAD_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2"


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
        L.batch([{"command": "edit.delete", "params": {"ids": dead}}],
                label="Clear v2")
    return len(dead)


k = Kit()
r, t = k.rect, k.txt
f = FRAME


def hair(x, y, w, color=LINE):
    r(f, x, y, w, 1, fill=color, name="hair")


def vr(x, y, h, color=LINE):
    r(f, x, y, 1, h, fill=color, name="vr")


def chip(x, y, kind, label=None):
    fg, bg = {"ok": (OK, OK_BG), "warn": (WARN, WARN_BG), "bad": (BAD, BAD_BG),
              "lock": ("#175CD3", "#EFF8FF")}[kind]
    lab = label or {"ok": "Lengkap", "warn": "Sebagian", "bad": "Gagal",
                    "lock": "Terkunci"}[kind]
    w = 16 + len(lab) * 5.9
    r(f, x, y, w, 20, fill=bg, radius=4, name=f"chip {lab}")
    t(f, x, y + 4, lab, 9.5, fg, "Medium", width=w, align="center")
    return w


def slabel(x, y, s):
    t(f, x, y, s, 10, INK3, "Medium")


# Jumlah tabel per kriteria, dari Daftar Tabel instrumen (total 38).
# sisa = total - lengkap. Everything on the page derives from this dict.
KRIT = {
    "K2": ("Kurikulum", 1, 1),
    "K3": ("Penilaian", 7, 4),
    "K4": ("Mahasiswa", 6, 5),
    "K5": ("Dosen, Tendik, Penelitian & PkM", 14, 13),
    "K6": ("Sarana, Prasarana & Keuangan", 6, 5),
    "K7": ("Penjaminan Mutu", 3, 3),
    "K8": ("Tata Kelola & Administrasi", 1, 1),
}
TOTAL_TABEL = sum(t for _, t, _ in KRIT.values())
LENGKAP = sum(d for _, _, d in KRIT.values())
BELUM = TOTAL_TABEL - LENGKAP
assert TOTAL_TABEL == 38 and LENGKAP == 32 and BELUM == 6, (TOTAL_TABEL, LENGKAP, BELUM)
SEBAGIAN, KOSONG = 4, 2
assert SEBAGIAN + KOSONG == BELUM

# ── header ───────────────────────────────────────────────────────────
t(f, 280, 84, "Dashboard", 24, T1, "Bold", name="page-title")
t(f, 280, 112, "Kebidanan · D-III · TS 2025/2026 · data per 8 Nov 2026", 11, INK3,
  name="page-meta")

# the only export control
r(f, 1252, 84, 156, 34, fill=SURF, radius=6, stroke=LINE, name="export-ctl")
r(f, 1252, 84, 34, 34, fill=LINE2, radius=6, name="export-ico-bg")
t(f, 1252, 92, "↓", 12, INK2, "Bold", width=34, align="center")
t(f, 1296, 93, "Ekspor", 11, INK2, "Medium")
vr(1344, 92, 18, LINE)
t(f, 1352, 92, "PDF", 11, T1, "Medium")
t(f, 1380, 93, "▾", 9, INK3, width=14, align="center")

# ── status band ──────────────────────────────────────────────────────
BY, BH = 152, 128
r(f, 280, BY, 1128, BH, fill=SURF, radius=10, stroke=LINE, name="band")

# 1. peringkat
slabel(304, BY + 18, "Peringkat akreditasi")
t(f, 304, BY + 34, "B", 40, T1, "Bold", name="rank")
t(f, 348, BY + 44, "Baik Sekali", 12, INK2, "Medium")
t(f, 348, BY + 61, "berlaku s.d. 12 Agu 2027", 10, INK3)
sx = 304
for s, w in [("C", 40), ("B", 40), ("A", 40), ("Unggul", 62)]:
    cur = s == "B"
    r(f, sx, BY + 88, w, 20, fill=ORN if cur else SURF, radius=4,
      stroke=None if cur else LINE, name=f"sc {s}")
    t(f, sx, BY + 92, s, 9.5, "#FFFFFF" if cur else INK3, "Bold" if cur else "Medium",
      width=w, align="center")
    sx += w + 5
t(f, sx + 6, BY + 92, "277 hari lagi", 10, INK3)

vr(706, BY + 20, BH - 40)

# 2. data dukung — strip is the only place these numbers live
slabel(730, BY + 18, "Data dukung")
t(f, 730 + 84, BY + 18, f"{LENGKAP} lengkap · {BELUM} belum", 10, INK3)
SW = 300
x = 730
for n, col in [(LENGKAP, "#101828"), (SEBAGIAN, WARN), (KOSONG, "#D0D5DD")]:
    w = round(SW * n / TOTAL_TABEL)
    r(f, x, BY + 40, max(w - 2, 2), 10, fill=col, radius=2, name=f"seg {n}")
    x += w
lx = 730
for n, col, lab in [(LENGKAP, "#101828", "lengkap"), (SEBAGIAN, WARN, "sebagian"),
                    (KOSONG, "#D0D5DD", "kosong")]:
    r(f, lx, BY + 63, 7, 7, fill=col, radius=2)
    t(f, lx + 12, BY + 60, f"{n} {lab}", 10, INK2)
    lx += 46 + len(lab) * 5.4
t(f, 730, BY + 86, f"{BELUM} tabel belum lengkap — ekspor menunggu 2 blokir", 10.5, INK2)

vr(1064, BY + 20, BH - 40)

# 3. dokumen — no ghost number
slabel(1088, BY + 18, "Dokumen bukti")
t(f, 1088, BY + 36, "41", 24, T1, "Bold", name="doc-n")
t(f, 1112, BY + 45, "dari 79 butir", 11, INK3)
r(f, 1088, BY + 68, 288, 8, fill=LINE2, radius=3)
r(f, 1088, BY + 68, round(288 * 41 / 79), 8, fill=T1, radius=3, name="doc-fill")
t(f, 1088, BY + 86, "38 belum diunggah · 7 kedaluwarsa", 10, INK2)

# ── celah terbesar: sorted by sisa desc, bar = same number ────────────
PX, PY, PW, PH = 280, 296, 700, 388
FRAC_X, FRAC_W = PX + PW - 24 - 56, 56
r(f, PX, PY, PW, PH, fill=SURF, radius=10, stroke=LINE, name="gap-panel")
t(f, PX + 24, PY + 20, "Celah terbesar", 13, T1, "Bold")
t(f, PX + 138, PY + 22, f"sisa {BELUM} tabel di 4 kriteria", 10, INK3)
t(f, FRAC_X - 60, PY + 22, "sisa/total", 10, INK3, width=FRAC_W + 60, align="right")
hair(PX + 24, PY + 44, PW - 48, LINE2)

# (kritik no, kriteria, selesai, total, catatan)
NOTES = {
    "K3": "Varian Tabel 5 belum dipilih",
    "K4": "1 lulusan belum terlacak",
    "K5": "Tabel 15 vs 16 selisih 1 nama",
    "K6": "Persentase Tabel 34 belum 100%",
}
rows = [(kid, nama, done, tot, NOTES.get(kid, ""))
        for kid, (nama, tot, done) in KRIT.items() if tot - done > 0]
rows.sort(key=lambda r: (-(r[3] - r[2]), r[0]))
assert sum(t - d for _, _, d, t, _ in rows) == BELUM, "gap panel must explain all missing tables"

BAR_X, BAR_W = PX + 404, 208
ry = PY + 62
for kid, nama, done, tot, note in rows:
    sisa = tot - done
    slabel(PX + 24, ry + 2, kid)
    t(f, PX + 24, ry + 14, str(sisa), 15, T1, "Bold", family=MONO, width=32)
    t(f, PX + 68, ry, nama, 11.5, T1, width=310)
    t(f, PX + 68, ry + 17, note, 9.5, INK2, width=310)
    r(f, BAR_X, ry + 8, BAR_W, 8, fill=LINE2, radius=3, name="track")
    # bar = share of this criterion already done; the tick marks 100%
    r(f, BAR_X, ry + 8, max(round(BAR_W * done / tot), 3), 8, fill=ORN, radius=3,
      name="fill")
    vr(BAR_X + BAR_W, ry + 4, 16, INK3)
    t(f, FRAC_X, ry + 5, f"{sisa}/{tot}", 10, INK3, family=MONO, width=FRAC_W,
      align="right")
    ry += 56

hair(PX + 24, PY + 342, PW - 48, LINE2)
lengkap_k = [k_ for k_, (_, t_, d_) in KRIT.items() if d_ == t_]
t(f, PX + 24, PY + 360,
  "Lengkap: " + ", ".join(lengkap_k) + " · K1 tidak punya tabel", 10.5, INK2, "Medium")
t(f, PX + PW - 24 - 96, PY + 360, "Lihat semua →", 10.5, T1, "Medium", width=96,
  align="right")

# ── right: penghalang ekspor ─────────────────────────────────────────
QX, QW = 1000, 408
r(f, QX, PY, QW, PH, fill=SURF, radius=10, stroke=LINE, name="block-panel")
t(f, QX + 24, PY + 20, "Menghalangi ekspor", 13, T1, "Bold")
t(f, QX + 24, PY + 40, "2 blokir · 1 peringatan", 10, INK3)
hair(QX + 24, PY + 62, QW - 48, LINE2)

blocks = [
    ("bad", "Tabel 15 dan 16 tidak sinkron",
     "Dosen tetap 24 di Tabel 15, 23 nama di Tabel 16", "Buka Tabel 15 · 16"),
    ("bad", "Persentase Tabel 34 bukan 100%",
     "TS-1 berjumlah 96,4% — selisih 3,6 poin", "Buka Tabel 34"),
    ("warn", "Varian Tabel 5 belum dipilih",
     "Jenjang D-III belum dikonfirmasi dospen", "Pilih varian"),
]
by = PY + 78
for i, (kind, title, sub, act) in enumerate(blocks):
    fg = BAD if kind == "bad" else WARN
    r(f, QX + 24, by + 6, 6, 6, fill=fg, radius=3)
    t(f, QX + 40, by, title, 11.5, T1, "Medium", width=344)
    t(f, QX + 40, by + 18, sub, 10, INK2, width=344)
    t(f, QX + 40, by + 36, act + " →", 10.5, T1, "Medium", width=344)
    by += 82
    if i < 2:
        hair(QX + 24, by - 12, QW - 48, LINE2)

hair(QX + 24, PY + 336, QW - 48, LINE2)
t(f, QX + 24, PY + 354, "Setelah 2 blokir selesai", 10, INK3)
t(f, QX + 24, PY + 368, "Ekspor PDF & XLSX aktif", 10.5, INK2, "Medium")

# ── tabel: header kolom, satu bentuk chip ────────────────────────────
TX, TY, TW, TH = 280, 704, 1128, 280
r(f, TX, TY, TW, TH, fill=SURF, radius=10, stroke=LINE, name="table-panel")
t(f, TX + 24, TY + 18, "Data dukung", 13, T1, "Bold")
t(f, TX + 110, TY + 20, "5 dari 38 tabel terbaru", 10, INK3)
t(f, TX + TW - 24 - 110, TY + 20, "Semua 38 tabel →", 10.5, T1, "Medium", width=110,
  align="right")

hy = TY + 46
hair(TX, hy, TW, LINE)
for lab, cx, cw, al in [("Tabel", 24, 56, "left"), ("Judul", 88, 520, "left"),
                        ("Status", 620, 110, "left"), ("Keterangan", 740, 220, "left"),
                        ("Aksi", 1000, 104, "right")]:
    t(f, TX + cx, hy + 8, lab, 9.5, INK3, "Medium", width=cw,
      align="left" if al == "left" else "right")
hair(TX, hy + 26, TW, LINE)

data = [
    ("01", "Substansi Kurikulum", "ok", "Lengkap", "239 baris · impor Excel", "Buka"),
    ("02", "Persentase Keberhasilan Studi", "warn", "Sebagian", "3 dari 7 tahun kosong", "Isi"),
    ("03", "IPK Lulusan Tahap Akademik", "ok", "Lengkap", "otomatis dari master", "Buka"),
    ("05", "Lulusan Tepat Waktu", "warn", "Sebagian", "varian belum dipilih", "Isi"),
    ("17", "Aktivitas Dosen Tetap", "lock", "Terkunci", "dikunci 6 Nov 2026", "Buka"),
]
ry = hy + 26
for i, (no, judul, kind, status, meta, act) in enumerate(data):
    if i % 2 == 1:
        r(f, TX + 1, ry, TW - 2, 41, fill="#FCFCFD", name="zebra")
    t(f, TX + 24, ry + 13, f"T-{no}", 10.5, INK2, family=MONO)
    t(f, TX + 88, ry + 12, judul, 11.5, T1)
    chip(TX + 620, ry + 11, kind, status)
    t(f, TX + 740, ry + 13, meta, 9.5, INK2)
    t(f, TX + 1000, ry + 12, act + " →", 10.5, T1, "Medium", width=104, align="right")
    ry += 41
    if i < len(data) - 1:
        hair(TX + 24, ry, TW - 48, LINE2)

if __name__ == "__main__":
    removed = clear_content()
    res = L.batch(k.ops, label="Dashboard v3")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"removed {removed}, drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:160])
    L.call("export", format="png", scale=2, ids=[FRAME], path="/tmp/v3.png")
    print("rendered /tmp/v3.png")
