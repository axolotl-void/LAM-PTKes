"""05 · Ringkasan 8 Kriteria — v2, same theme, different structure.

Why the first pass read as template output: eight rows of identical weight in
instrument order. Fixes:
  * split into two blocks — "butuh tindakan" (sorted by gap desc) and
    "sudah lengkap" (compact rows), so the eye lands on what is broken
  * the action verb differs by state: Perbaiki / Lengkapi / Lihat
  * blocker rows carry a severity bar, cosmetic rows do not
  * page header states period + scope; KPI strip is tied to the same numbers
  * K1 (no tables) rendered as its own case, not as a normal row
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME = 836
CONTENT_X0 = 256

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# id, nama, tabel_total, tabel_lengkap, tabel_na, dokumen, temuan
KRIT = [
    ("K1", "Visi, Misi, Tujuan & Strategi", 0, 0, 0, 4,
     ["4 butir dokumen sudah diunggah"]),
    ("K2", "Kurikulum", 1, 1, 0, 9,
     ["Tabel 1 tersimpan · 239 baris"]),
    ("K3", "Penilaian", 7, 1, 4, 8,
     ["Tabel 5 · varian belum dipilih"]),
    ("K4", "Mahasiswa", 6, 4, 1, 11,
     ["Tabel 13 · 3 dari 44 lulusan belum terlacak"]),
    ("K5", "Dosen, Tendik, Penelitian & PkM", 14, 12, 0, 15,
     ["Tabel 15 vs 16 · selisih 1 nama (24 vs 23)", "Tabel 24 · 2 artikel > 3 tahun"]),
    ("K6", "Sarana, Prasarana & Keuangan", 6, 4, 1, 14,
     ["Tabel 34 · TS-1 96,4%, belum 100%"]),
    ("K7", "Penjaminan Mutu", 3, 2, 0, 9,
     ["Tabel 35 · audit mutu 1 prodi kosong"]),
    ("K8", "Tata Kelola & Administrasi", 1, 1, 0, 9,
     ["Tabel 38 tersimpan · 9 butir dokumen belum diunggah"]),
]

T_TABEL = sum(t for *_, t, d, n, docs, f in KRIT)
T_DONE = sum(d for *_, t, d, n, docs, f in KRIT)
T_NA = sum(n for *_, t, d, n, docs, f in KRIT)
T_DOCS = sum(docs for *_, t, d, n, docs, f in KRIT)
T_DOC_DONE = 41
DOC_DONE_BY_KRIT = {"K1": 4, "K2": 9, "K3": 5, "K4": 7, "K5": 8, "K6": 4, "K7": 3, "K8": 1}
assert T_TABEL == 38 and T_DONE == 25 and T_NA == 6 and T_DOCS == 79
assert all(d <= t - n for *_x, t, d, n, docs, f in KRIT), "done must fit inside wajib"
assert sum(DOC_DONE_BY_KRIT.values()) == T_DOC_DONE
WAJIB = T_TABEL - T_NA
BELUM = WAJIB - T_DONE

# blockers are the two cross-table rules that fail, not the missing tables
BLOCKERS = {"K5", "K6"}   # Tabel 15/16 and Tabel 33/34 rules fail


def clear_content():
    node = L.call("get_node", id=FRAME, depth=3)
    dead = []

    def walk(n, depth=0):
        for c in n.get("children", []):
            x, y = c.get("x") or 0, c.get("y") or 0
            if depth == 0 and x >= CONTENT_X0 and y >= 70:
                dead.append(c["id"])
            else:
                walk(c, depth + 1)

    walk(node)
    if dead:
        L.batch([{"command": "edit.delete", "params": {"ids": dead}}], label="Clear 05")
    return len(dead)


k = Kit()
r, t = k.rect, k.txt
f = FRAME


def chip(x, y, kind, label=None):
    fg, bg = {"ok": (OK, OK_BG), "warn": (WARN, WARN_BG), "blk": (BAD, BAD_BG)}[kind]
    lab = label or {"ok": "Lengkap", "warn": "Sebagian", "blk": "Blokir"}[kind]
    w = 16 + len(lab) * 5.9
    r(f, x, y, w, 20, fill=bg, radius=4, name=f"chip {lab}")
    t(f, x, y + 4, lab, 9.5, fg, "Medium", width=w, align="center")
    return w


def group_header(y, title, note, count):
    """Editorial section header — the thing the flat list was missing."""
    t(f, 280, y, title, 13, T1, "Bold")
    nw = 12 + len(count) * 6.4
    r(f, 280 + 8 + len(title) * 7.2, y - 1, nw, 18, fill=LINE2, radius=4, name="count")
    t(f, 280 + 8 + len(title) * 7.2, y + 2, count, 10, INK2, "Bold", family=MONO,
      width=nw, align="center")
    t(f, 280 + 16 + len(title) * 7.2 + nw, y + 2, note, 10, INK3)
    return y + 28


# ══ header ═══════════════════════════════════════════════════════════
t(f, 280, 82, "Ringkasan 8 Kriteria", 24, T1, "Bold", name="page-title")
t(f, 280, 110, "Kebidanan · D-III · TS 2025/2026 · data per 8 Nov 2026", 11, INK3)
DOC_BELUM = T_DOCS - T_DOC_DONE
t(f, 280, 130,
  f"{BELUM} tabel, {DOC_BELUM} butir dokumen, dan 2 aturan lintas tabel "
  f"memisahkan berkas ini dari ekspor.", 11, INK2, "Medium")

r(f, 1156, 82, 120, 32, fill=SURF, radius=6, stroke=LINE, name="filter")
t(f, 1156, 90, "Semua kriteria", 10.5, INK2, "Medium", width=120, align="center")
r(f, 1288, 82, 120, 32, fill=ORN, radius=6, name="export")
t(f, 1288, 90, "Ekspor", 11, "#FFFFFF", "Bold", width=120, align="center")

# ══ strip ═══════════════════════════════════════════════════════════
SY = 162
r(f, 280, SY, 1128, 68, fill=SURF, radius=10, stroke=LINE, name="strip")
stats = [(str(T_DONE), "lengkap", OK), (str(BELUM), "belum lengkap", WARN),
         (str(T_NA), "tak berlaku", INK3), (str(WAJIB), "tabel wajib", T1),
         (f"{T_DOC_DONE}/{T_DOCS}", "dokumen", T1), ("2", "blokir ekspor", BAD)]
sx = 300
for i, (val, lab, col) in enumerate(stats):
    t(f, sx, SY + 14, val, 20, col, "Bold", family=MONO)
    t(f, sx, SY + 40, lab, 9.5, INK3)
    if i < len(stats) - 1:
        r(f, sx + 150, SY + 12, 1, 40, fill=LINE2, name="vr")
    sx += 172

# ══ block 1: butuh tindakan, sorted by gap ══════════════════════════
def gap(row):
    _, _, tot, done, na, docs, _ = row
    return (tot - na) - done if tot else 0


def docs_left(row):
    return row[5] - DOC_DONE_BY_KRIT[row[0]]


notable = [row for row in KRIT if row[2] == 0]
action = [row for row in KRIT
          if row[2] and (gap(row) > 0 or docs_left(row) > 0)]
clean = [row for row in KRIT
         if row[2] and gap(row) == 0 and docs_left(row) == 0]
assert len(action) + len(clean) + len(notable) == len(KRIT), "a criterion went missing"
action.sort(key=lambda r_: (-gap(r_), r_[0]))

y = group_header(262, "Butuh tindakan", f"diurutkan dari sisa tabel · {BELUM} tabel belum",
                 f"{len(action)} kriteria")

C_BAR, C_DOC, C_ACT = 452, 360, 986
CARD_H = 92

for kid, nama, tot, done, na, docs, finds in action:
    wajib = tot - na
    left = wajib - done
    blk = kid in BLOCKERS
    r(f, 280, y, 1128, CARD_H, fill=SURF, radius=10, stroke=LINE, name=f"card {kid}")
    # severity rail: red for blocker, orange for gap
    r(f, 280, y + 12, 3, CARD_H - 24, fill=BAD if blk else ORN, radius=2,
      name="severity")

    t(f, 300, y + 14, kid, 10, INK3, "Bold", family=MONO)
    if left:
        t(f, 300, y + 34, str(left), 26, BAD if blk else ORN, "Bold", family=MONO)
        t(f, 300, y + 66, "tabel", 9, INK3)
    else:
        dl = docs_left((kid, nama, tot, done, na, docs, finds))
        t(f, 300, y + 34, str(dl), 26, WARN, "Bold", family=MONO)
        t(f, 300, y + 66, "dokumen", 9, INK3)

    t(f, 340, y + 12, nama, 13, T1, "Bold", width=390)
    fy = y + 32
    for msg in finds:
        t(f, 340, fy, msg, 10, INK2, width=400)
        fy += 15

    # tables
    t(f, 280 + C_BAR, y + 12, "Tabel", 9, INK3, "Medium")
    t(f, 280 + C_BAR, y + 24, f"{done}/{wajib}", 12, T1, "Bold", family=MONO)
    BW = 84
    r(f, 280 + C_BAR, y + 44, BW, 5, fill=LINE2, radius=3, name="track")
    r(f, 280 + C_BAR, y + 44, max(round(BW * done / wajib), 2), 5,
      fill=OK if done == wajib else ORN, radius=3, name="fill")
    if na:
        t(f, 280 + C_BAR, y + 54, f"{na} dari {tot} dikecualikan", 8.5, INK3)
    else:
        t(f, 280 + C_BAR, y + 54, "semua wajib", 8.5, INK3)

    # documents
    t(f, 280 + C_DOC, y + 12, "Dokumen", 9, INK3, "Medium")
    t(f, 280 + C_DOC, y + 24, f"{DOC_DONE_BY_KRIT[kid]}/{docs}", 12, INK2, "Medium",
      family=MONO)

    # status + action, right
    chip(280 + 700, y + 12, "blk" if blk else "warn")
    t(f, 280 + C_ACT, y + 14, "Perbaiki →" if blk else "Lengkapi →", 10.5, IND,
      "Medium", width=100, align="right")
    tail = f"{left} tabel" if left else f"{docs_left((kid, nama, tot, done, na, docs, finds))} dok"
    t(f, 280 + C_ACT, y + 32, tail, 9, INK3, width=100, align="right")
    y += CARD_H + 8

# ══ block 2b: dinilai dari dokumen saja — its own block, after the clean rows ══
# ══ block 3: sudah lengkap, compact ═════════════════════════════════
y = group_header(y + 14, "Sudah lengkap", "tidak perlu ditinjau",
                 f"{len(clean)} kriteria")
row_y = y
for kid, nama, tot, done, na, docs, finds in clean:
    r(f, 280, row_y, 1128, 40, fill=SURF, radius=8, stroke=LINE, name=f"row {kid}")
    r(f, 288, row_y + 16, 3, 8, fill=OK, radius=2, name="ok-tick")
    t(f, 302, row_y + 12, kid, 10, INK3, "Bold", family=MONO)
    t(f, 332, row_y + 11, nama, 11.5, INK2, width=380)
    t(f, 280 + C_DOC, row_y + 12, f"{DOC_DONE_BY_KRIT[kid]}/{docs} dok", 10, INK3,
      family=MONO)
    t(f, 280 + C_BAR, row_y + 12, f"{done}/{tot - na} tabel", 10, INK3, family=MONO)
    chip(280 + 700, row_y + 10, "ok")
    t(f, 280 + C_ACT, row_y + 12, "Lihat →", 10, IND, "Medium", width=100,
      align="right")
    row_y += 46

# ══ block 2b: dokumen saja ══════════════════════════════════════════
y = group_header(row_y + 14, "Dinilai dari dokumen saja", "tanpa tabel data dukung",
                 f"{len(notable)} kriteria")
for kid, nama, tot, done, na, docs, finds in notable:
    r(f, 280, y, 1128, 76, fill="#FCFCFD", radius=10, stroke=LINE, name=f"card {kid}")
    t(f, 300, y + 14, kid, 10, INK3, "Bold", family=MONO)
    t(f, 340, y + 12, nama, 13, T1, "Bold", width=400)
    t(f, 340, y + 32,
      "Dinilai dari dokumen saja — tanpa tabel data dukung.",
      10, INK2, width=330)
    t(f, 280 + C_DOC, y + 14, "Dokumen", 9, INK3, "Medium")
    t(f, 280 + C_DOC, y + 28, f"{DOC_DONE_BY_KRIT[kid]}/{docs}", 12, OK, "Bold",
      family=MONO)
    chip(280 + 700, y + 14, "ok")
    t(f, 280 + C_ACT, y + 16, "Lihat →", 10.5, IND, "Medium", width=100, align="right")
    y += 76 + 8


# ══ footnote ════════════════════════════════════════════════════════
FOOT_Y = y + 76 + 22      # clears the K1 card and its section header
t(f, 280, FOOT_Y,
  "Blokir = aturan lintas tabel gagal: Tabel 15 vs 16 (jumlah dosen) dan Tabel 33 vs 34 (dana). "
  "Tabel \"tak berlaku\" tidak dihitung dalam target.", 9.5, INK3, width=1128)

if __name__ == "__main__":
    removed = clear_content()
    res = L.batch(k.ops, label="Ringkasan 8 v2")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"removed {removed}, drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:150])
    L.batch([{"command": "node.set",
              "params": {"ids": [FRAME], "height": int(FOOT_Y + 40)}}], label="Resize")
    L.call("export", format="png", scale=2, ids=[FRAME], path="/tmp/r8b.png")
    print("rendered /tmp/r8b.png")
