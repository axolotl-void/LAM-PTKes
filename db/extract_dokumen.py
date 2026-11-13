"""Tarik 78 dokumen pendukung dari xlsx instrumen.

Struktur nyata sheet `Daftar Dokumen Pendukung`:
  baris 1  judul
  baris 2  kosong
  baris 3  header kolom: Kriteria | No. | Dokumen Pendukung
  baris 4+ data: kolom A di-merge per kelompok kriteria (hanya terisi di baris
  pertama kelompok), kolom B nomor butir, kolom C nama dokumen.

PENTING: satu sel kolom C bisa memuat lebih dari satu dokumen dipisah baris
baru. Itu BUKAN butir tambahan — itu rincian di dalam satu butir. Catatan lama
pernah menghitung 81 dan 79 karena mengira sebaliknya; angka yang benar 78 dan
sudah diverifikasi 9 Nov. Jadi jangan dipecah.
"""
import json
import re
from collections import Counter

from openpyxl import load_workbook

SRC = "/Volumes/SSD GIK/07-ARSIP/LAM-PTKES/DATA DUKUNG KEBIDANAN.xlsx"
OUT = "/Users/yogiprasetyasadewa/Documents/03_Proyek/LAM-PTKES/db/dokumen-spec.json"

wb = load_workbook(SRC, data_only=True)
ws = wb["Daftar Dokumen Pendukung"]

butir = []
kriteria = None
nama_kriteria = None
for nomor_baris, row in enumerate(ws.iter_rows(min_row=4, values_only=True), start=4):
    sel_krit, sel_no, sel_dok = (list(row) + [None, None, None])[:3]

    if sel_krit:
        teks = str(sel_krit).strip()
        m = re.match(r"Kriteria\s+(\d+)\.?\s*(.*)", teks, re.I)
        if m:
            kriteria = m.group(1)
            nama_kriteria = m.group(2).strip()

    if sel_no is None or sel_dok is None:
        continue
    if not str(sel_dok).strip():
        continue

    butir.append({
        "kriteria": kriteria,
        "nama_kriteria": nama_kriteria,
        "nomor": int(str(sel_no).strip()),
        "nama": re.sub(r"\s+", " ", str(sel_dok).strip()),
        "rincian": [p.strip() for p in str(sel_dok).split("\n") if p.strip()],
    })

per = Counter(b["kriteria"] for b in butir)
print("total butir:", len(butir))
print("per kriteria:", dict(sorted(per.items())))

harapan = {"1": 4, "2": 9, "3": 8, "4": 11, "5": 15, "6": 14, "7": 9, "8": 8}
assert len(butir) == 78, f"harus 78 butir, dapat {len(butir)}"
assert dict(sorted(per.items())) == harapan, f"sebaran kriteria salah: {dict(per)}"

json.dump(butir, open(OUT, "w"), ensure_ascii=False, indent=1)
print("tulis", OUT)

# cek nomor tidak bolong dalam tiap kriteria
for k in sorted(harapan):
    nomor = [b["nomor"] for b in butir if b["kriteria"] == k]
    assert nomor == list(range(1, harapan[k] + 1)), f"K{k} nomor tidak urut: {nomor}"
print("nomor butir urut 1..n di setiap kriteria: ok")
