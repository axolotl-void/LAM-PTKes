"""Ambil judul asli 38 tabel dari berkas Excel instrumen.

KENAPA: `tabel_dukung.judul` diisi "01. Tabel 01" oleh generator — nama aslinya
tidak ada di field-spec.json, hanya ada di baris 1 tiap sheet Excel. Akibatnya
halaman, ekspor XLSX, dan PDF menampilkan "Tabel 01", "Tabel 02", ... yang
tidak memberi tahu apa pun kepada asesor.

Jalankan ulang kalau berkas instrumen berubah:
    python3 db/ambil_judul.py "/path/DATA DUKUNG.xlsx"
Hasil: db/judul-tabel.json (dipakai gen_skema_tabel.py)
"""

import json
import sys
import openpyxl

default = ('/Users/yogiprasetyasadewa/.hermes/cache/documents/'
           'doc_e2b52368e9a1_DATA DUKUNG KEBIDANAN.xlsx')
path = sys.argv[1] if len(sys.argv) > 1 else default

wb = openpyxl.load_workbook(path, data_only=True)
judul = {}

# Sebagian tabel punya beberapa sheet varian (Tabel 5: 4th/5th/6th/~ untuk
# program Spesialis 4/5/6 tahun dan Sarjana). Untuk prodi Sarjana dipakai
# varian '(~)' — itu yang sesuai jenjang S1. Varian lain tidak ditampilkan
# karena satu prodi hanya menjalani satu masa studi.
PILIH_VARIAN = {'05': '05 (~)'}

for i in range(1, 39):
    kode = f"{i:02d}"
    sheet = PILIH_VARIAN.get(kode, kode)
    if sheet not in wb.sheetnames:
        continue
    ws = wb[sheet]
    for r in range(1, 5):
        v = ws.cell(r, 1).value
        if v and str(v).strip().lower().startswith("tabel"):
            # Buang nomor di depan: "Tabel 22. Data Kegiatan..." -> "Data Kegiatan..."
            teks = " ".join(str(v).split())
            teks = teks.replace("Tabel 18a & 18b", "Tabel 18")
            teks = teks.replace("Tabel 37", "Tabel 37")
            parts = teks.split(".", 1)
            if len(parts) == 2 and parts[0].lower().startswith("tabel"):
                teks = parts[1].strip()
            judul[kode] = teks
            break

print(f"{len(judul)} judul terbaca")
for k in ["01", "15", "22", "38"]:
    print(" ", k, "->", judul.get(k, "?")[:72])

# Berkas keluaran ditaruh di folder yang sama dengan skrip ini.
out = __file__.rsplit("/", 1)[0] + "/judul-tabel.json"
open(out, "w").write(json.dumps(judul, ensure_ascii=False, indent=1))
print("tulis:", out)
