# Rules — generator desain

Script Python yang menggambar tiap layar ke FigCraft lewat MCP.

## Prasyarat

1. FigCraft.app terbuka dengan control channel:

   ```
   /Applications/FigCraft.app/Contents/MacOS/FigCraft --control 7979
   ```

2. `mockup.figcraft` terbuka di app itu.

## Menjalankan

```bash
python3 keuangan.py     # menggambar ulang layar 11
```

## File

| File | Layar |
|---|---|
| `dash_v3.py` | 01 Dashboard |
| `ringkasan8_v2.py` | 05 Ringkasan 8 Kriteria |
| `identitas.py` | 08 Identitas UPPS & Prodi |
| `periode.py` | 09 Periode Akreditasi |
| `dosen.py` | 10 Dosen & Tendik |
| `keuangan.py` | 11 Keuangan |
| `kerjasama.py` | 12 Kerja Sama |
| `kit.py` | helper gambar (topbar, sidebar, chip) |
| `icons.py` | 18 ikon SVG stroke |
| `live_client.py` | klien socket ke daemon MCP |

## Prinsip

Tiap script menurunkan semua angka dari satu tabel data di kepala file, lalu `assert`
memastikan total tutup (38 tabel, 79 dokumen, 100 persen). Kalau assert gagal, script
berhenti sebelum menggambar — supaya angka palsu tidak pernah masuk desain.

Kalau mau ubah desain, ubah **data dulu**, bukan koordinat.
