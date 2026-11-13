# Rancangan Database — LAM-PTKes

**Status** tahap 1 dari 3: inti sistem jalan, 38 tabel data dukung **belum**.
**Tanggal** 13 Nov 2026 · MySQL 8.0 / MariaDB 10.4+ (cPanel), InnoDB, utf8mb4.

## Isi folder

| Berkas | Isi |
|---|---|
| `migrasi/001_inti.sql` | 22 tabel: akun, peran, izin, sesi, nonce SSO, audit, 13 master |
| `migrasi/002_uji.sql` | 15 uji jaminan skema. Skrip ini **gagal dengan sengaja** kalau ada jaminan yang bocor. |
| `extract_fields.py` | tarik 243 field (+ tipe & asal) dari docx spesifikasi ke `field-spec.json` |
| `field-spec.json` | 55 tabel hasil tarikan docx — sumber untuk migrasi 003 |

## Kenapa dua tahap

Skema 38 tabel data dukung **belum dibuat** karena masih menunggu satu jawaban:
versi instrumen. Kalau versinya beda, yang berubah bukan 5 tabel — pemetaan
kriteria, varian Tabel 5, dan 78 butir dokumen ikut berubah. Pemetaan kriteria
sudah pernah salah sekali di prototipe dan baru ketahuan setelah app jadi.

Yang **tidak** bergantung versi instrumen dikerjakan lebih dulu: akun, izin, sesi,
SSO, audit, dan master data.

## Cara pakai

```bash
# buat DB (di cPanel: lewat menu MySQL Databases)
mysql -u root -p -e "CREATE DATABASE bbg_lamptkes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mysql -u root -p bbg_lamptkes < migrasi/001_inti.sql
mysql -u root -p bbg_lamptkes < migrasi/002_uji.sql   # harus: LULUS 15 uji skema
```

Lokal (Docker `si-lab-mysql` yang sudah ada untuk SPMI):

```bash
docker exec -i si-lab-mysql mysql -uroot -proot -e \
  "DROP DATABASE IF EXISTS bbg_lamptkes; CREATE DATABASE bbg_lamptkes CHARACTER SET utf8mb4;"
docker exec -i si-lab-mysql mysql -uroot -proot bbg_lamptkes < migrasi/001_inti.sql
docker exec -i si-lab-mysql mysql -uroot -proot bbg_lamptkes < migrasi/002_uji.sql
```

## Keputusan yang dikunci di skema

**Peran ganda mustahil, bukan dilarang.** `pengguna.peran_kode` satu kolom.
Tidak ada tabel penghubung. Konsekuensi: aturan "pengisi tidak boleh menyetujui
pekerjaannya sendiri" tidak bisa dilanggar lewat data — hanya lewat `ALTER TABLE`
yang disengaja. Prototipe lama memakai teks `"Operator + Verifikator"` dan harus
diperiksa tiap kali.

**Izin di tabel, bukan ENUM.** 4 peran × 6 aksi = 24 baris. Server dan UI membaca
matriks yang sama, jadi tidak mungkin beda pendapat.

**Status tabel tidak disimpan.** (Berlaku nanti di 003.) Status dihitung dari
`field_terisi`/`field_total`. Kelas bug "lengkap padahal 3/6" jadi mustahil.

**Nonce SSO disimpan.** Satu tautan SSO hanya bisa dipakai sekali. SPMI sudah
membuat nonce, tapi SIM-LKPS tidak memeriksanya — untuk sistem ini wajib.

**Audit menyimpan nilai lama dan baru.** Satu baris per field, teks bebas, supaya
bisa dibaca tanpa tahu tipe kolom asalnya.

**Tahun absolut, label dihitung.** `periode.tahun_ts` satu-satunya sumber label
TS-n / SS-n. Ganti tahun = ganti label, data tidak berubah.

## Uji: apa yang dijamin

`002_uji.sql` membuktikan 15 hal, dan **gagal dengan SIGNAL** kalau salah satu
bocor:

```
IPK > 4,00 ditolak              IPK negatif ditolak
NIM ganda ditolak               terlacak > jumlah lulusan ditolak
lulus UKOM > peserta ditolak    nonce SSO dipakai dua kali ditolak
akses izin di luar 0..2 ditolak peran tidak dikenal ditolak
email akun ganda ditolak        peran ganda mustahil (satu kolom)
tidak ada tabel peran ganda     matriks izin lengkap 24 baris
operator tidak boleh verifikasi verifikator tidak boleh isi
pimpinan hanya lihat (akses 2)  setiap peran punya 6 baris izin
```

Uji ini **sudah dibuktikan bisa gagal**: pada DB terpisah, satu baris izin diubah
jadi `akses=1` (operator boleh verifikasi) → skrip berhenti dengan
`GAGAL uji: operator tidak boleh verifikasi (dapat 1, harusnya 0)`. Tanpa
pembuktian ini, "15 lulus" cuma klaim.

## Yang belum ada di skema

- **38 tabel data dukung** (`tabel_data`, `baris_tabel`, pemetaan kriteria) —
  menunggu konfirmasi versi instrumen.
- **78 butir dokumen pendukung** + unggahan file.
- **Aturan lintas tabel** sebagai data, bukan kode.
- **Riwayat ekspor** dan antrean pekerjaan (kalau ekspor dibuat async).

## Catatan teknis MySQL yang kena saat menulis

1. MySQL 8 tidak punya `EXECUTE ... INTO ?` — `INTO` harus jadi bagian teks SQL
   yang di-PREPARE (`SELECT (...) INTO @hasil`).
2. `SIGNAL ... SET MESSAGE_TEXT = CONCAT(...)` ditolak parser; harus lewat
   variabel dulu.
3. `IF` / `SIGNAL` hanya sah di dalam stored program — tidak bisa dipakai di
   skrip lepas. Karena itu uji dibungkus prosedur `harus_tolak` dan `pastikan`.
4. Urutan `CREATE TABLE` penting: `stase` mereferensikan `wahana` dan `dosen`,
   jadi dibuat setelah keduanya. MySQL tidak menunda foreign key.
