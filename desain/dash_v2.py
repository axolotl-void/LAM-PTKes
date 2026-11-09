"""Dashboard v2 — 'readiness dossier', not a KPI card grid.

Design decisions (each answers a criticism of v1):
  * one status band instead of 4 identical cards; 32/38 appears once
  * criteria bars on a real 0-100% scale with a 100% target tick, sorted by gap
  * action panel rows are links with consequences, no coloured alert boxes
  * checklist gets column headers, one status chip shape, muted 'n/a' as text
  * one accent (orange) + neutral ink; status colour only for status
"""
from kit import Kit, IND, IND_D, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME = 2           # 01 · Dashboard
CONTENT_X0 = 256

INK2, INK3 = "#475467", "#98A2B3"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD = "#067647", "#B54708", "#B42318"
OK_BG, WARN_BG, BAD_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2"


def clear_content():
    node = L.call("get_node", id=FRAME, depth=3)
    dead = []

    def walk(n):
        for c in n.get("children", []):
            x, y = c.get("x") or 0, c.get("y") or 0
            if c.get("parentIsFrame", True) and (x >= CONTENT_X0 and y >= 80):
                dead.append(c["id"])
            else:
                walk(c)

    walk(node)
    if dead:
        L.batch([{"command": "edit.delete", "params": {"ids": dead}}], label="Clear dashboard")
    return len(dead)


k = Kit()
r, t = k.rect, k.txt


def hair(parent, x, y, w, color=LINE):
    r(parent, x, y, w, 1, fill=color, name="hair")


def vr(parent, x, y, h, color=LINE):
    r(parent, x, y, 1, h, fill=color, name="vr")


def chip(parent, x, y, kind, label=None):
    """One chip shape, three fills. n/a is not a chip — see dot_line()."""
    fg, bg = {"ok": (OK, OK_BG), "warn": (WARN, WARN_BG),
              "bad": (BAD, BAD_BG), "lock": ("#175CD3", "#EFF8FF")}[kind]
    lab = label or {"ok": "Lengkap", "warn": "Sebagian", "bad": "Gagal",
                    "lock": "Terkunci"}[kind]
    w = 16 + len(lab) * 5.9
    r(parent, x, y, w, 20, fill=bg, radius=4, name=f"chip {lab}")
    t(parent, x, y + 4, lab, 9.5, fg, "Medium", width=w, align="center")
    return w


# ── header ───────────────────────────────────────────────────────────
# The frame already exists — parent calls take its id directly.
f = FRAME

t(f, 280, 84, "Dashboard", 24, T1, "Bold", name="page-title")
t(f, 280, 112, "Kebidanan · D-III · TS 2025/2026 · data per 8 Nov 2026", 11, INK3,
  name="page-meta")

# single export control, one place
r(f, 1244, 84, 164, 32, fill=SURF, radius=6, stroke=LINE, name="export-control")
t(f, 1258, 92, "Ekspor", 11, T1, "Medium")
t(f, 1376, 92, "PDF", 11, INK2)
t(f, 1262 + 96, 92, "", 1, INK2)
vr(f, 1356, 90, 20, LINE)
t(f, 1364, 91, "▾", 9, INK2, width=14, align="center")

# ── status band ──────────────────────────────────────────────────────
BY, BH = 152, 124
r(f, 280, BY, 1128, BH, fill=SURF, radius=10, stroke=LINE, name="status-band")


def section_label(parent, x, y, s):
    t(parent, x, y, s, 10, INK3, "Medium")


# cell 1 — peringkat, dengan skala
section_label(f, 304, BY + 18, "Peringkat akreditasi")
t(f, 304, BY + 36, "B", 40, T1, "Bold", name="rank-letter")
t(f, 348, BY + 46, "Baik Sekali", 12, INK2)
t(f, 348, BY + 62, "LAM-PTKes · berlaku s.d. 12 Agu 2027", 10, INK3)
# scale C B A Unggul
scale = ["C", "B", "A", "Unggul"]
sx = 304
for i, s in enumerate(scale):
    cur = (s == "B")
    w = 66 if s == "Unggul" else 48
    r(f, sx, BY + 88, w, 20, fill=ORN if cur else "none" if False else SURF,
      radius=4, stroke=None if cur else LINE, name=f"scale {s}")
    t(f, sx, BY + 92, s, 9.5, "#FFFFFF" if cur else INK3, "Bold" if cur else "Medium",
      width=w, align="center")
    sx += w + 6
t(f, sx + 4, BY + 92, "277 hari lagi", 10, INK2, width=100)

vr(f, 700, BY + 20, BH - 40)

# cell 2 — data dukung, satu pita tersegmentasi
section_label(f, 724, BY + 18, "Data dukung · 38 tabel")
SW, SY, SH = 320, BY + 36, 10
seg = [(32, "#101828", "lengkap"), (4, WARN, "sebagian"), (2, "#D0D5DD", "kosong")]
x = 724
for n, col, _ in seg:
    w = round(SW * n / 38)
    r(f, x, SY, max(w - 2, 2), SH, fill=col, radius=2, name=f"strip {n}")
    x += w
# legend
ly = SY + 20
lx = 724
for n, col, lab in seg:
    r(f, lx, ly + 3, 7, 7, fill=col, radius=2, name=f"leg {lab}")
    t(f, lx + 12, ly, f"{n} {lab}", 10, INK2)
    lx += 34 + len(lab) * 5.6
t(f, 724, BY + 82, "6 tabel belum selesai menghalangi ekspor", 10.5, INK2)

vr(f, 1068, BY + 20, BH - 40)

# cell 3 — dokumen
section_label(f, 1092, BY + 18, "Dokumen bukti")
t(f, 1092, BY + 34, "41", 26, T1, "Bold", name="doc-num")
t(f, 1116, BY + 44, "/ 79 butir", 11, INK3)
r(f, 1092, SY, SW - 60, 8, fill=LINE2, radius=3, name="doc-track")
r(f, 1092, SY, round((SW - 60) * 41 / 79), 8, fill=T1, radius=3, name="doc-fill")
t(f, 1092, BY + 82, "38 butir belum diunggah · 7 kedaluwarsa", 10.5, INK2)

# ── left: celah terbesar, bar jujur ──────────────────────────────────
PX, PY, PW, PH = 280, 292, 700, 392
r(f, PX, PY, PW, PH, fill=SURF, radius=10, stroke=LINE, name="panel-gap")
t(f, PX + 24, PY + 20, "Celah terbesar", 13, T1, "Bold")
t(f, PX + 130, PY + 22, "diurutkan dari sisa tabel", 10, INK3)
t(f, PX + PW - 24 - 90, PY + 22, "skala 0–100%", 10, INK3, width=90, align="right")
hair(f, PX + 24, PY + 44, PW - 48, LINE2)

BAR_X, BAR_W = PX + 372, 240
rows = [
    ("5", "Dosen, Tendik, Penelitian & PkM", 11, 14, "Tabel 15 vs 16 selisih 1 nama"),
    ("3", "Penilaian", 4, 7, "Varian Tabel 5 belum dipilih"),
    ("7", "Penjaminan Mutu", 2, 3, "Audit mutu prodi lain belum masuk"),
    ("4", "Mahasiswa", 5, 6, "3 lulusan belum terlacak"),
    ("6", "Sarana, Prasarana & Keuangan", 5, 6, "2 wahana tanpa dokumen kerja sama"),
]
ry = PY + 60
for no, nama, done, tot, note in rows:
    r(f, PX + 24, ry + 2, 18, 18, fill=LINE2, radius=4, name="crit-no")
    t(f, PX + 24, ry + 5, no, 9.5, INK2, "Bold", width=18, align="center")
    t(f, PX + 52, ry, nama, 11.5, T1, width=300)
    t(f, PX + 52, ry + 17, note, 9.5, INK3, width=310)
    # bar: panjang jujur, target tick di 100%
    r(f, BAR_X, ry + 6, BAR_W, 8, fill=LINE2, radius=3, name="track")
    r(f, BAR_X, ry + 6, round(BAR_W * done / tot), 8, fill=T1 if done == tot else ORN,
      radius=3, name="fill")
    vr(f, BAR_X + BAR_W, ry + 2, 16, INK3)
    t(f, BAR_X + BAR_W + 12, ry + 3, f"{done}/{tot}", 10.5, INK2, "Medium", family=MONO)
    ry += 56

hair(f, PX + 24, PY + 340, PW - 48, LINE2)
t(f, PX + 24, PY + 356, "Kriteria 1, 2, dan 8 sudah lengkap (3 tabel)", 10.5, INK2)
t(f, PX + PW - 24 - 110, PY + 356, "Lihat semua →", 10.5, T1, "Medium", width=110,
  align="right")

# ── right: penghalang ekspor, baris yang bisa diklik ─────────────────
QX, QW = 1000, 408
r(f, QX, PY, QW, PH, fill=SURF, radius=10, stroke=LINE, name="panel-block")
t(f, QX + 24, PY + 20, "Menghalangi ekspor", 13, T1, "Bold")
t(f, QX + 24, PY + 40, "2 blokir · 1 peringatan", 10, INK3)
hair(f, QX + 24, PY + 62, QW - 48, LINE2)

blocks = [
    ("bad", "Tabel 15 dan 16 tidak sinkron",
     "Jumlah dosen tetap 24, daftar nama 23", "Buka Tabel 15 dan 16"),
    ("bad", "Persentase Tabel 34 bukan 100%",
     "TS-1 total 96,4% — selisih 3,6 poin", "Buka Tabel 34"),
    ("warn", "Varian Tabel 5 belum dipilih",
     "Jenjang D-III belum dikonfirmasi dosen pembimbing", "Pilih varian"),
]
by = PY + 76
for kind, title, sub, action in blocks:
    fg = BAD if kind == "bad" else WARN
    r(f, QX + 24, by + 6, 6, 6, fill=fg, radius=3, name="dot")
    t(f, QX + 40, by, title, 11.5, T1, "Medium", width=340)
    t(f, QX + 40, by + 18, sub, 10, INK3, width=340)
    t(f, QX + 40, by + 36, action + " →", 10.5, T1, "Medium", width=340)
    by += 84
    if kind == "bad":
        hair(f, QX + 24, by - 14, QW - 48, LINE2)

hair(f, QX + 24, PY + 336, QW - 48, LINE2)
t(f, QX + 24, PY + 352, "Setelah 2 blokir selesai", 10, INK3)
t(f, QX + 24, PY + 368, "Ekspor PDF & XLSX aktif", 10.5, INK2, "Medium")

# ── bottom: tabel data dukung dengan header kolom ────────────────────
TX, TY, TW, TH = 280, 700, 1128, 284
r(f, TX, TY, TW, TH, fill=SURF, radius=10, stroke=LINE, name="panel-table")
t(f, TX + 24, TY + 18, "Data dukung", 13, T1, "Bold")
t(f, TX + 108, TY + 20, "5 dari 38 tabel terbaru", 10, INK3)
t(f, TX + TW - 24 - 120, TY + 20, "Semua 38 tabel →", 10.5, T1, "Medium", width=120,
  align="right")

COLS = [("Tabel", 24, 56, "left"), ("Judul", 88, 560, "left"),
        ("Status", 660, 110, "left"), ("Isi", 790, 90, "left"),
        ("Aksi", 1000, 104, "right")]
hy = TY + 46
hair(f, TX, hy, TW, LINE)
for lab, cx, cw, al in COLS:
    t(f, TX + cx, hy + 8, lab, 9.5, INK3, "Medium", width=cw,
      align="left" if al == "left" else "right")
hair(f, TX, hy + 26, TW, LINE)

data = [
    ("01", "Substansi Kurikulum", "Lengkap", "ok", "239 baris", "Buka"),
    ("02", "Persentase Keberhasilan Studi", "Sebagian", "warn", "3 dari 7 tahun", "Isi"),
    ("03", "IPK Lulusan Tahap Akademik", "Lengkap", "ok", "otomatis", "Buka"),
    ("05", "Lulusan Tepat Waktu", "Sebagian", "warn", "varian belum dipilih", "Isi"),
    ("17", "Aktivitas Dosen Tetap", "Terkunci", "lock", "dikunci 6 Nov", "Buka"),
]
ry = hy + 26
for i, (no, judul, status, kind, meta, action) in enumerate(data):
    if i % 2 == 1:
        r(f, TX + 1, ry, TW - 2, 42, fill="#FCFCFD", name="zebra")
    t(f, TX + 24, ry + 13, f"T-{no}", 10.5, INK2, family=MONO)
    t(f, TX + 88, ry + 12, judul, 11.5, T1)
    cw = chip(f, TX + 660, ry + 11, kind, status)
    t(f, TX + 660 + cw + 8, ry + 13, meta, 9.5, INK3, width=120)
    t(f, TX + 1000, ry + 12, action + " →", 10.5, T1, "Medium", width=104, align="right")
    ry += 42
    if i < len(data) - 1:
        hair(f, TX + 24, ry, TW - 48, LINE2)

if __name__ == "__main__":
    removed = clear_content()
    res = L.batch(k.ops, label="Dashboard v2")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"removed {removed}, drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:200])
    L.call("export", format="png", scale=2, ids=[FRAME], path="/tmp/v2.png")
    print("rendered")
