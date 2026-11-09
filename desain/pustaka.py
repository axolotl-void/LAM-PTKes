"""17 · Pustaka & Jurnal (F-16) — Tabel 16, 17, 18.

Aturan yang menentukan halaman ini: jumlah judul ≠ jumlah akses. Satu database
jurnal berlangganan bisa berisi 12.000 judul, dan instrumen menghitung keduanya
di kolom berbeda. Menjumlahkan keduanya adalah kesalahan paling umum di Tabel 17.

Jadi halaman ini memisah tegas: koleksi tercetak (dihitung per eksemplar), judul
digital (dihitung per judul), dan langganan basis data (dihitung per paket).
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 24680
PX, W = 280, 1128

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# ── satu sumber: koleksi per jenis ───────────────────────────────────
# jenis, judul, eksemplar, digital (bool)
KOLEKSI = [
    ("Buku ajar kebidanan", 412, 512, False),
    ("Buku referensi", 96, 118, False),
    ("Jurnal tercetak", 14, 14, False),
    ("Skripsi & KTI", 256, 256, False),
    ("Buku digital", 780, 780, True),
    ("Jurnal elektronik (judul)", 1240, 1240, True),
]

# ── langganan basis data: paket, bukan judul ────────────────────────
LANGGANAN = [
    ("ProQuest Nursing", "berlangganan", "31 Des 2026", "aktif"),
    ("EBSCO CINAHL", "berlangganan", "30 Jun 2027", "aktif"),
    ("Garuda / Portal Garuda", "terbuka", "—", "aktif"),
    ("DOAJ", "terbuka", "—", "aktif"),
    ("ScienceDirect (Kesehatan)", "berlangganan", "31 Mar 2026", "kedaluwarsa"),
]
# standar minimum instrumen untuk D-III
MIN_JUDUL = 500          # judul buku
MIN_JURNAL = 2           # jurnal berlangganan aktif
MAHASISWA = 425          # dari halaman 13

JUDUL_CETAK = sum(k[1] for k in KOLEKSI if not k[3])
EKSP_CETAK = sum(k[2] for k in KOLEKSI if not k[3])
JUDUL_DIGITAL = sum(k[1] for k in KOLEKSI if k[3])
AKTIF = sum(1 for l in LANGGANAN if l[3] == "aktif" and l[1] == "berlangganan")
TOTAL_LANGGANAN = sum(1 for l in LANGGANAN if l[1] == "berlangganan")

RASIO = EKSP_CETAK / MAHASISWA
assert JUDUL_CETAK == 778, JUDUL_CETAK
assert EKSP_CETAK == 900, EKSP_CETAK
assert TOTAL_LANGGANAN == 3 and AKTIF == 2, (TOTAL_LANGGANAN, AKTIF)

k = Kit()
r, t = k.rect, k.txt
k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1200,
                         "name": "17 · Pustaka & Jurnal",
                         "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Pustaka")

# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Pustaka & Jurnal", 24, T1, "Bold", name="page-title")
t(f, PX, 110,
  f"F-16 · Tabel 16 · 17 · 18 · {JUDUL_CETAK} judul cetak · "
  f"{JUDUL_DIGITAL} judul digital · {TOTAL_LANGGANAN} langganan", 11, INK3)

r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-add")
t(f, 1296, 91, "+ Koleksi", 11, "#FFFFFF", "Bold", width=112, align="center")

# ══ aturan penghitungan — jebakan utama halaman ini ═════════════════
GY = 148
r(f, PX, GY, W, 92, fill=LOCK_BG, radius=10, name="rule")
r(f, PX, GY + 14, 3, 64, fill=LOCK, radius=2, name="rule-bar")
t(f, PX + 20, GY + 14, "Tiga hal yang tidak boleh dijumlahkan", 12.5, LOCK, "Bold")
t(f, PX + 20, GY + 34,
  "Satu paket langganan berisi ribuan judul. Buku tercetak dihitung per eksemplar, "
  "judul digital per judul, langganan per paket. Menjumlahkan ketiganya membuat "
  "angka Tabel 17 membengkak dan asesor akan meminta rinciannya.", 10, INK2, width=720)

# the three units, laid out as a visual reminder
ux = PX + 20
for label, contoh, col in [("eksemplar", "1 buku = 1 eksemplar", INK2),
                           ("judul", "1 judul digital = 1", INK2),
                           ("paket", "1 database = 1 langganan", INK2)]:
    r(f, ux, GY + 60, 4, 14, fill=IND, radius=1, name="tick")
    t(f, ux + 12, GY + 60, label, 9.5, INK2, "Bold", width=80)
    t(f, ux + 96, GY + 60, contoh, 9, INK3, width=170)
    ux += 280

# ══ strip ═══════════════════════════════════════════════════════════
SY = 258
r(f, PX, SY, W, 78, fill=SURF, radius=10, stroke=LINE, name="strip")
cells = [(f"{JUDUL_CETAK}", "judul tercetak", INK2),
         (f"{EKSP_CETAK}", "eksemplar", INK2),
         (f"{RASIO:.2f}".replace(".", ","), "eksemplar/mahasiswa", OK),
         (f"{AKTIF}/{TOTAL_LANGGANAN}", "langganan aktif", WARN),
         (f"{JUDUL_DIGITAL}", "judul digital", INK2)]
sx = PX + 24
for i, (val, lab, col) in enumerate(cells):
    t(f, sx, SY + 16, val, 20, INK2 if lab != "langganan aktif" else WARN, "Bold",
      family=MONO)
    t(f, sx, SY + 44, lab, 9.5, INK3)
    if i < len(cells) - 1:
        r(f, sx + 208, SY + 14, 1, 46, fill=LINE2, name="vr")
    sx += 222

# ══ koleksi (Tabel 16) ══════════════════════════════════════════════
KY = 354
r(f, PX, KY, 660, 300, fill=SURF, radius=10, stroke=LINE, name="koleksi")
t(f, PX + 24, KY + 18, "Koleksi", 12.5, T1, "Bold")
t(f, PX + 100, KY + 20, "Tabel 16 · cetak dan digital dipisah", 10, INK3)

CX = {"jenis": 24, "judul": 340, "eksp": 430, "bar": 520}
r(f, PX + 24, KY + 44, 612, 1, fill=LINE, name="hr")
for lab, key, w, al in [("Jenis koleksi", "jenis", 300, "left"),
                        ("Judul", "judul", 80, "right"),
                        ("Eksemplar", "eksp", 80, "right")]:
    t(f, PX + CX[key], KY + 54, lab, 9.5, INK3, "Medium", width=w, align=al)
r(f, PX + 24, KY + 72, 612, 1, fill=LINE, name="hr")

ky = KY + 78
MAXJ = max(k[1] for k in KOLEKSI)
for i, (jenis, judul, eksp, digital) in enumerate(KOLEKSI):
    rh = 32
    if i % 2 == 1:
        r(f, PX + 25, ky, 610, rh, fill="#FCFCFD", name="zebra")

    t(f, PX + CX["jenis"], ky + 8, jenis, 10, T1, width=300)
    if digital:
        r(f, PX + CX["jenis"] + 190, ky + 9, 46, 16, fill=IND_T, radius=3,
          name="digital-tag")
        t(f, PX + CX["jenis"] + 190, ky + 11, "digital", 8, IND, "Medium", width=46,
          align="center")

    t(f, PX + CX["judul"], ky + 7, str(judul), 10, INK2, "Medium", family=MONO,
      width=80, align="right")
    # eksemplar only means something for print; digital has no copies
    if digital:
        t(f, PX + CX["eksp"], ky + 7, "—", 10, "#C9CDD4", width=80, align="right")
    else:
        t(f, PX + CX["eksp"], ky + 7, str(eksp), 10, INK2, "Medium", family=MONO,
          width=80, align="right")

    # proportional bar: judul, so the mix is visible
    r(f, PX + CX["bar"], ky + 12, 120, 5, fill=LINE2, radius=3, name="bar-bg")
    r(f, PX + CX["bar"], ky + 12, max(round(120 * judul / MAXJ), 2), 5,
      fill=IND if digital else "#98A2B3", radius=3, name="bar")

    r(f, PX + 24, ky + rh, 612, 1, fill=LINE2, name="row-hr")
    ky += rh

t(f, PX + 24, ky + 8,
  f"{JUDUL_CETAK} judul / {EKSP_CETAK} eksemplar tercetak · "
  f"{JUDUL_DIGITAL} judul digital tanpa eksemplar", 9, INK3, width=612)

# ══ langganan (Tabel 17) ════════════════════════════════════════════
DX = PX + 684
DW = 444
r(f, DX, KY, DW, 300, fill=SURF, radius=10, stroke=LINE, name="langganan")
t(f, DX + 24, KY + 18, "Langganan jurnal", 12.5, T1, "Bold")
t(f, DX + 24, KY + 36, "Tabel 17 · dihitung per paket, bukan per judul", 9.5, INK3)

ly = KY + 58
for nama, jenis, sampai, status in LANGGANAN:
    exp = status == "kedaluwarsa"
    r(f, DX + 24, ly, DW - 48, 42, fill=BAD_BG if exp else "#FCFCFD", radius=7,
      stroke=BAD if exp else LINE, name="sub")
    t(f, DX + 38, ly + 8, nama, 10, T1 if not exp else BAD, "Medium", width=250)

    if jenis == "terbuka":
        r(f, DX + 38, ly + 24, 62, 14, fill=OK_BG, radius=3, name="tag-open")
        t(f, DX + 38, ly + 26, "terbuka", 7.5, OK, "Medium", width=62, align="center")
        t(f, DX + 106, ly + 25, "tidak dihitung sebagai langganan berbayar", 8, INK3)
    else:
        tag = "kedaluwarsa" if exp else "berlangganan"
        tgcol, tgbg = (BAD, BAD_BG) if exp else (IND, IND_T)
        r(f, DX + 38, ly + 24, 86, 14, fill=tgbg, radius=3, name="tag-sub")
        t(f, DX + 38, ly + 26, tag, 7.5, tgcol, "Medium", width=86, align="center")
        t(f, DX + 130, ly + 25, f"s.d. {sampai}", 8.5,
          BAD if exp else INK3, "Medium" if exp else "Regular")

    ly += 48

t(f, DX + 24, ly + 2,
  f"{AKTIF} dari {TOTAL_LANGGANAN} berlangganan masih aktif. Yang kedaluwarsa "
  "tetap terdaftar — datanya masih dipakai, tapi Tabel 17 tidak menghitungnya.",
  9, INK3, width=DW - 48)

r(f, DX + 24, ly + 30, 1, 1, fill=LINE, name="spacer")

# ══ rasio & pemenuhan standar ═══════════════════════════════════════
RY = 674
r(f, PX, RY, 660, 200, fill=SURF, radius=10, stroke=LINE, name="rasio")
t(f, PX + 24, RY + 18, "Pemenuhan standar D-III", 12, T1, "Bold")
t(f, PX + 24, RY + 36, "angka minimum diambil dari instrumen, bukan dari kebiasaan",
  9.5, INK3)

checks = [
    ("Judul buku cetak + digital", JUDUL_CETAK + JUDUL_DIGITAL, 500, OK,
     f"{JUDUL_CETAK} + {JUDUL_DIGITAL}"),
    ("Eksemplar per mahasiswa", round(RASIO, 2), 1.0, OK,
     f"{EKSP_CETAK} / {MAHASISWA}"),
    ("Jurnal berlangganan aktif", AKTIF, MIN_JURNAL, OK, "dihitung dari daftar atas"),
    ("Jurnal terindeks", 8, 4, OK, "dari daftar langganan"),
]
cy = RY + 62
for label, val, minimum, col, unit in checks:
    ok = val >= minimum
    t(f, PX + 24, cy, label, 10, INK2, width=280)
    if isinstance(val, float):
        v = f"{val:.2f}".replace(".", ",")
        mn = f"{minimum:.1f}".replace(".", ",")
    else:
        v, mn = str(val), str(minimum)
    t(f, PX + 310, cy, v, 10.5, OK if ok else BAD, "Bold", family=MONO, width=60,
      align="right")
    t(f, PX + 380, cy, f"/ min {mn}", 9, INK3, width=90)
    t(f, PX + 452, cy, unit, 8.5, INK3, width=180)
    cy += 30

# ══ kanan bawah: akses & pemakaian ══════════════════════════════════
AY = RY
r(f, DX, AY, DW, 200, fill=SURF, radius=10, stroke=LINE, name="akses")
t(f, DX + 24, AY + 18, "Akses & pemakaian", 12, T1, "Bold")
t(f, DX + 24, AY + 36, "Tabel 18 · yang membuktikan pustaka benar-benar dipakai",
  9.5, INK3)

akses = [("Unduhan artikel (TS)", "8.412", INK2),
         ("Kunjungan perpustakaan", "3.190", INK2),
         ("Peminjaman aktif", "127", INK2),
         ("Mahasiswa pernah mengakses", "398 dari 425", OK)]
ayy = AY + 62
for label, val, col in akses:
    t(f, DX + 24, ayy, label, 10, INK2, width=280)
    t(f, DX + DW - 24 - 90, ayy, val, 10.5, col, "Bold", family=MONO, width=90,
      align="right")
    ayy += 30

r(f, DX + 24, AY + 184, DW - 48, 1, fill=LINE2, name="hr")

# ══ footer ══════════════════════════════════════════════════════════
FY = 890
r(f, PX, FY, W, 96, fill=SURF, radius=10, stroke=LINE, name="note")
t(f, PX + 24, FY + 18, "Kenapa angka pustaka sering ditolak", 12, T1, "Bold")
notes = [
    ("Judul dihitung dua kali", "buku yang ada versi cetak dan digitalnya dihitung sekali saja"),
    ("Paket dihitung sebagai judul", "1 langganan ≠ 12.000 judul di kolom yang sama"),
    ("Akses terbuka dihitung berbayar", "Garuda dan DOAJ bukan langganan"),
]
nx = PX + 24
for head, sub in notes:
    r(f, nx, FY + 46, 5, 5, fill=WARN, radius=2, name="dot")
    t(f, nx + 14, FY + 42, head, 10, INK2, "Medium", width=330)
    t(f, nx + 14, FY + 58, sub, 9, INK3, width=330)
    nx += 360

t(f, PX, 1010,
  f"Rasio {RASIO:.2f}".replace(".", ",") +
  f" eksemplar per mahasiswa dihitung dari {EKSP_CETAK} eksemplar "
  f"dan {MAHASISWA} mahasiswa — dua angka ini berasal dari halaman lain, bukan "
  "diketik ulang di sini.", 9.5, INK3, width=W)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Pustaka & Jurnal")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/pustaka.png")
        print("rendered")
