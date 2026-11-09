"""05 · Ringkasan 8 Kriteria — rebuilt on one data source.

Fixes every finding from the review of the first attempt:
  * 38 tables total (was 39: K1 wrongly owned Tabel 1)
  * table-level and criterion-level status never mixed
  * "tak berlaku" is a real, visible state with its own count
  * docs per criterion sum to 79
  * finding text: right colour (issue = amber, clean = green), full sentence
  * badge in its own column with a header, aligned across cards
  * progress bar width = done/total of *tables*, evenly comparable
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME = 836          # 05 · Ringkasan 8 Kriteria
CONTENT_X0 = 256

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# ── ONE source of truth. Totals must close to 38 tables / 79 docs. ─────
# id, nama, tabel_total, tabel_lengkap, tabel_na, dokumen, temuan(list)
KRIT = [
    ("K1", "Visi, Misi, Tujuan & Strategi", 0, 0, 0, 4,
     ["4 butir dokumen sudah diunggah", "Tidak ada tabel data dukung pada kriteria ini"]),
    ("K2", "Kurikulum", 1, 1, 0, 9,
     ["Tabel 1 tersimpan · 239 baris kurikulum", "Tidak ada temuan"]),
    ("K3", "Penilaian", 7, 2, 4, 8,
     ["Tabel 5 · varian belum dipilih", "Tabel 4, 6, 7, 8 · khusus profesi, tak berlaku D-III"]),
    ("K4", "Mahasiswa", 6, 4, 1, 11,
     ["Tabel 13 · 3 dari 44 lulusan belum terlacak", "Tabel 11 · khusus profesi, tak berlaku D-III"]),
    ("K5", "Dosen, Tendik, Penelitian & PkM", 14, 12, 0, 15,
     ["Tabel 15 vs 16 · selisih 1 nama dosen (24 vs 23)", "Tabel 24 · 2 artikel di luar rentang 3 tahun"]),
    ("K6", "Sarana, Prasarana & Keuangan", 6, 4, 1, 14,
     ["Tabel 34 · persen TS-1 berjumlah 96,4%, belum 100%", "Tabel 30 · khusus profesi, tak berlaku D-III"]),
    ("K7", "Penjaminan Mutu", 3, 2, 0, 9,
     ["Tabel 35 · audit mutu 1 prodi belum diisi"]),
    ("K8", "Tata Kelola & Administrasi", 1, 1, 0, 9,
     ["Tidak ada temuan"]),
]

T_TABEL = sum(t for *_x, t, d, n, docs, f in KRIT)
T_DONE = sum(d for *_x, t, d, n, docs, f in KRIT)
T_NA = sum(n for *_x, t, d, n, docs, f in KRIT)
T_DOCS = sum(docs for *_x, t, d, n, docs, f in KRIT)
T_DOC_DONE = 41
assert T_TABEL == 38, T_TABEL
assert T_DONE == 26, T_DONE
assert T_TABEL - T_NA == 32, (T_TABEL, T_NA)
assert T_DOCS == 79, T_DOCS
WAJIB = T_TABEL - T_NA

# Butir dokumen yang benar-benar sudah diunggah, per kriteria. Harus berjumlah 41.
DOC_DONE_BY_KRIT = {"K1": 4, "K2": 9, "K3": 5, "K4": 7, "K5": 8, "K6": 4, "K7": 3, "K8": 1}
assert sum(DOC_DONE_BY_KRIT.values()) == T_DOC_DONE, sum(DOC_DONE_BY_KRIT.values())
BELUM = WAJIB - T_DONE
assert BELUM == 6, BELUM


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
    fg, bg = {"ok": (OK, OK_BG), "warn": (WARN, WARN_BG), "bad": (BAD, BAD_BG),
              "lock": (LOCK, LOCK_BG), "na": (INK3, "#F9FAFB")}[kind]
    lab = label or {"ok": "Lengkap", "warn": "Sebagian", "bad": "Kosong",
                    "lock": "Terkunci", "na": "Tak berlaku"}[kind]
    w = 16 + len(lab) * 5.9
    r(f, x, y, w, 20, fill=bg, radius=4, stroke=fg if kind == "na" else None,
      name=f"chip {lab}")
    t(f, x, y + 4, lab, 9.5, fg, "Medium", width=w, align="center")
    return w


# ══ header ═══════════════════════════════════════════════════════════
t(f, 280, 84, "Ringkasan 8 Kriteria", 24, T1, "Bold", name="page-title")
t(f, 280, 112,
  "Penilaian instrumen LAM-PTKes · Kebidanan · D-III · TS 2025/2026 · data per 8 Nov 2026",
  11, INK3)

r(f, 1156, 84, 120, 34, fill=SURF, radius=6, stroke=LINE, name="filter")
t(f, 1156, 93, "Semua kriteria", 10.5, INK2, "Medium", width=120, align="center")
r(f, 1288, 84, 120, 34, fill=ORN, radius=6, name="export")
t(f, 1288, 93, "Ekspor", 11, "#FFFFFF", "Bold", width=120, align="center")

# ══ scale strip — table-level only, labelled as such ═════════════════
SY = 152
r(f, 280, SY, 1128, 76, fill=SURF, radius=10, stroke=LINE, name="strip")
t(f, 304, SY + 14, "Tabel data dukung", 9.5, INK3, "Medium")
stats = [(str(T_DONE), "lengkap", OK), (str(BELUM), "belum lengkap", WARN),
         (str(T_NA), "tak berlaku", INK3), (str(T_TABEL), "total wajib", T1),
         (f"{T_DOC_DONE}/{T_DOCS}", "dokumen", T1)]
sx = 304
for i, (val, lab, col) in enumerate(stats):
    t(f, sx, SY + 30, val, 22, col, "Bold", family=MONO)
    t(f, sx, SY + 58, lab, 9.5, INK3)
    if i < len(stats) - 1:
        r(f, sx + 128, SY + 30, 1, 28, fill=LINE2, name="vr")
    sx += 168

t(f, 1116, SY + 14, "Dokumen & tabel dihitung terpisah", 9.5, INK3)
t(f, 1116, SY + 32, "Tabel wajib = 38 − 6 tak berlaku", 10, INK2)
t(f, 1116, SY + 48, "= 32 tabel harus lengkap", 10, INK2)

# ══ card list ═══════════════════════════════════════════════════════
# column grid, same on every card
C_NO, C_TITLE, C_DOC, C_BAR, C_BADGE, C_FIND, C_ACT = 24, 62, 360, 452, 650, 754, 986
FIND_W = C_ACT - C_FIND - 16
Y0, CARD_H, GAP = 244, 100, 10

# header row for the table
hx, hy = 280, 232
t(f, hx + C_TITLE, hy, "Kriteria", 9.5, INK3, "Medium")
t(f, hx + C_DOC, hy, "Dokumen", 9.5, INK3, "Medium")
t(f, hx + C_BAR, hy, "Tabel", 9.5, INK3, "Medium")
t(f, hx + C_BADGE, hy, "Status", 9.5, INK3, "Medium")
t(f, hx + C_FIND, hy, "Temuan", 9.5, INK3, "Medium")
t(f, hx + C_ACT, hy, "Aksi", 9.5, INK3, "Medium", width=92, align="right")

y = Y0
for kid, nama, tot, done, na, docs, finds in KRIT:
    r(f, 280, y, 1128, CARD_H, fill=SURF, radius=10, stroke=LINE, name=f"card {kid}")

    # number
    t(f, 280 + C_NO, y + 16, kid, 10, INK3, "Bold", family=MONO, width=30)

    # title + doc count
    t(f, 280 + C_TITLE, y + 14, nama, 12.5, T1, "Bold", width=390)
    doc_done = DOC_DONE_BY_KRIT[kid]
    t(f, 280 + C_DOC, y + 16, f"{doc_done}/{docs}", 11, INK2, "Medium", family=MONO)
    t(f, 280 + C_DOC, y + 32, "butir", 9.5, INK3)

    # tables: bar on a common scale, NA shown as hatched count not as progress
    if tot == 0:
        t(f, 280 + C_BAR, y + 14, "0/0", 11, INK3, "Bold", family=MONO)
        t(f, 280 + C_BAR, y + 32, "tanpa tabel", 9, INK3)
    else:
        wajib = tot - na
        t(f, 280 + C_BAR, y + 14, f"{done}/{wajib}", 11, T1, "Bold", family=MONO)
        if na:
            lab = f"{na} tak berlaku"
            w = 10 + len(lab) * 4.9          # fits inside the Tabel column
            r(f, 280 + C_BAR + 46, y + 15, w, 18, fill="#F9FAFB", radius=4,
              stroke=INK3, name="na-tag")
            t(f, 280 + C_BAR + 46, y + 19, lab, 8.5, INK3, "Medium",
              width=w, align="center")
        BW = 92
        r(f, 280 + C_BAR, y + 38, BW, 6, fill=LINE2, radius=3, name="track")
        if wajib:
            r(f, 280 + C_BAR, y + 38, max(round(BW * done / wajib), 2), 6,
              fill=OK if done == wajib else ORN, radius=3, name="fill")

    # status — criterion level, derived from its own tables
    if tot == 0:
        kind = "ok" if all("Tidak ada temuan" in f_ for f_ in finds) else "warn"
    elif done == tot - na and not any("belum" in f_.lower() or "selisih" in f_.lower()
                                      or "berjumlah" in f_.lower() for f_ in finds):
        kind = "ok"
    elif done == 0:
        kind = "bad"
    else:
        kind = "warn"
    chip(280 + C_BADGE, y + 16, kind)

    # findings — colour by meaning, not by card
    fy = y + 14
    for msg in finds[:2]:
        clean = ("Tidak ada temuan" in msg or "tersimpan" in msg
                 or "sudah diunggah" in msg)
        t(f, 280 + C_FIND, fy, ("· " if not clean else "+ ") + msg, 9.5,
          OK if clean else WARN, width=FIND_W)
        # ~48 chars per line at 9.5px in this column
        lines = max(1, -(-len(msg) // 46))
        fy += 14 * lines + 3

    # hover affordance
    t(f, 280 + C_ACT, y + 16, "Buka →", 10, IND, "Medium", width=92, align="right")

    y += CARD_H + GAP

# ══ footer note ═════════════════════════════════════════════════════
t(f, 280, y + 4,
  "Status kriteria diturunkan dari tabelnya sendiri — bukan dari status tabel lain.",
  9.5, INK3)

if __name__ == "__main__":
    removed = clear_content()
    res = L.batch(k.ops, label="Ringkasan 8 Kriteria")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"removed {removed}, drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:150])
    # the frame grew: 8 cards no longer fit in 1080
    L.batch([{"command": "node.set", "params": {"ids": [FRAME], "height": int(y + 40)}}],
            label="Resize 05")
    L.call("export", format="png", scale=2, ids=[FRAME], path="/tmp/r8.png")
    print("rendered /tmp/r8.png")
