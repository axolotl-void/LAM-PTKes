"""08 · Identitas UPPS & Prodi (F-01).

The page that decides everything downstream: `jenjang` and `punya_tahap_profesi`
filter which of the 38 tables are wajib. So the form shows its own consequence
inline — a segmented control that immediately reports what it turns off.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 10560
CONTENT_X0 = 256
W_LEFT, W_RIGHT = 760, 344

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD = "#067647", "#B54708", "#B42318"
OK_BG, WARN_BG, BAD_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2"

PX, RX = 280, 280 + W_LEFT + 24        # main column, right rail

k = Kit()
r, t = k.rect, k.txt


def field(parent, x, y, w, label, value, note=None, kind="normal", mono=False):
    """One labelled field. kind: normal | readonly | derived | needs-check"""
    t(parent, x, y, label, 10, INK3, "Medium")
    ih = 38
    fill = SURF if kind in ("normal", "needs-check") else "#FCFCFD"
    stroke = BAD if kind == "needs-check" else LINE
    r(parent, x, y + 16, w, ih, fill=fill, radius=6, stroke=stroke, name=f"input {label}")
    t(parent, x + 12, y + 16 + 11, value, 11.5, T1 if kind != "readonly" else INK2,
      "Medium", family=MONO if mono else UI, width=w - 24)
    if kind == "readonly":
        t(parent, x + w - 12 - 46, y + 16 + 12, "terkunci", 9, INK3, width=46, align="right")
    elif kind == "needs-check":
        r(parent, x + w - 74, y + 16 + 10, 62, 18, fill=BAD_BG, radius=4, name="warn-tag")
        t(parent, x + w - 74, y + 16 + 13, "perlu cek", 8.5, BAD, "Medium", width=62,
          align="center")
    if note:
        t(parent, x, y + 16 + ih + 5, note, 9.5, INK3, width=w)


def devider(f, y, x=PX, w=W_LEFT):
    r(f, x, y, w, 1, fill=LINE2, name="divider")


# ── build the frame ──────────────────────────────────────────────────
k.ops.append({
    "command": "frame.create", "as": "f",
    "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1180,
               "name": "08 · Identitas UPPS & Prodi",
               "props": {"fill": BG, "clip": True}},
})
f = {"$ref": "f"}

k.topbar(f)
k.sidebar(f, aktif="Dashboard")

# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Identitas UPPS & Prodi", 24, T1, "Bold", name="page-title")
t(f, PX, 110,
  "F-01 · dipakai 38 tabel dan semua ekspor · isi terakhir diubah 6 Nov 2026 11:05 oleh Admin UPPS",
  11, INK3)

r(f, 1252, 82, 156, 34, fill="#F2F4F7", radius=6, stroke=LINE, name="btn-save")
t(f, 1252, 91, "Terkunci", 11, INK3, "Medium", width=156, align="center")


# verification strip — identity is the only thing that can be locked hard
SY = 146
r(f, PX, SY, W_LEFT + 24 + W_RIGHT, 56, fill=WARN_BG, radius=8, name="verify-strip")
r(f, PX, SY + 10, 3, 36, fill=WARN, radius=2, name="verify-bar")
t(f, PX + 16, SY + 12, "Terkunci — 1 field perlu konfirmasi", 11.5, WARN, "Bold")
t(f, PX + 16, SY + 30,
  "Diverifikasi Pimpinan 6 Nov 2026 · konfirmasi jenjang tidak mengunci halaman",
  10, INK2, width=740)
r(f, PX + W_LEFT + 24 + W_RIGHT - 150, SY + 14, 130, 30, fill=SURF, radius=6,
  stroke=WARN, name="btn-unlock")
t(f, PX + W_LEFT + 24 + W_RIGHT - 150, SY + 23, "Minta buka kunci", 10.5, "#7A3E00",
  "Medium", width=130, align="center")

# ══ main: UPPS ═══════════════════════════════════════════════════════
y = 224
t(f, PX, y, "Unit Pengelola Program Studi", 13, T1, "Bold")
t(f, PX + 220, y + 2, "4 dari 8 field diisi otomatis dari master PT", 10, INK3)
y += 26

R1 = PX + 380
field(f, PX, y, 360, "Nama perguruan tinggi", "Universitas Bina Bangsa Getsempena",
      kind="readonly", note="berlaku untuk semua prodi di UPPS ini")
field(f, R1, y, 360, "Nama unit pengelola", "Fakultas Ilmu Kesehatan", kind="readonly",
      note="berlaku untuk semua prodi di UPPS ini")
y += 74

field(f, PX, y, 360, "Narahubung", "Wakil Dekan I · 0812-6031-2799",
      note="Nama dan nomor seluler yang dihubungi asesor")
field(f, R1, y, 360, "Kota / Kabupaten", "Banda Aceh")
y += 74

field(f, PX, y, 360, "Alamat", "Jl. Tanggul Krueng Lamnyong No. 34",
      note="alamat lengkap UPPS")
field(f, R1, y, 360, "Telepon", "(0651) 7551-800", note="nomor yang aktif pada jam kerja")
y += 74

field(f, PX, y, 360, "Email", "fik@ubbg.ac.id")
field(f, R1, y, 360, "Website", "ubbg.ac.id/fik")
y += 78

# ══ main: Prodi ═════════════════════════════════════════════════════
devider(f, y, w=W_LEFT + W_RIGHT + 24)
y += 20
t(f, PX, y, "Program Studi", 13, T1, "Bold")
t(f, PX + 120, y + 2, "satu prodi per halaman · ganti lewat switch di sidebar", 10, INK3)
y += 26

field(f, PX, y, 360, "Nama program studi", "Kebidanan")
field(f, R1, y, 360, "Bidang", "Kebidanan")
y += 74

# jenjang: segmented, this is the field that filters tables
t(f, PX, y, "Jenjang", 10, INK3, "Medium")
JY = y + 16
opts = [("D-III", 62), ("Sarjana", 70), ("Sarjana + Profesi", 118), ("Spesialis", 78)]
jx = PX
for lab, w in opts:
    cur = lab == "D-III"
    r(f, jx, JY, w, 38, fill=IND if cur else SURF, radius=6,
      stroke=None if cur else LINE, name=f"jenjang {lab}")
    t(f, jx, JY + 12, lab, 10.5, "#FFFFFF" if cur else INK2,
      "Bold" if cur else "Regular", width=w, align="center")
    jx += w + 6

field(f, R1, y, 360, "Lama studi", "6 semester", mono=True)
y += 74

field(f, PX, y, 360, "Punya tahap profesi", "Tidak", kind="needs-check",
      note="Menentukan Tabel 4, 6, 7, 8, 11, 30 wajib atau tidak")
field(f, R1, y, 360, "Nama profesi lulusan", "—", kind="readonly",
      note="Kosong karena tidak ada tahap profesi")
y += 74

# inline consequence: right under the field that causes it
r(f, PX, y, W_LEFT, 46, fill=ORN_T, radius=6, name="consequence-inline")
r(f, PX, y + 8, 3, 30, fill=ORN, radius=2, name="cons-bar")
t(f, PX + 16, y + 8, "6 tabel tidak berlaku: Tabel 4 · 6 · 7 · 8 · 11 · 30",
  10.5, "#7A3E00", "Bold")
t(f, PX + 16, y + 25, "Target prodi ini 32 tabel wajib, bukan 38 — angka di Ringkasan ikut menyesuaikan.",
  9.5, INK2, width=W_LEFT - 40)
y += 62

field(f, PX, y, 360, "Nama uji kompetensi", "UKOM Bidan")
field(f, R1, y, 360, "Peringkat akreditasi", "B · Baik Sekali", mono=True)
y += 74

field(f, PX, y, 360, "Tanggal akreditasi", "12 Agustus 2022", mono=True)
field(f, R1, y, 360, "Berakhir", "12 Agustus 2027",
      note="Peringatan otomatis H-180 sebelum berakhir")
y += 78

# ══ right rail ══════════════════════════════════════════════════════
RY = 224
r(f, RX, RY, W_RIGHT, 236, fill=SURF, radius=10, stroke=LINE, name="rail-status")
t(f, RX + 20, RY + 18, "Kelengkapan identitas", 12, T1, "Bold")
t(f, RX + 20, RY + 38, "17 dari 18 terkonfirmasi", 10.5, WARN, "Medium")
BW = W_RIGHT - 40
r(f, RX + 20, RY + 58, BW, 6, fill=LINE2, radius=3, name="bar-track")
r(f, RX + 20, RY + 58, round(BW * 17 / 18), 6, fill=WARN, radius=3, name="bar-fill")
r(f, RX + 20, RY + 80, BW, 1, fill=LINE2, name="hr")

rows = [("UPPS", "8/8", OK), ("Program studi", "9/10", WARN),
        ("Konsekuensi tabel", "dihitung", INK3)]
ry = RY + 94
for lab, val, col in rows:
    t(f, RX + 20, ry, lab, 10.5, INK2)
    t(f, RX + 20, ry, val, 10.5, col, "Medium", family=MONO, width=BW, align="right")
    ry += 26

t(f, RX + 20, RY + 182, "Identitas terkunci setelah verifikasi.", 9.5, INK3, width=BW)
t(f, RX + 20, RY + 198, "Perubahan perlu buka kunci oleh Pimpinan.", 9.5, INK3, width=BW)

# rail: where it is used
r(f, RX, RY + 250, W_RIGHT, 200, fill=SURF, radius=10, stroke=LINE, name="rail-used")
t(f, RX + 20, RY + 268, "Dipakai di", 12, T1, "Bold")
uses = [("Export PDF & XLSX", "semua sheet"), ("38 tabel data dukung", "header tabel"),
        ("Ringkasan 8 Kriteria", "judul & cap"), ("Dokumen pendukung", "cover berkas")]
uy = RY + 292
for lab, sub in uses:
    r(f, RX + 20, uy + 4, 5, 5, fill=IND, radius=2, name="dot")
    t(f, RX + 34, uy, lab, 10.5, INK2, "Medium")
    t(f, RX + 34, uy + 15, sub, 9.5, INK3)
    uy += 38

# rail: history
r(f, RX, RY + 464, W_RIGHT, 156, fill=SURF, radius=10, stroke=LINE, name="rail-hist")
t(f, RX + 20, RY + 482, "Riwayat", 12, T1, "Bold")
hist = [("6 Nov 14:20", "Pimpinan mengunci identitas"),
        ("6 Nov 11:05", "Narahubung diperbarui · Admin"),
        ("4 Nov 09:12", "Identitas dibuat · Admin")]
hy = RY + 506
for when, what in hist:
    t(f, RX + 20, hy, when, 9, INK3, family=MONO, width=76)
    t(f, RX + 102, hy, what, 9.5, INK2, width=W_RIGHT - 122)
    hy += 26

# ══ footer note ═════════════════════════════════════════════════════
t(f, PX, y + 110,
  "Field bertanda terverifikasi diambil dari master perguruan tinggi; mengubahnya memengaruhi semua prodi di UPPS ini.",
  9.5, INK3, width=W_LEFT)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Identitas UPPS & Prodi")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/id.png")
        print("rendered /tmp/id.png")
