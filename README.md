# LAM-PTKes — Sistem Akreditasi Prodi Kesehatan

Aplikasi akreditasi **LAM-PTKes** untuk Program Studi Kebidanan & Keperawatan,
Universitas Bina Bangsa Getsempena (UBBG). Instrumen: 38 tabel data dukung,
8 kriteria, 78 butir dokumen pendukung.

Status: **prototipe fungsional.** Tampilan selesai, pengisian bisa disimulasikan
lewat SQLite, tapi **data yang terisi masih contoh** — bukan data kampus.

## Isi repo

| Folder | Isi |
|---|---|
| `app/` | Aplikasi jalan: HTML + CSS + JS, tanpa build step, tanpa framework |
| `app/db.py` | Skema SQLite + aturan (status turunan, kunci, pemisahan tugas) |
| `app/serve.py` | Server statis + API JSON |
| `desain/` | Script generator mockup FigCraft (Python) + PNG hasil ekspor |
| `handoff/` | Paket serah-terima untuk pengembang backend: dokumen kontrak data, PNG layar, file `.figcraft` |

## Menjalankan

```bash
cd app
python3 serve.py 8123
```

Buka **http://127.0.0.1:8123**

Tidak ada dependency di luar Python 3 stdlib (`sqlite3` sudah termasuk).
Database `lamptkes.db` dibuat otomatis saat server pertama kali jalan.

### Perintah lain

```bash
python3 db.py              # lihat isi DB + ringkasan
python3 db.py reset        # kembali ke kondisi awal
python3 simulate.py        # isi tabel seperti petugas bekerja
python3 test_db.py         # 6 uji aturan DB
node --test test/data.test.js   # 10 uji data & render
```

## API

Semua JSON, tanpa autentikasi (belum ada sesi).

```
GET  /api/ringkasan              angka turunan untuk dashboard
GET  /api/tabel                  semua 38 tabel + status
POST /api/tabel/<kode>/isi       {"terisi": 6, "oleh": "nama"}
POST /api/tabel/<kode>/kunci     {"oleh": "nama"}
POST /api/tabel/<kode>/buka      {}
GET  /api/pengguna               daftar akun + ringkasan
POST /api/pengguna/<id>/peran    {"peran": "...", "status": "..."}
POST /api/pengguna/<id>/pisah    {}
GET  /api/riwayat?batas=50       log perubahan
POST /api/reset                  kembalikan ke kondisi awal
```

## Aturan yang dijaga, bukan cuma ditampilkan

**Status tabel diturunkan, tidak disimpan.** Kolom `field_terisi` / `field_total`
satu-satunya sumber; status dihitung SQL. Jadi mustahil ada baris "lengkap"
dengan 3 dari 6 field terisi.

```sql
CASE WHEN berlaku = 0                    THEN 'na'
     WHEN dikunci_oleh IS NOT NULL       THEN 'terkunci'
     WHEN field_terisi >= field_total    THEN 'lengkap'
     WHEN field_terisi = 0               THEN 'kosong'
     ELSE 'sebagian' END
```

Plus `CHECK (field_terisi >= 0 AND field_terisi <= field_total)`.

**Pemisahan tugas** ditegakkan server: akun yang mengisi tidak boleh menyetujui
pekerjaannya sendiri (`db._konflik`). Admin UPPS dikecualikan, karena itu
dibatasi satu akun.

**Satu sumber angka.** Daftar 38 tabel tinggal di `app/assets/js/data.js` sebagai
`TABEL`; rekap per kriteria diturunkan dari situ. Halaman tidak mendefinisikan
angka sendiri.

## Identitas prodi

Dari [situs resmi UBBG](https://bbg.ac.id/fakultas-dan-prodi/), diakses 9 Nov 2026:

| Prodi | Jenjang | Akreditasi | Berlaku |
|---|---|---|---|
| Kebidanan | S1 | Unggul | 26/09/2026 – 26/09/2030 |
| Pendidikan Profesi Bidan | Profesi | Unggul | 26/09/2026 – 26/09/2030 |
| Keperawatan | S1 | Baik Sekali | 28/02/2025 – 28/02/2030 |
| Pendidikan Profesi Ners | Profesi | Baik Sekali | 28/02/2025 – 28/02/2030 |

Karena prodi punya tahap profesi, **6 tabel "Khusus Profesi" wajib diisi** →
38 tabel wajib, bukan 32.

## Yang belum ada

- **Login / sesi.** Tombol isi memakai nama tetap; izin per peran belum ditegakkan.
- **14 halaman masih statis.** Baru Dashboard, Data Dukung, dan Pengguna yang
  membaca SQLite.
- **Buka kunci belum ada UI-nya.** Tabel yang dikunci belum bisa dibuka dari layar.
- **Data asli belum ada.** File Excel dari dosen pembimbing berisi template kosong
  (107 sel, semuanya nomor urut). Semua angka di app adalah simulasi.

## Lisensi

Prototipe untuk keperluan internal. Instrumen LAM-PTKes milik LAM-PTKes.
