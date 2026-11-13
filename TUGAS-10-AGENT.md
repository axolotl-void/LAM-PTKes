# TUGAS 10 AGENT — Swarm LAM-PTKes

Baca `KONTRAK-SWARM.md` dulu. Dokumen itu mengikat: wilayah berkas, pola kode,
helper, aturan tampilan, dan format uji.

Fondasi yang **sudah jadi dan dibekukan** (jangan diubah, jangan dibuat ulang):

```
web/system/{config,db,auth,view,layout,rekap}.php   helper + layout + rekap
web/index.php                                        front controller
web/aset/css/app.css, web/aset/js/app.js             tema + shell
web/spesifikasi/tabel.php                            38 tabel, 243 field
web/modul/masuk/index.php                            modul contoh (pola)
db/migrasi/001..005                                  skema + seed
```

Database lokal: container `si-lab-mysql`, DB `bbg_lamptkes`, port host **3307**.
Jalankan app: `cd web && php -S 127.0.0.1:8003 -t .`
Uji fondasi: `php web/uji/asap_masuk.php` dan `php web/uji/uji_rekap.php` — dua-duanya harus lulus.

Akun uji: `siti@bbg.ac.id` (OPERATOR, Kebidanan), `maimunah@bbg.ac.id` (VERIFIKATOR),
`zulfikar@bbg.ac.id` (ADMIN), `zahriah@bbg.ac.id` (PIMPINAN). Sandi: `demo1234`.

Skema yang perlu dibaca sebelum mulai:
```sql
SELECT * FROM tabel_dukung;      -- 38 tabel: kode, nomor, kriteria, khusus_profesi
SELECT * FROM spec_field WHERE tabel_kode='01';  -- label, kunci, tipe, asal, keterangan
SELECT * FROM baris_tabel;       -- satu baris = satu record per tabel/prodi/periode
SELECT * FROM sel;               -- nilai (EAV): baris_id + field_kunci
SELECT * FROM dokumen_spec;      -- 78 butir, kriteria, nomor, nama, rincian
SELECT * FROM konsistensi_aturan;-- 10 aturan lintas tabel
```

---

## Agent 1 — Kerangka halaman & navigasi

**Modul**: `web/modul/dashboard/`, `web/modul/ringkasan/`, `web/tampilan/kerangka/`
**Aktif di sidebar**: `dashboard`, `ringkasan`

Buat kerangka tampilan yang dipakai semua halaman lain: fungsi pembentuk kartu,
tabel, callout, strip angka, dan halaman galat. Taruh di `web/tampilan/kerangka/`.

Yang dikerjakan:
1. `web/tampilan/kerangka/komponen.php` — fungsi: `kartu($judul, $isi, $opsi)`,
   `tabel_html($kolom, $baris, $opsi)`, `strip_angka($item)`, `callout($jenis, $judul, $isi)`,
   `halaman_galat($kode, $pesan)`. Semua escape lewat `e()`.
2. `web/modul/dashboard/index.php` — dashboard nyata:
   - strip: total tabel wajib, selesai, sebagian, kosong, dokumen terunggah
   - daftar 5 tabel paling tertinggal (status kosong/sebagian), urut nomor
   - peringatan dokumen kedaluwarsa
   - **semua angka dari `rekap.php`**, jangan hitung sendiri
   - kalau semua kosong: tampilkan keadaan kosong yang jujur ("belum ada data
     diisi"), bukan kotak-kotak 0 yang tidak berarti
3. `web/modul/ringkasan/index.php` — 8 kriteria:
   - per kriteria: jumlah tabel, lengkap, sisa, jumlah dokumen, dokumen terunggah
   - K1 punya 0 tabel — tampilkan apa adanya dengan catatan "hanya dokumen"
   - bar harus mengkodekan besaran yang sama dengan angka di sebelahnya
4. `web/aset/css/kerangka.css` — komponen yang belum ada di `app.css`
5. `web/uji/uji_kerangka.php`

**Terima**: dashboard dan ringkasan menampilkan angka yang sama dengan
`rekap_ringkas()` untuk prodi yang sama. Uji membuktikannya.

---

## Agent 2 — Daftar & form tabel data dukung

**Modul**: `web/modul/tabel/`
**Aktif di sidebar**: `tabel`

Ini inti sistem: 38 tabel, 243 field.

1. `a=index` — daftar 38 tabel: nomor, judul, kriteria, status (dari
   `status_satu_tabel()`), terisi/total, aksi. Bisa disaring per kriteria.
2. `a=lihat&t=<kode>` — form isian satu tabel:
   - baca `spec_field WHERE tabel_kode = ?` urut `urut`
   - field `asal='Input'` → input yang bisa diedit
   - field `asal='Master'` → tampil sebagai nilai **read-only** + label sumbernya
   - field `asal='Hitung'` → tampil read-only + keterangan "dihitung sistem"
   - field `asal='Tetap'` → teks penanda, read-only
   - `tipe` menentukan kontrol: angka/desimal → `type=number`, tanggal → `date`,
     tahun akademik → `type=number`, teks panjang → `textarea`, selain itu → `text`
   - form mendukung **banyak baris** (tabel 1 punya 239 baris). Tambah/hapus baris
     lewat JS kecil di `web/aset/js/tabel.js` — tanpa reload.
   - simpan: `wajib('ISI')`, `csrf_periksa()`, tulis ke `baris_tabel` + `sel`
     dalam satu `transaksi()`. Hapus sel yang dikosongkan, jangan simpan string kosong.
3. `a=kunci` — `wajib('VERIFIKASI')`; hanya boleh kalau status `lengkap`
4. `a=buka` — `wajib('BUKA_KUNCI')`
5. `web/aset/css/tabel.css`, `web/uji/uji_tabel.php`

**Terima**: mengisi tabel 01 dengan 3 field dan menyimpan → status berubah jadi
`lengkap`. Menyimpan saat terkunci → ditolak 403. Verifikator mencoba isi → 403.

---

## Agent 3 — Master mahasiswa & dosen

**Modul**: `web/modul/master/` (aksi `mahasiswa`, `dosen`)
**Aktif di sidebar**: `mahasiswa`, `dosen`

1. Daftar + form mahasiswa: NIM, nama, tahun masuk, jalur, tahap, status, IPK,
   tanggal lulus, tahun lulus. Pencarian + saring status. Hapus = nonaktifkan,
   bukan DELETE (riwayat pengisian harus tetap punya rujukan).
2. Daftar + form dosen/tendik: NIDN, nama, jenis, jabatan fungsional, gelar,
   sertifikat pendidik, profesi, STR, tahap mengajar, riwayat pendidikan (banyak baris).
3. Impor CSV untuk dua entitas ini: unggah berkas → pratinjau 10 baris pertama →
   konfirmasi → simpan. Tolak baris yang gagal dengan **nomor baris + alasannya**,
   jangan gagal semua.
4. `web/aset/css/master.css`, `web/uji/uji_master.php`

**Terima**: NIM ganda ditolak dengan pesan yang menyebut NIM-nya. IPK 5,0 ditolak.
Impor 3 baris (2 benar, 1 salah) → 2 masuk, 1 dilaporkan.

---

## Agent 4 — Master akademik (MK, wahana, lab, pustaka)

**Modul**: `web/modul/master/` (aksi `mk`, `wahana`, `lab`, `pustaka`)
**Aktif di sidebar**: `kurikulum`, `sarana`, `pustaka`

1. Mata kuliah: kode, nama, sks kuliah/praktik, CPL, 4 aspek penilaian (centang),
   RPS, lokasi praktik, tahap. Tampilkan total SKS per tahap.
2. Wahana praktik: nama, kualifikasi, variasi kasus, jumlah pembimbing, dokumen
   kerja sama, **berlaku sampai**. Wahana kedaluwarsa disorot, bukan disembunyikan.
3. Laboratorium & alat: nama lab, alat, jumlah unit, kepemilikan SD/SW, kondisi, logbook, jam pakai.
4. Pustaka & jurnal: jenis, judul, jumlah judul/copy, tahun, nomor.
5. `web/aset/css/master-akademik.css`, `web/uji/uji_master_akademik.php`

**Terima**: total SKS dihitung, bukan diketik. Wahana dengan MoU kedaluwarsa
muncul di daftar perhatian dengan tanggalnya.

---

## Agent 5 — Identitas, periode, dan penanganan tahun relatif

**Modul**: `web/modul/identitas/`, `web/modul/periode/`
**Aktif di sidebar**: `identitas`, `periode`

1. Identitas UPPS & prodi: 8 field UPPS + 10 field prodi. Perubahan jenjang atau
   `punya_tahap_profesi` harus menampilkan **konsekuensinya langsung** ("38 tabel
   wajib" vs "32 tabel wajib") sebelum disimpan.
2. Periode akreditasi: `tahun_ts`, `tahun_ss`, tanggal pengajuan. Tampilkan
   pratinjau **semua label** yang akan dipakai: TS-0..TS-6, SS-1..SS-16.
3. **Penting**: ganti `tahun_ts` tidak boleh mengubah data, hanya label. Tulis uji
   yang membuktikannya.
4. `web/aset/css/identitas.css`, `web/uji/uji_identitas.php`

**Terima**: mengubah tahun_ts dari 2025 ke 2026 mengubah semua label TS tetapi
jumlah baris `baris_tabel` dan `sel` **tidak berubah**.

---

## Agent 6 — Dokumen pendukung & unggahan

**Modul**: `web/modul/dokumen/`
**Aktif di sidebar**: `dokumen`

1. Daftar 78 butir per kriteria. Per butir: status (belum/ada/kedaluwarsa),
   nama berkas, tanggal unggah, tanggal berlaku.
2. Unggah: validasi tipe (pdf, docx, xlsx, jpg, png), ukuran maks 20 MB, nama
   berkas disimpan sebagai `<id>_<random>.<ext>` — **jangan pakai nama asli**
   (path traversal). Simpan nama asli di kolom `nama_asli`.
3. Riwayat unggahan per butir: yang lama tetap ada, yang dipakai terbaru.
4. Peringatan kedaluwarsa: dokumen yang `berlaku_sampai < hari ini`.
5. `web/aset/css/dokumen.css`, `web/uji/uji_dokumen.php`

**Terima**: unggah `.exe` ditolak. Nama berkas `../../etc/passwd` tidak bisa
keluar folder `unggah/`. Dokumen kedaluwarsa muncul dengan tanggal.

---

## Agent 7 — Konsistensi lintas tabel

**Modul**: `web/modul/konsistensi/`
**Aktif di sidebar**: `konsistensi`

1. Baca 10 aturan dari `konsistensi_aturan`. Jalankan yang bisa dihitung.
2. Setiap aturan menampilkan: nilai A, nilai B, selisih, status, dan **tabel mana
   yang harus diperbaiki**.
3. Tingkat `blokir` → ekspor dimatikan. `peringatan` → boleh ekspor, muncul di catatan.
4. Aturan yang **belum bisa dihitung** karena datanya belum ada, tampilkan
   "belum ada data" — jangan status "lulus" palsu. Ini kesalahan yang sudah
   kejadian di prototipe (R4 menampilkan selisih 0 padahal 7 vs 6).
5. `web/aset/css/konsistensi.css`, `web/uji/uji_konsistensi.php`

**Terima**: aturan tanpa data menampilkan "belum ada data", bukan lulus.
Selisih dihitung, tidak pernah diketik `0`.

---

## Agent 8 — Pengguna & hak akses

**Modul**: `web/modul/pengguna/`
**Aktif di sidebar**: `pengguna` (hanya untuk yang boleh)

1. Daftar akun: nama, email, peran, prodi, status, masuk terakhir.
2. Ubah peran, prodi, aktif/nonaktif. **Tidak boleh mengubah sandi orang lain**
   tanpa diminta — yang ada hanya "kirim tautan atur ulang" (belum perlu dibuat;
   cukup tombol nonaktif).
3. Admin tidak bisa menonaktifkan akunnya sendiri (kalau bisa, sistem bisa
   kehilangan admin terakhir). Tolak dengan pesan jelas.
4. Matriks izin ditampilkan read-only: 4 peran × 6 aksi, dengan `akses` 0/1/2.
5. Riwayat perubahan akun diambil dari tabel `audit`.
6. `web/aset/css/pengguna.css`, `web/uji/uji_pengguna.php`

**Terima**: admin menonaktifkan diri sendiri → ditolak. Operator membuka
`m=pengguna` → 403.

---

## Agent 9 — Ekspor PDF & XLSX

**Modul**: `web/modul/ekspor/`
**Aktif di sidebar**: tombol PDF/XLSX

1. **XLSX tanpa library**: cPanel tidak selalu punya Composer. Tulis
   `web/system/xlsx.php` yang membuat `.xlsx` sah dari nol — arsip ZIP berisi
   `[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/worksheets/sheetN.xml`.
   PHP punya `ZipArchive`. Satu sheet per tabel, gaya minimal.
2. **PDF**: HTML siap cetak + `window.print()` CSS `@media print`. Jangan pakai
   library PDF eksternal — kalau nanti perlu DomPDF, itu keputusan terpisah.
3. Ekspor diblokir kalau ada aturan `blokir` yang gagal (baca `konsistensi_aturan`
   + hitung) atau ada akun berperan ganda.
4. Tabel 5 dan 6 **wajib** memuat penanda (a)–(g). Ambil dari `spec_field`
   `asal='Tetap'`.
5. `web/aset/css/ekspor.css`, `web/uji/uji_ekspor.php`

**Terima**: berkas `.xlsx` bisa dibuka Excel/LibreOffice (uji: `unzip -l` dan
cek XML sah). Ekspor ditolak saat ada blokir.

---

## Agent 10 — SSO dari SPMI

**Modul**: `web/modul/masuk/` (aksi `sso`) + `web/system/sso.php`
**Catatan**: `web/modul/masuk/index.php` sudah ada. **Tambahkan** aksi `sso`
tanpa merusak yang ada. Jangan ubah bagian login lokal.

1. `web/system/sso.php`:
   - terima `?t=<isi>.<tanda_tangan>`
   - `isi` = base64url JSON `{e,n,r,j,x}`
   - tolak kalau `x < time()` (kedaluwarsa 90 detik)
   - tolak kalau HMAC-SHA256 tidak cocok (`hash_equals`)
   - tolak kalau nonce `j` sudah ada di `sso_nonce` (sekali pakai)
   - cari `pengguna WHERE email = e`; kalau belum ada, **jangan buat akun
     otomatis** — tolak dengan pesan "akun belum terdaftar, hubungi Admin".
     Akun otomatis dari SSO = siapa pun di SPMI bisa masuk sistem akreditasi.
   - kalau `aktif = 0` → tolak
   - buat sesi lewat `masuk_sebagai()`, catat nonce, alihkan ke dashboard
2. `web/uji/uji_sso.php` — uji semua penolakan: kedaluwarsa, tanda tangan salah,
   nonce dipakai dua kali, akun tidak ada, akun nonaktif, dan satu kasus sukses.
   Buat token uji sendiri dengan `hash_hmac`.
3. Tulis **berkas pendamping** `web/modul/masuk/PANDUAN-SPMI.md` berisi potongan
   kode PHP untuk `spmi.bbg.ac.id/dashboard/lamptkes.php` (mengikuti pola
   `laminfokom.php` yang sudah ada) dan daftar yang harus diisi di kedua config.

**Terima**: 6 uji penolakan lulus, 1 uji sukses lulus. Nonce yang sama dua kali
→ ditolak pada percobaan kedua.

---

## Urutan pengerjaan & ketergantungan

```
Agent 1 (kerangka)  ──┐  komponen dipakai 2,3,4,6,7,8
Agent 5 (identitas) ──┤  tahun_ts dipakai label semua modul
                      ├─→ Agent 2 (tabel)  ← inti, paling berat
                      ├─→ Agent 3, 4 (master)
                      ├─→ Agent 6 (dokumen)
                      ├─→ Agent 7 (konsistensi)
                      ├─→ Agent 8 (pengguna)
                      └─→ Agent 9 (ekspor)
Agent 10 (SSO)      ────  mandiri, tidak bergantung apa pun
```

Agent 1 harus menulis `komponen.php` **lebih dulu** dari yang lain supaya
agent 2–9 tidak membuat versi kartu/tabel sendiri. Kalau `komponen.php` belum
ada saat agent lain mulai, mereka **wajib menunggu** atau memakai HTML polos
dan menggantinya nanti — jangan membuat fungsi kartu sendiri.
