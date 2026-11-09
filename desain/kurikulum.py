"""14 · Kurikulum & MK (F-07) — Tabel 1.

239 baris. Halaman ini tidak boleh berbentuk tabel isian — tidak ada manusia yang
mengetik 239 baris MK satu per satu. Jadi bentuknya: ringkasan kurikulum +
status impor + pemeriksaan integritas, dengan daftar MK sebagai pratinjau.

Yang menentukan lulus-tidaknya Tabel 1: tiap MK harus punya CPL, sks, aspek
penilaian, RPS, dan lokasi praktik. Jadi halamannya memeriksa itu, bukan
menampilkan kolom.
"""
from kit import Kit, IND, IND_T, ORN, ORN_T, BG, SURF, BD, DV, T1, T2, T3, UI, MONO
from live_client import L

FRAME_X = 20000
PX, W = 280, 1128

INK2, INK3 = "#475467", "#667085"
LINE, LINE2 = "#E4E7EC", "#F2F4F7"
OK, WARN, BAD, LOCK = "#067647", "#B54708", "#B42318", "#175CD3"
OK_BG, WARN_BG, BAD_BG, LOCK_BG = "#ECFDF3", "#FFFAEB", "#FEF3F2", "#EFF8FF"

# ── source of truth: kurikulum D-III Kebidanan ───────────────────────
# kode, nama, sks_kuliah, sks_praktik, tahap, cpl, aspek(4 bool), rps, lokasi
MK = [
    ("KB.101", "Anatomi & Fisiologi", 2, 1, "Akademik", True, (1, 1, 0, 1), True, "Lab"),
    ("KB.102", "Asuhan Kebidanan Kehamilan", 2, 2, "Akademik", True, (1, 1, 1, 1), True, "Lab · RB"),
    ("KB.103", "Asuhan Kebidanan Persalinan", 2, 3, "Akademik", True, (1, 1, 1, 1), True, "RB · BPM"),
    ("KB.104", "Asuhan Kebidanan Nifas & Menyusui", 2, 2, "Akademik", True, (1, 1, 1, 0), True, "RB"),
    ("KB.105", "Asuhan Neonatus & Bayi", 2, 2, "Akademik", True, (1, 1, 1, 1), True, "RS · RB"),
    ("KB.106", "Kesehatan Reproduksi", 2, 0, "Akademik", True, (1, 1, 0, 0), True, "—"),
    ("KB.107", "Keluarga Berencana", 1, 1, "Akademik", True, (1, 1, 1, 0), False, "BPM"),
    ("KB.108", "Deteksi Dini Komplikasi", 2, 1, "Akademik", False, (1, 0, 1, 0), True, "RS"),
    ("KB.109", "Etika & Hukum Kebidanan", 2, 0, "Akademik", True, (1, 1, 0, 0), True, "—"),
    ("KB.110", "Komunikasi Terapeutik", 2, 0, "Akademik", True, (1, 1, 1, 0), True, "—"),
    ("KB.201", "Praktik Klinik ANC", 0, 3, "Profesi", True, (1, 1, 1, 1), True, "RB · BPM"),
    ("KB.202", "Praktik Klinik Persalinan", 0, 4, "Profesi", True, (1, 1, 1, 1), True, "RB"),
    ("KB.203", "Praktik Komunitas", 0, 2, "Profesi", True, (1, 1, 1, 0), True, "Posyandu"),
]

TOTAL_MK = 239              # angka sebenarnya di instrumen
SAMPLE = len(MK)
SHOW = 11                   # yang benar-benar muat di kartu pratinjau

SKS_K = sum(m[2] for m in MK)
SKS_P = sum(m[3] for m in MK)
AKADEMIK = sum(1 for m in MK if m[4] == "Akademik")
PROFESI = sum(1 for m in MK if m[4] == "Profesi")

tanpa_cpl = [m for m in MK if not m[5]]
tanpa_rps = [m for m in MK if not m[7]]
tanpa_lokasi = [m for m in MK if m[3] > 0 and m[8] == "—"]
tanpa_aspek = [m for m in MK if sum(m[6]) == 0]

k = Kit()
r, t = k.rect, k.txt
k.ops.append({"command": "frame.create", "as": "f",
              "params": {"x": FRAME_X, "y": 0, "width": 1440, "height": 1200,
                         "name": "14 · Kurikulum & MK", "props": {"fill": BG, "clip": True}}})
f = {"$ref": "f"}
k.topbar(f)
k.sidebar(f, aktif="Dashboard")

# ══ header ═══════════════════════════════════════════════════════════
t(f, PX, 82, "Kurikulum & MK", 24, T1, "Bold", name="page-title")
t(f, PX, 110,
  f"F-07 · Tabel 1 · {TOTAL_MK} baris di instrumen · wajib impor, bukan ketik", 11, INK3)

r(f, 1180, 82, 108, 34, fill=SURF, radius=6, stroke=LINE, name="btn-tpl")
t(f, 1180, 91, "Template", 10.5, INK2, "Medium", width=108, align="center")
r(f, 1296, 82, 112, 34, fill=IND, radius=6, name="btn-import")
t(f, 1296, 91, "Impor Excel", 11, "#FFFFFF", "Bold", width=112, align="center")

# ══ import state: the page's primary object ═════════════════════════
GY = 148
r(f, PX, GY, W, 96, fill=WARN_BG, radius=10, name="import-state")
r(f, PX, GY + 14, 3, 68, fill=WARN, radius=2, name="state-bar")
t(f, PX + 20, GY + 14, "Kurikulum terakhir diimpor 4 Nov 2026", 12.5, "#7A3E00", "Bold")
t(f, PX + 20, GY + 34,
  f"13 baris contoh dari {TOTAL_MK} baris Tabel 1 ada di sistem. Sisa 226 baris belum masuk — "
  "selama belum, Tabel 1 tidak bisa diekspor.", 10, INK2, width=680)

# three integrity counters, inline
ix = PX + 20
for label, val, col, ok in [
        ("CPL kosong", len(tanpa_cpl), BAD, False),
        ("RPS belum ada", len(tanpa_rps), WARN, False),
        ("Lokasi praktik kosong", len(tanpa_lokasi), WARN, False)]:
    t(f, ix, GY + 62, str(val), 14, col, "Bold", family=MONO)
    t(f, ix + 24, GY + 66, label, 9.5, INK3)
    ix += 176

r(f, PX + W - 24 - 150, GY + 32, 150, 32, fill=SURF, radius=6, stroke=WARN,
  name="btn-resume")
t(f, PX + W - 24 - 150, GY + 41, "Lanjutkan impor", 10.5, "#7A3E00", "Medium",
  width=150, align="center")

# ══ structure strip ═════════════════════════════════════════════════
SY = 262
r(f, PX, SY, W, 84, fill=SURF, radius=10, stroke=LINE, name="struct")
cells = [(str(AKADEMIK), "MK akademik", INK2), (str(PROFESI), "MK profesi", IND),
         (str(SKS_K), "sks kuliah", INK2), (str(SKS_P), "sks praktik", INK2),
         (str(SKS_K + SKS_P), "total sks contoh", T1),
         (f"{TOTAL_MK}", "baris instrumen", INK3)]
sx = PX + 24
for i, (val, lab, col) in enumerate(cells):
    t(f, sx, SY + 18, val, 20, col, "Bold", family=MONO)
    t(f, sx, SY + 46, lab, 9.5, INK3)
    if i < len(cells) - 1:
        r(f, sx + 168, SY + 16, 1, 48, fill=LINE2, name="vr")
    sx += 184

# ══ MK preview table ════════════════════════════════════════════════
TY = 366
r(f, PX, TY, W, 470, fill=SURF, radius=10, stroke=LINE, name="mk-table")
t(f, PX + 24, TY + 18, "Pratinjau MK", 12.5, T1, "Bold")
t(f, PX + 140, TY + 20, f"{SAMPLE} baris contoh · {SHOW} ditampilkan", 10, INK3)

CX = {"kode": 24, "nama": 110, "sks": 400, "aspek": 470, "tahap": 640,
      "rps": 740, "lokasi": 810}
r(f, PX + 24, TY + 44, W - 48, 1, fill=LINE, name="hr")
for lab, key, w, al in [("Kode", "kode", 80, "left"), ("Mata kuliah", "nama", 280, "left"),
                        ("sks kuliah/praktik", "sks", 60, "right"),
                        ("Aspek penilaian", "aspek", 160, "left"),
                        ("Tahap", "tahap", 90, "left"), ("RPS", "rps", 60, "center"),
                        ("Lokasi", "lokasi", 120, "left")]:
    t(f, PX + CX[key], TY + 54, lab, 9.5, INK3, "Medium", width=w, align=al)
r(f, PX + 24, TY + 72, W - 48, 1, fill=LINE, name="hr")

ASPEK_SHORT = ["Sikap", "Pengeth", "Ket. umum", "Ket. khusus"]
ASPEK_LETTER = ["S", "P", "U", "K"]

ry = TY + 78
for i, (kode, nama, sk, sp, tahap, cpl, aspek, rps, lokasi) in enumerate(MK[:SHOW]):
    rh = 28
    if i % 2 == 1:
        r(f, PX + 25, ry, W - 50, rh, fill="#FCFCFD", name="zebra")

    t(f, PX + CX["kode"], ry + 9, kode, 9.5, INK3, family=MONO, width=80)
    t(f, PX + CX["nama"], ry + 8, nama, 10.5, T1, width=280)
    if not cpl:
        r(f, PX + CX["nama"] - 8, ry + 14, 3, 8, fill=BAD, radius=1, name="no-cpl-bar")

    # sks: kuliah/praktik distinguished, because Tabel 1 has separate columns
    t(f, PX + CX["sks"], ry + 7, str(sk), 10, INK2 if sk else INK3, "Medium",
      family=MONO, width=26, align="right")
    t(f, PX + CX["sks"] + 26, ry + 7, "/", 10, INK3, width=10, align="center")
    t(f, PX + CX["sks"] + 34, ry + 7, str(sp), 10, INK2 if sp else INK3, "Medium",
      family=MONO, width=26, align="right")

    # aspek: four letters, filled = counted. Compact, still printable.
    ax = PX + CX["aspek"]
    for j, on in enumerate(aspek):
        r(f, ax, ry + 9, 20, 16, fill=IND if on else LINE2, radius=3,
          name=f"asp {ASPEK_LETTER[j]}")
        t(f, ax, ry + 11, ASPEK_LETTER[j], 8.5,
          "#FFFFFF" if on else INK3, "Bold", width=20, align="center")
        ax += 23

    t(f, PX + CX["tahap"], ry + 9, tahap, 9.5, INK2, width=90)

    if rps:
        t(f, PX + CX["rps"], ry + 9, "✓", 10, OK, "Bold", width=60, align="center")
    else:
        t(f, PX + CX["rps"], ry + 9, "—", 10, WARN, "Bold", width=60, align="center")

    t(f, PX + CX["lokasi"], ry + 9, lokasi, 9.5,
      WARN if lokasi == "—" and sp > 0 else INK3, width=120)

    r(f, PX + 24, ry + rh, W - 48, 1, fill=LINE2, name="row-hr")
    ry += rh

t(f, PX + 24, ry + 10,
  f"Menampilkan {SHOW} dari {SAMPLE} baris contoh · {TOTAL_MK - SAMPLE} baris "
  "instrumen belum diimpor sama sekali", 9.5, INK3)
t(f, PX + W - 24 - 130, ry + 10, f"Lihat semua {TOTAL_MK} →", 10, IND, "Medium",
  width=130, align="right")

# legend for the aspek letters — needed, otherwise S/P/U/K is a secret code
ly = ry + 34
lx = PX + 24
for letter, full in zip(ASPEK_LETTER, ASPEK_SHORT):
    r(f, lx, ly + 2, 16, 14, fill=IND, radius=3, name="leg")
    t(f, lx, ly + 4, letter, 8.5, "#FFFFFF", "Bold", width=16, align="center")
    t(f, lx + 22, ly + 4, full, 9, INK3, width=90)
    lx += 118

# ══ integrity checks ════════════════════════════════════════════════
BY = 862
r(f, PX, BY, 660, 240, fill=SURF, radius=10, stroke=LINE, name="integrity")
t(f, PX + 24, BY + 18, "Pemeriksaan integritas", 12, T1, "Bold")
t(f, PX + 24, BY + 36, "yang membuat Tabel 1 ditolak instrumen", 9.5, INK3)

checks = [
    ("Setiap MK punya CPL", len(MK) - len(tanpa_cpl), len(MK), BAD if tanpa_cpl else OK,
     ", ".join(m[0] for m in tanpa_cpl) or "lengkap"),
    ("Setiap MK punya RPS", len(MK) - len(tanpa_rps), len(MK),
     WARN if tanpa_rps else OK, ", ".join(m[0] for m in tanpa_rps) or "lengkap"),
    ("MK praktik punya lokasi", len([m for m in MK if m[3] > 0]) - len(tanpa_lokasi),
     len([m for m in MK if m[3] > 0]), WARN if tanpa_lokasi else OK,
     ", ".join(m[0] for m in tanpa_lokasi) or "lengkap"),
    ("Setiap MK punya aspek penilaian", len(MK) - len(tanpa_aspek), len(MK),
     OK, "lengkap"),
    ("sks kuliah + praktik ≤ 6 per MK", len(MK), len(MK), OK, "lengkap"),
]
cy = BY + 56
for label, done, tot, col, detail in checks:
    t(f, PX + 24, cy, label, 10, INK2, width=300)
    t(f, PX + 330, cy, f"{done}/{tot}", 10, col, "Medium", family=MONO, width=50,
      align="right")
    r(f, PX + 388, cy + 4, 120, 5, fill=LINE2, radius=3, name="chk-bg")
    r(f, PX + 388, cy + 4, max(round(120 * done / tot), 2), 5, fill=col, radius=3,
      name="chk")
    t(f, PX + 524, cy, detail, 9, INK3, width=134)
    cy += 28

# ══ right: kenapa impor, bukan ketik ════════════════════════════════
DX = PX + 684
r(f, DX, BY, 444, 240, fill=SURF, radius=10, stroke=LINE, name="why")
t(f, DX + 24, BY + 18, "Kenapa impor, bukan form", 12, T1, "Bold")

reasons = [
    (f"{TOTAL_MK} baris", "mengetik satu per satu butuh ±4 jam dan rawan salah"),
    ("13 kolom", "MK, CPL, sks kuliah, sks praktik, 4 aspek, RPS, lokasi, tahap"),
    ("1 baris gagal = 1 baris ditolak", "tidak ada impor sebagian yang diam-diam"),
    ("Sumber asli", "prodi sudah punya Excel kurikulum; jangan minta ketik ulang"),
]
ry2 = BY + 46
for head, sub in reasons:
    r(f, DX + 24, ry2 + 5, 5, 5, fill=IND, radius=2, name="dot")
    t(f, DX + 38, ry2, head, 10.5, INK2, "Medium", width=370)
    t(f, DX + 38, ry2 + 15, sub, 9.5, INK3, width=370)
    ry2 += 46

# ══ footer ══════════════════════════════════════════════════════════
t(f, PX, 1122,
  "Tabel 1 punya 239 baris dan penanda (a)–(g) yang wajib tercetak. "
  "Format ekspor mengikuti template instrumen, bukan tabel bebas.", 9.5, INK3, width=W)

if __name__ == "__main__":
    res = L.batch(k.ops, label="Kurikulum & MK")
    bad = [x for x in res if isinstance(x, dict) and not x.get("ok", True)]
    print(f"drew {len(k.ops)}, failed {len(bad)}")
    for b in bad[:3]:
        print("  ", str(b)[:170])
    fid = [x["result"]["id"] for x in res if isinstance(x, dict)
           and x.get("command") == "frame.create" and x.get("ok")]
    print("frame id:", fid)
    if fid:
        L.call("export", format="png", scale=2, ids=fid, path="/tmp/mk.png")
        print("rendered")
