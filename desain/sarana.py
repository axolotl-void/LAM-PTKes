"""16 · Sarana & Wahana (F-15) — Tabel 15, 28, 30, 36.

Satu-satunya halaman yang disilang dua aturan sekaligus:
1. Wahana praktik (Tabel 30) butuh MoU berlaku — nyambung ke halaman 12 Kerja Sama.
2. Sarana umum (Tabel 15) menuntut lokasi unik — satu ruangan tidak boleh dihitung
   dua kali oleh dua tabel.

Jadi halaman ini tiga seksi: sarana umum, wahana praktik, dan keselamatan — dengan
banner yang mengingatkan dua aturan silang itu sebelum tabel.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 23120
PX, W = 280, 1128

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# ── sumber kebenaran: wahana praktik (Tabel 30) ─────────────────────
# nama, jenis, lokasi, kapasitas, MoU (bool), tabel pemakai
WAHANA = [
    ("RSUD Zainoel Abidin", "Rumah Sakit", "Banda Aceh", 40, True),
    ("RSIA Banda Aceh", "Rumah Sakit", "Banda Aceh", 25, True),
    ("RSJ Banda Aceh", "Rumah Sakit", "Banda Aceh", 15, False),
    ("Puskesmas Kuta Alam", "Puskesmas", "Banda Aceh", 12, True),
    ("Puskesmas Ulee Kareng", "Puskesmas", "Banda Aceh", 10, True),
    ("BPM Cut Nyak Dhien", "BPM", "Banda Aceh", 6, True),
    ("PMB Nurhasanah", "BPM", "Banda Aceh", 5, True),
]

# sarana umum (Tabel 15) — jumlah, kondisi, lokasi
SARANA = [
    ("Ruang kelas", 8, "baik", "Kampus A"),
    ("Laboratorium", 4, "baik", "Kampus A"),
    ("Ruang simulasi", 2, "baik", "Kampus A"),
    ("Perpustakaan", 1, "baik", "Kampus A"),
    ("Ruang praktik komunitas", 3, "sebagian", "Kampus A · luar"),
]

MOu_VALID = sum(1 for w in WAHANA if w[4])
MOu_EXPIRED = sum(1 for w in WAHANA if not w[4])
TOTAL_KAP = sum(w[3] for w in WAHANA if w[4])   # only valid ones count
KAP_DITOLAK = sum(w[3] for w in WAHANA if not w[4])

KELAS_OK = sum(s[1] for s in SARANA if s[2] == "baik")
KELAS_TOTAL = sum(s[1] for s in SARANA)
SEBAGIAN = sum(1 for s in SARANA if s[2] == "sebagian")
assert SEBAGIAN == 1, SEBAGIAN
assert len(WAHANA) == MOu_VALID + MOu_EXPIRED

# ── keselamatan (Tabel 36) ──────────────────────────────────────────
KESELAMATAN = [
    ("APAR", "12", "berlaku", True),
    ("Jalur evakuasi", "—", "terpasang", True),
    ("Kotak P3K", "5", "berlaku", True),
    ("Sertifikat kelayakan gedung", "—", "kedaluwarsa 2024", False),
]

k = Kit()
r, t = k.rect, k.txt
k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1200,
                         "name": "16 · Sarana & Wahana",
                         "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Sarana")

# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Sarana & Wahana", 24, T1, "Bold", name="page-title")
t(f, PX, 110,
  f"F-15 · Tabel 15 · 28 · 30 · 36 · {len(WAHANA)} wahana praktik · "
  f"{len(SARANA)} jenis sarana umum", 11, INK3)

r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-add")
t(f, 1296, 91, "+ Sarana", 11, "#FFFFFF", "Bold", width=112, align="center")

# ══ dua aturan silang — alasan halaman ini sulit ═════════════════════
GY = 148
r(f, PX, GY, W, 88, fill=WARN_BG, radius=10, name="cross")
r(f, PX, GY + 14, 3, 60, fill=WARN, radius=2, name="cross-bar")
t(f, PX + 20, GY + 14, "Dua aturan yang menyilang halaman ini", 12.5, "#7A3E00",
  "Bold")

# rule 1
t(f, PX + 20, GY + 40, "1", 10, WARN, "Bold", family=MONO, width=16)
t(f, PX + 44, GY + 38,
  f"{MOu_EXPIRED} wahana tanpa MoU berlaku (RSJ Banda Aceh) — Tabel 30 menolaknya.",
  10, INK2, width=540)
r(f, PX + 620, GY + 40, 110, 22, fill=SURF, radius=5, stroke=WARN, name="go-mou")
t(f, PX + 620, GY + 44, "Periksa MoU →", 9, "#7A3E00", "Medium", width=110,
  align="center")

# rule 2
t(f, PX + 20, GY + 62, "2", 10, WARN, "Bold", family=MONO, width=16)
t(f, PX + 44, GY + 60,
  "Satu ruangan tidak boleh dihitung dua kali oleh Tabel 15 dan Tabel 30.",
  10, INK2, width=540)

# ══ strip ═══════════════════════════════════════════════════════════
SY = 254
r(f, PX, SY, W, 78, fill=SURF, radius=10, stroke=LINE, name="strip")
cells = [(str(len(WAHANA)), "wahana praktik", INK2),
         (str(MOu_VALID), "MoU berlaku", OK),
         (str(MOu_EXPIRED), "MoU kedaluwarsa", BAD),
         (str(TOTAL_KAP), "kapasitas dihitung", INK2),
         (f"{KELAS_OK}/{KELAS_TOTAL}", "ruang kondisi baik", INK2)]
sx = PX + 24
for i, (val, lab, col) in enumerate(cells):
    t(f, sx, SY + 16, val, 20, col, "Bold", family=MONO)
    t(f, sx, SY + 44, lab, 9.5, INK3)
    if i < len(cells) - 1:
        r(f, sx + 208, SY + 14, 1, 46, fill=LINE2, name="vr")
    sx += 222

# ══ wahana praktik (Tabel 30) — yang paling kritis ══════════════════
WY = 350
r(f, PX, WY, W, 318, fill=SURF, radius=10, stroke=LINE, name="wahana")
t(f, PX + 24, WY + 18, "Wahana praktik", 12.5, T1, "Bold")
t(f, PX + 180, WY + 20, "Tabel 30 · MoU berlaku adalah syarat", 10, INK3)

CX = {"nama": 24, "jenis": 300, "lokasi": 460, "kap": 600, "mou": 690}
r(f, PX + 24, WY + 44, W - 48, 1, fill=LINE, name="hr")
for lab, key, w, al in [("Wahana", "nama", 260, "left"), ("Jenis", "jenis", 150, "left"),
                        ("Lokasi", "lokasi", 130, "left"), ("Kap.", "kap", 80, "right"),
                        ("MoU (Tabel 30)", "mou", 300, "left")]:
    t(f, PX + CX[key], WY + 54, lab, 9.5, INK3, "Medium", width=w, align=al)
r(f, PX + 24, WY + 72, W - 48, 1, fill=LINE, name="hr")

wy = WY + 78
for i, (nama, jenis, lokasi, kap, mou) in enumerate(WAHANA):
    rh = 32
    if not mou:
        r(f, PX + 25, wy, W - 50, rh, fill=BAD_BG, name="row-bad")
    elif i % 2 == 1:
        r(f, PX + 25, wy, W - 50, rh, fill="#FCFCFD", name="zebra")

    t(f, PX + CX["nama"], wy + 8, nama, 10.5, T1, "Medium", width=260)
    t(f, PX + CX["jenis"], wy + 8, jenis, 9.5, INK2, width=150)
    t(f, PX + CX["lokasi"], wy + 8, lokasi, 9, INK3, width=130)

    # capacity that does not count is struck through, not silently summed
    kap_col = INK2 if mou else "#98A2B3"
    t(f, PX + CX["kap"], wy + 7, str(kap), 10, kap_col, "Medium", family=MONO,
      width=80, align="right")
    if not mou:
        r(f, PX + CX["kap"], wy + 14, 30, 1, fill="#98A2B3", name="strike")

    if mou:
        t(f, PX + CX["mou"], wy + 8, "✓ berlaku", 9.5, OK, "Medium", width=140)
    else:
        t(f, PX + CX["mou"], wy + 8, "kedaluwarsa · tidak dihitung", 9, BAD,
          "Medium", width=180)

    r(f, PX + 24, wy + rh, W - 48, 1, fill=LINE2, name="row-hr")
    wy += rh

t(f, PX + 24, wy + 8,
  f"Kapasitas dihitung {TOTAL_KAP} dari {TOTAL_KAP + KAP_DITOLAK} — {KAP_DITOLAK} "
  "milik RSJ tidak masuk sampai MoU diperbarui.", 9, INK3, width=W - 48)

# ══ sarana umum (Tabel 15) ══════════════════════════════════════════
SY2 = 692
r(f, PX, SY2, W, 232, fill=SURF, radius=10, stroke=LINE, name="sarana")
t(f, PX + 24, SY2 + 18, "Sarana umum", 12.5, T1, "Bold")
t(f, PX + 160, SY2 + 20,
  f"Tabel 15 · {sum(s[1] for s in SARANA if s[2] == 'sebagian')} unit kondisi "
  "'sebagian' perlu diperbaiki", 10, INK3)

CX2 = {"nama": 24, "jumlah": 320, "kondisi": 470, "lokasi": 640}
r(f, PX + 24, SY2 + 44, W - 48, 1, fill=LINE, name="hr")
for lab, key, w in [("Jenis sarana", "nama", 280), ("Jumlah", "jumlah", 130),
                    ("Kondisi", "kondisi", 160), ("Lokasi", "lokasi", 250)]:
    t(f, PX + CX2[key], SY2 + 54, lab, 9.5, INK3, "Medium", width=w)
r(f, PX + 24, SY2 + 72, W - 48, 1, fill=LINE, name="hr")

sy = SY2 + 78
for i, (nama, jumlah, kondisi, lokasi) in enumerate(SARANA):
    rh = 30
    if kondisi == "sebagian":
        r(f, PX + 25, sy, W - 50, rh, fill=WARN_BG, name="row-warn")

    t(f, PX + CX2["nama"], sy + 6, nama, 10.5, T1, width=280)
    t(f, PX + CX2["jumlah"], sy + 6, str(jumlah), 10, INK2, "Medium", family=MONO,
      width=130, align="right")
    col = OK if kondisi == "baik" else WARN
    bg = OK_BG if kondisi == "baik" else WARN_BG
    r(f, PX + CX2["kondisi"], sy + 7, 72, 18, fill=bg, radius=4, name="kond")
    t(f, PX + CX2["kondisi"], sy + 10, kondisi, 8.5, col, "Medium", width=72,
      align="center")
    t(f, PX + CX2["lokasi"], sy + 6, lokasi, 9.5, INK3, width=250)

    r(f, PX + 24, sy + rh, W - 48, 1, fill=LINE2, name="row-hr")
    sy += rh

# ══ keselamatan (Tabel 36) ══════════════════════════════════════════
KY = 948
r(f, PX, KY, 660, 150, fill=SURF, radius=10, stroke=LINE, name="keselamatan")
t(f, PX + 24, KY + 18, "Keselamatan & kesehatan kerja", 12, T1, "Bold")
t(f, PX + 300, KY + 20, "Tabel 36", 10, INK3)

ky = KY + 46
for nama, jumlah, status, ok in KESELAMATAN:
    t(f, PX + 24, ky, nama, 10, INK2, width=220)
    t(f, PX + 250, ky, jumlah, 10, INK2, family=MONO, width=60)
    col = OK if ok else BAD
    t(f, PX + 330, ky, status, 9.5, col, "Medium", width=300)
    ky += 24

# ══ kanan: hubungan lintas tabel ═════════════════════════════════════
DX = PX + 684
r(f, DX, KY, 444, 150, fill=SURF, radius=10, stroke=LINE, name="link")
t(f, DX + 24, KY + 18, "Hubungan antar halaman", 12, T1, "Bold")

links = [
    ("Tabel 30 → Kerja Sama", "MoU di cek di F-19, bukan di sini"),
    ("Tabel 15 → Keuangan", "pengadaan sarana masuk F-17"),
    ("Tabel 28 → Kurikulum & MK", "rasio mahasiswa:sarana"),
]
ly = KY + 46
for head, sub in links:
    r(f, DX + 24, ly + 5, 5, 5, fill=IND, radius=2, name="dot")
    t(f, DX + 38, ly, head, 10, INK2, "Medium", width=380)
    t(f, DX + 38, ly + 15, sub, 9.5, INK3, width=380)
    ly += 34

# ══ footer ══════════════════════════════════════════════════════════
t(f, PX, 1118,
  "Wahana dan sarana dipisah karena aturannya beda: wahana butuh MoU, sarana butuh "
  "lokasi unik. Jangan gabung jadi satu tabel.", 9.5, INK3, width=W)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Sarana & Wahana")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/sarana.png")
        print("rendered")
