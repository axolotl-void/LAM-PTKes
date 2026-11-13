# KONTRAK KERJA — Swarm 10 Agent

Dokumen ini **mengikat setiap agent**. Dibaca sebelum menulis satu baris kode.
Kalau ada yang bertentangan dengan instruksi task, dokumen ini menang.

## 0. Batas wilayah — TIDAK BOLEH dilanggar

| Path | Siapa yang boleh menulis |
|---|---|
| `web/system/*` | **tidak ada agent.** Sudah selesai, dibekukan. Baca saja. |
| `web/index.php`, `web/system/layout.php` | **tidak ada agent.** Dibekukan. |
| `web/aset/css/app.css`, `web/aset/js/app.js` | **tidak ada agent.** Butuh komponen baru? tulis di `web/aset/css/<modul>.css`. |
| `web/spesifikasi/tabel.php` | **tidak ada agent.** Dibangkitkan skrip. |
| `db/*` | **tidak ada agent.** Migrasi dibekukan. |
| `web/modul/<modul-anda>/*` | **hanya agent pemilik modul itu** |
| `web/tampilan/<modul-anda>/*` | **hanya agent pemilik modul itu** |
| `web/aset/css/<modul-anda>.css` | **hanya agent pemilik modul itu** |
| `web/uji/uji_<modul-anda>.php` | **hanya agent pemilik modul itu** |

Menulis di luar wilayah = tugas dianggap gagal, walaupun fiturnya jalan.

## 1. Pola yang WAJIB diikuti

Modul contoh yang sudah jalan dan sudah diuji: **`web/modul/masuk/index.php`**.
Baca berkas itu dulu. Semua modul meniru polanya:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/layout.php';

$aksi = $_GET['a'] ?? 'index';

// 1. Gerbang izin DULU, sebelum menyentuh data.
if ($aksi === 'simpan') {
    wajib('ISI');            // atau IMPOR / VERIFIKASI / BUKA_KUNCI / KELOLA_PENGGUNA
    csrf_periksa();
    // ... tulis data ...
    alihkan(BASE_URL . 'index.php?m=<modul>&a=index');
}

// 2. Ambil data lewat q() / q1() / qv() — JANGAN pernah merangkai SQL.
$baris = q('SELECT * FROM ... WHERE prodi_id = ?', [$PRODI]);

// 3. Render. Semua keluaran lewat e().
render('Judul', $isiHtml, ['aktif' => '<modul>', 'sub' => '...']);
```

### Larangan keras

| Larangan | Kenapa |
|---|---|
| `mysqli_*` | PDO saja. Dua API DB di satu proyek = bug yang tidak bisa dilacak. |
| SQL dengan variabel dirangkai (`"... WHERE id = $id"`) | Placeholder `?`. Tidak ada pengecualian, termasuk untuk angka. |
| `echo $_POST[...]` atau `echo $row[...]` langsung | Selalu `e()`. Data kampus mengandung `&`, `<`, kutip. |
| `strip_tags` / `sanitize` sebagai pengganti escape | Bukan escape. Simpan nilai mentah, escape saat tampil. |
| `alert()` / `confirm()` di PHP | Konfirmasi lewat atribut `data-konfirmasi` pada `<form>`. |
| CSS baru di `app.css` | Tulis di `web/aset/css/<modul>.css`. |
| `<style>` di dalam modul | Kecuali halaman masuk (sudah ada). Pakai berkas CSS modul. |
| Angka yang diketik di tampilan | Hitung dari DB. Kalau tidak bisa dihitung, tulis alasannya di komentar. |
| `SELECT *` untuk data yang ditampilkan | Sebut kolomnya. `SELECT *` membocorkan kolom baru tanpa disadari. |

## 2. Helper yang tersedia (jangan bikin versi sendiri)

Dari `web/system/db.php`:
```php
db()                          // PDO
q($sql, $param)               // array baris
q1($sql, $param)              // satu baris | null
qv($sql, $param, $bawaan)     // satu nilai
ex($sql, $param)              // jumlah baris terpengaruh
sisip($tabel, $data)          // insert, balik id
ubah($tabel, $data, $kunci, $nilai)
transaksi(callable)
```

Dari `web/system/auth.php`:
```php
pengguna()                    // array | null
prodi_aktif()                 // int | null
akses($aksi, $u)              // 0 | 1 | 2
boleh($aksi)                  // bool
hanya_lihat($aksi)            // bool
wajib($aksi)                  // hentikan 401/403
wajib_masuk()                 // array pengguna
csrf() / csrf_input() / csrf_periksa()
```

Dari `web/system/view.php`:
```php
e($v)                         // escape HTML
rupiah($n) / desimal($n,$d) / tgl($iso)
label_ts($tahunTs, $mundur)   // 'TS-1 (2024/2025)'
status_tabel($terisi,$total,$dikunci,$berlaku)   // 'kosong'|'sebagian'|'lengkap'|'terkunci'|'na'
badge_status($status)         // HTML badge
tolak($kode,$pesan) / alihkan($url)
masuk_input($nama) / halaman()
```

Dari `web/system/layout.php`:
```php
render($judul, $isiHtml, ['aktif' => '<kode>', 'sub' => '...', 'aksi' => '<html tombol>'])
```

## 3. Skema 38 tabel: kunci, bukan kolom

`spec_field` (243 baris) menyimpan 5 kolom per field:
`tabel_kode, urut, label, kunci, tipe, asal, keterangan`.

`asal` menentukan siapa yang mengisi:

| asal | Arti | Yang menyimpan |
|---|---|---|
| `Input` | 109 field | operator, ke `sel` |
| `Master` | 65 field | otomatis dari master, **tidak disimpan** di `sel` |
| `Hitung` | 68 field | dihitung sistem, **tidak disimpan** di `sel` |
| `Tetap` | 1 field | teks penanda (a)–(g), **tidak disimpan** |

Penyimpanan nilai: tabel `sel` (EAV), kunci `baris_id + field_kunci`.
- `tipe` angka/desimal/tahun akademik/tanggal → `nilai_angka`
- selain itu → `nilai_teks`

Baris: `baris_tabel` (satu baris = satu record per tabel per prodi per periode).

## 4. Aturan tampilan yang mengikat

Diambil dari `08 - Aturan Desain Anti-Slop` — ini bukan selera, ini hasil
halaman yang ditolak berkali-kali:

1. **Status selalu label teks + ikon**, bukan warna saja. Asesor mencetak hitam-putih.
2. **Bar di samping angka harus mengkodekan besaran yang sama** dengan angkanya.
   Bar = porsi selesai + angka = jumlah celah → menyesatkan.
3. **Jangan render blok kosong.** Kalau isinya nol dan bukan indikator masalah, hapus.
4. **Angka tanpa label ditebak salah.** `17` harus jadi `17 tabel`.
5. **Definisi status satu fungsi**, dipakai di badge, blok, dan hitungan.
6. **Font fluid `clamp()`, breakpoint hanya untuk struktur.**
7. **Tidak ada overflow horizontal** di 320–1920px.

## 5. Bahasa

Seluruh UI **Bahasa Indonesia**. Nama variabel/fungsi boleh Indonesia
(`$baris`, `simpan`, `wajib`) — konsisten dengan kode yang sudah ada.
Komentar menjelaskan **alasan**, bukan mengulang kode.

## 6. Uji wajib per modul

Setiap agent menulis `web/uji/uji_<modul>.php` yang:
- memanggil `require __DIR__ . '/../system/view.php'`
- **tidak butuh server HTTP** — panggil fungsi langsung
- mencetak `ok <nama>` per uji dan `GAGAL <nama> — <detail>` kalau gagal
- keluar dengan kode 1 kalau ada kegagalan
- minimal menguji: gerbang izin (boleh/tidak), query mengembalikan angka yang
  benar, dan satu kasus batas (kosong / nilai aneh / baris tidak ada)

Jalankan sendiri: `php web/uji/uji_<modul>.php`. **Jangan lapor selesai kalau
uji belum lulus.**

## 7. Yang harus dilaporkan

Laporan akhir, maksimal 12 baris:

```
modul      : <nama>
berkas     : <daftar path yang dibuat/diubah>
uji        : <n> lulus, <n> gagal  (tempel baris terakhir output)
belum      : <yang sengaja tidak dikerjakan + alasannya>
risiko     : <hal yang bisa salah kalau data asli dipakai>
```

Jangan menyalin kode ke laporan. Jangan mengaku "sudah diuji" tanpa output.

## 8. Kalau buntu

Tulis pertanyaan di laporan, **jangan mengarang**. Terutama:
- field yang artinya tidak jelas di `spec_field`
- aturan validasi yang tidak ada di dokumen
- apakah sebuah angka masuk akal untuk prodi kesehatan

Data karangan di sistem akreditasi lebih berbahaya daripada halaman kosong.
