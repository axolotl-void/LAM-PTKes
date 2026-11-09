"""10 · Dosen & Tendik (F-06).

Different page type from F-01/F-02: this is a registry, not a form. 24 people
feed 8 tables, so the page is a dense roster + a detail drawer that answers the
only question that matters: "where does this person's data go?"

One rule drives the design: a lecturer teaching in both prodi is ONE row with
two memberships, never duplicated. The table shows that as stacked chips.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 13640
PX = 280
W = 1128

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# one source of truth for the roster numbers used in the header
ROSTER = {"total": 24, "tetap": 18, "tidak_tetap": 4, "tendik": 2,
          "sertifikat": 15, "str": 21, "lintas_prodi": 3}
assert ROSTER["tetap"] + ROSTER["tidak_tetap"] + ROSTER["tendik"] == ROSTER["total"]

k = Kit()
r, t = k.rect, k.txt

k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1150,
                         "name": "10 · Dosen & Tendik",
                         "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Dashboard")

C_NO, C_NAME, C_JAB, C_PRODI, C_STR, C_ACT = 24, 62, 386, 610, 830, 1000


def blk(x, y, w, h, name, fill=SURF, stroke=LINE):
    return r(f, x, y, w, h, fill=fill, radius=10, stroke=stroke, name=name)


# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Dosen & Tendik", 24, T1, "Bold", name="page-title")
t(f, PX, 110, "F-06 · 24 orang mengisi 8 tabel · satu baris per orang, bukan per prodi", 11, INK3)

r(f, 1180, 82, 108, 34, fill=SURF, radius=6, stroke=LINE, name="btn-import")
t(f, 1180, 91, "Impor Excel", 10.5, INK2, "Medium", width=108, align="center")
r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-add")
t(f, 1296, 91, "+ Tambah", 11, "#FFFFFF", "Bold", width=112, align="center")

# ══ rekap strip: Tabel 15 needs exactly this breakdown ════════════════
SY = 148
blk(PX, SY, W, 84, "rekap")
t(f, PX + 24, SY + 16, "Rekap master", 10, INK3, "Medium")
t(f, PX + 24 + 100, SY + 16,
  "dihitung dari daftar · Tabel 15 memakai kolom jabatan", 9.5, INK3)
cells = [(str(ROSTER["tetap"]), "dosen tetap", T1), (str(ROSTER["tidak_tetap"]), "tidak tetap", T1),
         (str(ROSTER["tendik"]), "tendik", T1), (str(ROSTER["sertifikat"]), "punya sertifikat", OK),
         (str(ROSTER["total"] - ROSTER["sertifikat"]), "belum sertifikat", WARN),
         (str(ROSTER["lintas_prodi"]), "mengajar 2 prodi", IND)]
cx = PX + 24
for i, (val, lab, col) in enumerate(cells):
    t(f, cx, SY + 38, val, 20, col, "Bold", family=MONO)
    t(f, cx, SY + 62, lab, 9.5, INK3)
    if i < len(cells) - 1:
        r(f, cx + 150, SY + 36, 1, 32, fill=LINE2, name="vr")
    cx += 172

# ══ table card ══════════════════════════════════════════════════════
TY = 254
blk(PX, TY, W, 700, "table-card")

# filter chips row
chips = [("Semua", True, 66), ("Dosen tetap 18", False, 104),
         ("Tidak tetap 4", False, 100), ("Tendik 2", False, 74),
         ("Belum sertifikat 9", False, 118)]
cx = PX + 24
for lab, on, w in chips:
    r(f, cx, TY + 18, w, 28, fill=IND if on else SURF, radius=14,
      stroke=None if on else LINE, name="chip")
    t(f, cx, TY + 25, lab, 10, "#FFFFFF" if on else INK2, "Medium", width=w,
      align="center")
    cx += w + 8

r(f, PX + W - 24 - 180, TY + 18, 180, 28, fill=SURF, radius=6, stroke=LINE, name="search")
t(f, PX + W - 24 - 168, TY + 25, "Cari nama atau NIDN", 10, INK3)

# header row
HY = TY + 62
r(f, PX + 24, HY, W - 48, 1, fill=LINE, name="hr")
heads = [("Nama & gelar", C_NAME, 300), ("Jabatan fungsional", C_JAB, 200),
         ("Prodi", C_PRODI, 200), ("STR / sertifikat", C_STR, 150)]
for lab, dx, w in heads:
    t(f, PX + dx, HY + 10, lab, 9.5, INK3, "Medium", width=w)
t(f, PX + C_ACT, HY + 10, "Dipakai di", 9.5, INK3, "Medium", width=104, align="right")
r(f, PX + 24, HY + 28, W - 48, 1, fill=LINE, name="hr")

# roster rows — 24 people, first 9 shown; the rest summarised honestly
people = [
    ("Rina Kusumawati, S.ST., M.Keb", "NIDN 0112 0587 01", "Lektor", "Kebidanan", "STR · 2031", ["15", "16", "17", "21"], OK),
    ("Nurhayati, S.ST., M.Keb", "NIDN 0109 0790 02", "Asisten Ahli", "Kebidanan", "STR · 2029", ["15", "16"], OK),
    ("Cut Marlina, S.Tr.Keb., M.Keb", "NIDN 0120 0588 03", "Lektor", "Kebidanan · Keperawatan", "STR · 2030", ["15", "16", "19"], OK),
    ("Sri Wahyuni, S.ST., M.Keb", "NIDN 0105 1285 04", "Lektor Kepala", "Kebidanan", "STR · 2028", ["15", "16", "17", "21"], OK),
    ("Dewi Sartika, S.Keb., M.Kes", "NIDN 0111 0991 05", "Asisten Ahli", "Kebidanan", "belum ada STR", ["--"], WARN),
    ("Yusnidar, S.ST., M.Keb", "NIDN 0114 0392 06", "Lektor", "Kebidanan", "STR · 2032", ["15", "16"], OK),
    ("Marliana, S.Kep., Ns., M.Kep", "NIDN 0107 0788 07", "Lektor", "Keperawatan", "STR · 2030", ["15", "16", "19"], OK),
    ("Fitriani, S.Kep., Ns., M.Kep", "NIDN 0118 1190 08", "Asisten Ahli", "Keperawatan · Kebidanan", "STR · 2029", ["15", "16"], OK),
    ("Hendra Saputra, S.Kep., Ns., M.Kep", "NIDN 0103 0589 09", "Lektor Kepala", "Keperawatan", "STR · 2031", ["15", "16", "17"], OK),
]

ry = HY + 34
for i, (nama, nidn, jab, prodi, strv, used, kind) in enumerate(people):
    rh = 58
    if i % 2 == 1:
        r(f, PX + 25, ry, W - 50, rh, fill="#FCFCFD", name="zebra")

    # initials badge — cheap identity anchor for a long list
    ini = "".join([w[0] for w in nama.split()[:2]]).upper()
    r(f, PX + C_NO + 8, ry + 15, 30, 30, fill=IND_T, radius=15, name="ini")
    t(f, PX + C_NO + 8, ry + 22, ini, 10, IND, "Bold", width=30, align="center")

    t(f, PX + C_NAME, ry + 14, nama, 11.5, T1, "Medium", width=300)
    t(f, PX + C_NAME, ry + 32, nidn, 9.5, INK3, family=MONO, width=300)

    t(f, PX + C_JAB, ry + 22, jab, 10.5, INK2, width=200)

    # prodi: stacked/joined text; a second prodi is the thing that avoids duplication
    two = "·" in prodi
    t(f, PX + C_PRODI, ry + 22, prodi, 10, IND if two else INK2,
      "Medium" if two else "Regular", width=200)
    if two:
        r(f, PX + C_PRODI - 8, ry + 20, 2, 14, fill=IND, radius=1, name="two-bar")

    t(f, PX + C_STR, ry + 22, strv, 10, WARN if kind == "WARN" else INK2,
      "Medium" if kind == "WARN" else "Regular", width=160)

    # tables this person feeds
    ux = PX + C_ACT - 74
    for tag in used[:3]:
        r(f, ux, ry + 19, 26, 20, fill=LINE2, radius=4, name="tbl-tag")
        t(f, ux, ry + 23, tag, 9, INK2, family=MONO, width=26, align="center")
        ux += 30
    if len(used) > 3:
        t(f, ux, ry + 23, f"+{len(used) - 3}", 9, INK3, "Medium", family=MONO,
          width=24)

    r(f, PX + 24, ry + rh, W - 48, 1, fill=LINE2, name="row-hr")
    ry += rh

# footer of the table: honest about truncation
t(f, PX + 24, ry + 12,
  f"Menampilkan 9 dari {ROSTER['total']} orang · 3 mengajar di dua prodi dan tetap satu baris",
  10, INK3)
t(f, PX + W - 24 - 120, ry + 12, "Lihat semua 24 →", 10.5, IND, "Medium", width=120,
  align="right")

# ══ detail drawer preview — where the data goes ═════════════════════
DY = TY + 712
blk(PX, DY, W, 128, "drawer")
t(f, PX + 24, DY + 18, "Kalau satu orang dibuka", 11.5, T1, "Bold")
t(f, PX + 24, DY + 38, "Rina Kusumawati · Lektor · NIDN 0112 0587 01", 10, INK3)

flow = [("Tabel 15", "hitung per jabatan"), ("Tabel 16", "daftar nama"),
        ("Tabel 17", "beban sks"), ("Tabel 21", "pembimbing TA")]
fx = PX + 24
for i, (tbl, note) in enumerate(flow):
    r(f, fx, DY + 62, 196, 48, fill=SURF, radius=8, stroke=LINE, name="flow-card")
    t(f, fx + 12, DY + 70, tbl, 10.5, T1, "Bold", family=MONO)
    t(f, fx + 12, DY + 86, note, 9, INK3, width=170)
    if i < len(flow) - 1:
        t(f, fx + 198, DY + 78, "→", 11, INK3, width=14, align="center")
    fx += 220

t(f, PX + 24, DY + 118 - 6, "", 1, INK3)

# ══ footer note ═════════════════════════════════════════════════════
t(f, PX, DY + 148,
  "Master dosen satu baris per orang. Mengajar di dua prodi dicatat sebagai keanggotaan, bukan baris kedua.",
  9.5, INK3, width=W)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Dosen & Tendik")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/dosen.png")
        print("rendered")
