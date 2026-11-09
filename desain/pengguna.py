"""15 · Pengguna & Hak Akses (F-22).

Bukan daftar pengguna. Yang menentukan halaman ini adalah satu aturan: orang yang
mengisi data tidak boleh orang yang memverifikasi. Jadi bentuknya matriks peran,
dan yang dicari sistem adalah pelanggaran pemisahan tugas — bukan jumlah akun.

Ini juga tempat permintaan "buka kunci" dari halaman Identitas mendarat.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 21560
PX, W = 280, 1128

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# ── sumber kebenaran ─────────────────────────────────────────────────
# nama, peran, cakupan, status, terakhir aktif
PENGGUNA = [
    ("Siti Rahmawati, S.ST., M.Keb", "Operator Prodi", "Kebidanan", "aktif", "hari ini"),
    ("Nurhayati, S.Kep., M.Kep", "Operator Prodi", "Keperawatan", "aktif", "kemarin"),
    ("Dr. Maimunah, M.Keb", "Verifikator", "Kebidanan", "aktif", "2 hari lalu"),
    ("Yusrizal, S.Kep., Ns., M.Kep", "Verifikator", "Keperawatan", "aktif", "3 hari lalu"),
    ("Dr. Ir. Zulfikar, M.Pd", "Admin UPPS", "Semua prodi", "aktif", "hari ini"),
    ("Prof. Dr. Cut Zahriah, M.Pd", "Pimpinan", "Semua prodi", "aktif", "1 minggu lalu"),
    ("Marlina, S.ST., M.Keb", "Operator + Verifikator", "Kebidanan + Keperawatan",
     "konflik", "hari ini"),
    ("Hasnawati, A.Md.Keb", "Operator Prodi", "Kebidanan", "nonaktif", "14 Sep 2026"),
]

PERAN = ["Operator Prodi", "Verifikator", "Admin UPPS", "Pimpinan"]
IZIN = ["Isi", "Impor", "Verifikasi", "Buka kunci", "Kelola pengguna", "Ekspor"]

# peran -> izin (1 = boleh, 0 = tidak, 2 = hanya lihat)
MATRIX = {
    "Operator Prodi": [1, 1, 0, 0, 0, 1],
    "Verifikator":    [0, 0, 1, 0, 0, 1],
    "Admin UPPS":     [1, 1, 1, 1, 1, 1],
    "Pimpinan":       [2, 2, 2, 0, 0, 1],
}

# pelanggaran pemisahan tugas: peran yang tidak boleh dipegang orang sama
TERLARANG = [("Operator Prodi", "Verifikator")]

AKTIF = sum(1 for p in PENGGUNA if p[3] == "aktif")
KONFLIK = sum(1 for p in PENGGUNA if p[3] == "konflik")
NONAKTIF = sum(1 for p in PENGGUNA if p[3] == "nonaktif")
assert AKTIF + KONFLIK + NONAKTIF == len(PENGGUNA)
assert KONFLIK == 1

# permintaan buka kunci — nyambung ke halaman 08 Identitas
PERMINTAAN = [
    ("Identitas UPPS & Prodi", "field jenjang", "Siti Rahmawati", "9 Nov 2026 · 09:12",
     "menunggu"),
    ("Periode Akreditasi", "tanggal pengajuan", "Dr. Ir. Zulfikar", "8 Nov 2026 · 14:20",
     "disetujui"),
]
MENUNGGU = sum(1 for p in PERMINTAAN if p[4] == "menunggu")
assert MENUNGGU == 1, MENUNGGU

k = Kit()
r, t = k.rect, k.txt
k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1200,
                         "name": "15 · Pengguna & Hak Akses",
                         "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Dashboard")

# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Pengguna & Hak Akses", 24, T1, "Bold", name="page-title")
t(f, PX, 110,
  f"F-22 · {len(PENGGUNA)} akun · 4 peran · yang diperiksa bukan jumlah akun, "
  "tapi pemisahan tugas", 11, INK3)

r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-add")
t(f, 1296, 91, "+ Undang", 11, "#FFFFFF", "Bold", width=112, align="center")

# ══ pelanggaran pemisahan tugas — alasan halaman ini ada ════════════
GY = 148
r(f, PX, GY, W, 92, fill=BAD_BG, radius=10, name="sod")
r(f, PX, GY + 14, 3, 64, fill=BAD, radius=2, name="sod-bar")
t(f, PX + 20, GY + 14, "1 akun memegang peran yang tidak boleh digabung", 12.5,
  BAD, "Bold")
t(f, PX + 20, GY + 34,
  "Marlina mengisi data Kebidanan sekaligus memverifikasi Keperawatan. Artinya data "
  "yang dia isi bisa dia setujui sendiri — asesor akan menanyakan itu.", 10, INK2,
  width=700)

t(f, PX + 20, GY + 60, "Operator Prodi", 9.5, INK3, width=100)
t(f, PX + 20, GY + 72, "mengisi", 9, INK3, width=100)
t(f, PX + 130, GY + 64, "✕", 11, BAD, "Bold", width=20)
t(f, PX + 156, GY + 60, "Verifikator", 9.5, INK3, width=100)
t(f, PX + 156, GY + 72, "menyetujui", 9, INK3, width=100)

r(f, PX + W - 24 - 158, GY + 30, 158, 32, fill=BAD, radius=6, name="btn-split")
t(f, PX + W - 24 - 158, GY + 39, "Pisahkan peran", 10.5, "#FFFFFF", "Bold",
  width=158, align="center")

# ══ strip ═══════════════════════════════════════════════════════════
SY = 258
r(f, PX, SY, W, 78, fill=SURF, radius=10, stroke=LINE, name="strip")
cells = [(str(AKTIF), "aktif tanpa konflik", INK2), (str(KONFLIK), "konflik peran", BAD),
         (str(MENUNGGU), "buka kunci menunggu", WARN),
         ("4", "peran", INK2), ("0", "akun tanpa verifikasi 2 langkah", OK)]
sx = PX + 24
for i, (val, lab, col) in enumerate(cells):
    t(f, sx, SY + 16, val, 20, col, "Bold", family=MONO)
    t(f, sx, SY + 44, lab, 9.5, INK3)
    if i < len(cells) - 1:
        r(f, sx + 196, SY + 14, 1, 46, fill=LINE2, name="vr")
    sx += 212

# ══ matriks peran × izin ════════════════════════════════════════════
MY = 352
r(f, PX, MY, W, 236, fill=SURF, radius=10, stroke=LINE, name="matrix")
t(f, PX + 24, MY + 18, "Peran menentukan izin", 12.5, T1, "Bold")
t(f, PX + 216, MY + 20,
  "satu-satunya tempat aturan ini didefinisikan — jangan disebar di kode", 10, INK3)

MXC = {"peran": 24}
for i, nm in enumerate(IZIN):
    MXC[nm] = 300 + i * 138
r(f, PX + 24, MY + 44, W - 48, 1, fill=LINE, name="hr")
t(f, PX + MXC["peran"], MY + 54, "Peran", 9.5, INK3, "Medium", width=260)
for nm in IZIN:
    t(f, PX + MXC[nm], MY + 54, nm, 9.5, INK3, "Medium", width=130)
r(f, PX + 24, MY + 72, W - 48, 1, fill=LINE, name="hr")

my = MY + 80
for peran in PERAN:
    rh = 38
    izin = MATRIX[peran]
    t(f, PX + MXC["peran"], my + 9, peran, 11, T1, "Medium", width=200)
    if peran == "Admin UPPS":
        r(f, PX + MXC["peran"] + 106, my + 11, 62, 17, fill=WARN_BG, radius=4,
          name="max1")
        t(f, PX + MXC["peran"] + 106, my + 14, "maks 1 akun", 8, WARN, "Medium",
          width=62, align="center")
    for i, val in enumerate(izin):
        cx = PX + MXC[IZIN[i]]
        if val == 1:
            r(f, cx + 40, my + 9, 20, 20, fill=OK_BG, radius=4, name="yn")
            t(f, cx + 40, my + 13, "✓", 10, OK, "Bold", width=20, align="center")
        elif val == 2:
            r(f, cx + 40, my + 9, 20, 20, fill="#EAECF0", radius=4, stroke="#98A2B3",
              name="yn")
            t(f, cx + 40, my + 12, "M", 9, "#475467", "Bold", width=20,
              align="center")
        else:
            t(f, cx + 40, my + 13, "—", 10, "#C9CDD4", width=20, align="center")
    r(f, PX + 24, my + rh, W - 48, 1, fill=LINE2, name="row-hr")
    my += rh

# the rule that isn't visible in the grid
t(f, PX + 24, my + 8,
  "Operator dan Verifikator tidak boleh dipegang satu orang. Admin UPPS memegang "
  "keduanya — itu sebabnya admin dibatasi 1 akun (lihat badge di barisnya).",
  9.5, INK3, width=W - 48)

# legend: the eye glyph is meaningless without one
lgx = PX + W - 24 - 320
t(f, lgx, my + 8, "✓ boleh", 9, INK3, width=64)
r(f, lgx + 74, my + 10, 14, 14, fill="#EAECF0", radius=3, stroke="#98A2B3",
  name="leg-eye")
t(f, lgx + 74, my + 11, "M", 7.5, "#475467", "Bold", width=14, align="center")
t(f, lgx + 94, my + 8, "hanya lihat", 9, INK3, width=84)
t(f, lgx + 188, my + 8, "— tidak boleh", 9, INK3, width=120)

# ══ tabel pengguna ══════════════════════════════════════════════════
UY = 610
UW = 660
r(f, PX, UY, UW, 392, fill=SURF, radius=10, stroke=LINE, name="users")
t(f, PX + 24, UY + 18, "Akun", 12.5, T1, "Bold")
t(f, PX + 90, UY + 20, f"{len(PENGGUNA)} akun termasuk 1 nonaktif", 10, INK3)

UX = {"nama": 24, "peran": 330, "cakupan": 466, "status": 570}
r(f, PX + 24, UY + 44, UW - 48, 1, fill=LINE, name="hr")
for lab, key in [("Nama", "nama"), ("Peran", "peran"), ("Cakupan", "cakupan"),
                 ("Status", "status")]:
    t(f, PX + UX[key], UY + 54, lab, 9.5, INK3, "Medium", width=120)
r(f, PX + 24, UY + 72, UW - 48, 1, fill=LINE, name="hr")

uy = UY + 78
for nama, peran, cakupan, status, aktif in PENGGUNA:
    rh = 38
    konf = status == "konflik"
    if konf:
        r(f, PX + 25, uy, UW - 50, rh, fill=BAD_BG, name="row-bad")

    # initials avatar
    ini = "".join(w[0] for w in nama.replace(",", "").split()[:2] if w[0].isupper())[:2]
    r(f, PX + UX["nama"], uy + 8, 24, 24, fill=BAD_BG if konf else IND_T, radius=12,
      name="av")
    t(f, PX + UX["nama"], uy + 13, ini, 9, BAD if konf else IND, "Bold", width=24,
      align="center")

    nm = nama if len(nama) <= 34 else nama[:32] + "…"
    t(f, PX + UX["nama"] + 32, uy + 9, nm, 10, T1, "Medium", width=270)
    t(f, PX + UX["nama"] + 32, uy + 22, f"aktif {aktif}", 8.5, INK3, width=270)

    t(f, PX + UX["peran"], uy + 9, peran, 9.5, BAD if konf else INK2,
      "Medium" if konf else "Regular", width=130)
    t(f, PX + UX["cakupan"], uy + 9, cakupan, 9, INK3, width=100)

    col = {"aktif": OK, "konflik": BAD, "nonaktif": INK3}[status]
    bg = {"aktif": OK_BG, "konflik": BAD_BG, "nonaktif": LINE2}[status]
    lab = {"aktif": "aktif", "konflik": "konflik", "nonaktif": "nonaktif"}[status]
    r(f, PX + UX["status"], uy + 11, 62, 18, fill=bg, radius=4, name="badge")
    t(f, PX + UX["status"], uy + 14, lab, 8.5, col, "Medium", width=62,
      align="center")

    r(f, PX + 24, uy + rh, UW - 48, 1, fill=LINE2, name="row-hr")
    uy += rh

t(f, PX + 24, uy + 8,
  f"{len(PENGGUNA)} akun termasuk 1 nonaktif. Akun nonaktif tetap disimpan supaya "
  "riwayat pengisian tidak hilang.", 9, INK3, width=UW - 48)

# ══ antrian buka kunci + audit ══════════════════════════════════════
DX = PX + 684
DW = 444
r(f, DX, UY, DW, 240, fill=SURF, radius=10, stroke=LINE, name="unlock")
t(f, DX + 24, UY + 18, "Permintaan buka kunci", 12, T1, "Bold")
t(f, DX + 24, UY + 36, "yang mendarat dari halaman lain", 9.5, INK3)

qy = UY + 58
for modul, field, siapa, kapan, status in PERMINTAAN:
    menunggu = status == "menunggu"
    r(f, DX + 24, qy, DW - 48, 88, fill=WARN_BG if menunggu else "#FCFCFD",
      radius=8, stroke=WARN if menunggu else LINE, name="req")
    t(f, DX + 38, qy + 10, modul, 10, T1, "Medium", width=340)
    t(f, DX + 38, qy + 26, field, 9.5, INK2, width=340)
    t(f, DX + 38, qy + 42, f"{siapa} · {kapan}", 8.5, INK3, width=340)

    # actions sit on their own line: no collision when a name is long
    if menunggu:
        r(f, DX + 38, qy + 58, 74, 22, fill=WARN, radius=5, name="btn-ok")
        t(f, DX + 38, qy + 62, "Buka kunci", 9, "#FFFFFF", "Bold", width=74,
          align="center")
        r(f, DX + 120, qy + 58, 60, 22, fill=SURF, radius=5, stroke=LINE,
          name="btn-no")
        t(f, DX + 120, qy + 62, "Tolak", 9, INK2, "Medium", width=60, align="center")
    else:
        t(f, DX + 38, qy + 62, "✓ disetujui", 9, OK, "Medium", width=120)
    qy += 96

# audit note
AY = UY + 256
r(f, DX, AY, DW, 136, fill=SURF, radius=10, stroke=LINE, name="audit")
t(f, DX + 24, AY + 18, "Yang dicatat sistem", 12, T1, "Bold")
notes = [("Setiap buka kunci", "siapa, kapan, field apa, alasan"),
         ("Setiap verifikasi", "tidak bisa oleh pengisi data yang sama"),
         ("Setiap impor", "jumlah baris diterima dan ditolak")]
ny = AY + 44
for head, sub in notes:
    r(f, DX + 24, ny + 5, 5, 5, fill=IND, radius=2, name="dot")
    t(f, DX + 38, ny, head, 10, INK2, "Medium", width=370)
    t(f, DX + 38, ny + 15, sub, 9.5, INK3, width=370)
    ny += 30

# ══ footer ══════════════════════════════════════════════════════════
t(f, PX, 1030,
  "Hak akses mengikuti prodi di sidebar, bukan mengikuti akun. Operator Kebidanan "
  "tidak melihat data Keperawatan meski membuka URL yang sama.", 9.5, INK3, width=W)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Pengguna & Hak Akses")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/pengguna.png")
        print("rendered")
