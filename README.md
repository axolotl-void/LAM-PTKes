# LAM-PTKes — Sistem Akreditasi Prodi Kesehatan

Aplikasi akreditasi **LAM-PTKes** untuk Program Studi Kebidanan & Keperawatan,
Universitas Bina Bangsa Getsempena (UBBG). Instrumen: 38 tabel data dukung,
8 kriteria, 78 butir dokumen pendukung.

Status: **prototipe fungsional.** Tampilan selesai, pengisian bisa disimulasikan
lewat SQLite, **login + hak akses per peran jalan**, tapi **data yang terisi masih
contoh** — bukan data kampus.

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

Buka **http://127.0.0.1:8123** — yang muncul layar masuk.

**Akun demo** (sandi semua `demo1234`; tombol di layar masuk mengisi nama otomatis):

| Akun | Peran | Prodi |
|---|---|---|
| Siti Rahmawati, S.ST., M.Keb | Operator Prodi | Kebidanan |
| Nurhayati, S.Kep., M.Kep | Operator Prodi | Keperawatan |
| Dr. Maimunah, M.Keb | Verifikator | Kebidanan |
| Yusrizal, S.Kep., Ns., M.Kep | Verifikator | Keperawatan |
| Dr. Ir. Zulfikar, M.Pd | Admin UPPS | semua |
| Prof. Dr. Cut Zahriah, M.Pd | Pimpinan (read-only) | semua |
| Marlina, S.ST., M.Keb | Operator + Verifikator → status `konflik` | dua-duanya |
| Hasnawati, A.Md.Keb | Operator · **nonaktif**, tidak bisa masuk | Kebidanan |

Tidak ada dependency di luar Python 3 stdlib (`sqlite3` sudah termasuk).
Database `lamptkes.db` dibuat otomatis saat server pertama kali jalan.

### Perintah lain

```bash
python3 db.py              # lihat isi DB + ringkasan
python3 db.py reset        # kembali ke kondisi awal
python3 simulate.py        # isi tabel seperti petugas bekerja
python3 test_db.py         # 9 uji aturan DB + sesi/izin
node --test test/data.test.js   # 10 uji data & render
```

## API

Semua JSON. **Identitas diambil dari cookie `sesi`, bukan dari body** — dulu
`oleh` dikirim klien, artinya siapa pun bisa mengaku Admin. Sekarang peran &
prodi hanya datang dari token, dan endpoint menolak dengan 401/403 sesuai
matriks izin di tabel `izin`.

```
POST /api/masuk                  {"nama","sandi","prodi"} → sesi + Set-Cookie
POST /api/keluar
GET  /api/saya                   sesi aktif + matriks akses
GET  /api/ringkasan              angka turunan untuk dashboard
GET  /api/tabel                  semua 38 tabel + status
POST /api/tabel/<kode>/isi       {"terisi": 6}      butuh akses "Isi"
POST /api/tabel/<kode>/kunci                        butuh "Verifikasi"
POST /api/tabel/<kode>/buka                         butuh "Buka kunci"
GET  /api/pengguna               butuh "Kelola pengguna"
POST /api/pengguna/<id>/peran    {"peran", "status"} butuh "Kelola pengguna"
POST /api/pengguna/<id>/pisah    butuh "Kelola pengguna"
GET  /api/riwayat?batas=50       log perubahan (perlu sesi)
POST /api/reset                  butuh "Kelola pengguna"
```

Matriks izin (0 tidak boleh / 1 boleh / 2 hanya lihat):

| Aksi | Operator | Verifikator | Admin | Pimpinan |
|---|---|---|---|---|
| Isi | 1 | 0 | 1 | 2 |
| Impor | 1 | 0 | 1 | 2 |
| Verifikasi | 0 | 1 | 1 | 2 |
| Buka kunci | 0 | 0 | 1 | 0 |
| Kelola pengguna | 0 | 0 | 1 | 0 |
| Ekspor | 1 | 1 | 1 | 1 |

Konsekuensi yang disengaja: operator tidak bisa mengunci pekerjaannya sendiri,
verifikator tidak bisa mengisi. Menu **Pengguna & Hak Akses** hanya muncul untuk
Admin.

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

- **11 halaman masih statis** (Identitas, Periode, Dosen, Keuangan, Kerja Sama,
  Data Dukung agregat, Dokumen, Konsistensi, Uji, Survei). Baru Dashboard,
  Data Dukung per-tabel, dan Pengguna yang membaca SQLite.
- **Sandi demo semua akun sama** dan belum ada halaman ganti sandi / kelola sesi.
- **Cakupan per-prodi belum ditegakkan.** Tabel belum punya kolom prodi, jadi
  operator Kebidanan masih bisa mengisi tabel yang sama dengan Keperawatan.
- **Cookie belum `Secure`** — prototipe ini jalan di `127.0.0.1`. Sebelum dipasang
  di jaringan: pasang HTTPS + `Secure`, dan pindahkan sesi ke server asli.
- **Impor/ekspor masih `alert()`**, belum ada endpoint.
- **Data asli belum ada.** File Excel dari dosen pembimbing berisi template kosong
  (107 sel, semuanya nomor urut). Semua angka di app adalah simulasi.

## Lisensi

Prototipe untuk keperluan internal. Instrumen LAM-PTKes milik LAM-PTKes.
