---
title: LAM-PTKes — Desain UI & Handoff
description: Paket serah-terima desain ke agent backend — tema, token, 13 layar, aturan data, dan yang belum selesai
tags: [lam-ptkes, desain, handoff, ui, akreditasi]
created: 2026-11-09
status: aktif
---

# LAM-PTKes — Desain UI & Handoff

Dokumen ini untuk **agent backend** yang akan membangun database & API. Isinya: apa yang
sudah digambar, aturan visual yang mengikat, dan — paling penting — **kontrak data** yang
harus backend sediakan supaya tampilan ini bisa hidup.

Folder ini berisi:
- `LAM-PTKes-Desain.md` — dokumen ini
- `mockup.figcraft` — file FigCraft, 13 frame, bisa dibuka & diedit
- `screens/*.png` — render 2x tiap layar
- `rules/` — script generator (Python) untuk tiap layar, sumber kebenaran tata letak

---

## 1. Ringkasan proyek

Sistem pengelolaan data akreditasi prodi kesehatan mengikuti instrumen **LAM-PTKes**.
Bukan skripsi — ini kerja joki untuk teman. Sumber kebenaran instrumen: dua file Excel dari
dosen pembimbing, keduanya **byte-identik** (MD5 `32743c142b88877f06e79c1831221e97`),
jadi satu template generik untuk semua prodi kesehatan.

Angka yang sudah terukur dari file asli:

| Item | Nilai |
|---|---|
| Sheet per file | 41 sheet data (3 pengantar + 38 tabel) + 3 varian Tabel 5 = 44 |
| Kriteria penilaian | 8 |
| Butir dokumen pendukung | 79 |
| Tabel berformula (SUM/COUNTA) | 17 dari 38 |
| Field isian 38 tabel | 243 |
| Field master data | 103 |
| Tabel khusus tahap profesi | 6 (Tabel 4, 6, 7, 8, 11, 30) |

Kalau ada angka di desain yang beda dari ini, **desainnya salah** — bukan instrumennya.

---

## 2. Tema: "Klinis Netral"

Light-only. Alasan: dipakai di ruang rapat/proyektor, dan hasil ekspor PDF-nya putih.
Dark mode sengaja tidak dibuat.

### Warna

```
bg          #F5F6F9     surface     #FFFFFF
border      #E4E7EC     divider     #F2F4F7
text-1      #101828     text-2      #475467     text-3    #667085
brand-indigo #2F2A95    brand-tint  #EEEDF8
brand-orange #FF8200    orange-tint #FFF3E6
```

Indigo `#2F2A95` dan oranye `#FF8200` **bukan pilihan sembarang** — keduanya diukur
langsung dari logo resmi UBBG (oranye 51% area, indigo 36,5%, hitam 12,3% wordmark).
Jangan diganti ke biru institusi generik.

### Warna status

```
Lengkap        #067647 / bg #ECFDF3
Sebagian       #B54708 / bg #FFFAEB
Kosong|Gagal   #B42318 / bg #FEF3F2
Terkunci       #175CD3 / bg #EFF8FF
Tak berlaku    #667085 / bg #F9FAFB (garis putus)
```

Status **selalu** ikut label teks, bukan warna saja — asesor mencetak hitam-putih.

### Tipografi & ukuran

- UI: **Helvetica Neue** (fallback Inter di web)
- Angka tabel: **monospace** (Menlo/JetBrains Mono) — angka tabular wajib, Tabel 5 punya
  25 kolom angka dan harus lurus vertikal saat dicetak
- Skala: 9 · 9.5 · 10 · 10.5 · 11 · 11.5 · 12 · 13 · 24 (judul halaman) · 34+ (angka besar)
- Sidebar 248px · topbar 56px · radius kartu 10 · radius kontrol 6

---

## 3. Kerangka navigasi

Satu sidebar, **switch prodi di atas** (bukan dua tree). Dua zona:

```
BERSAMA UPPS   ← tidak berubah saat switch prodi
  Identitas UPPS & Prodi   F-01
  Periode Akreditasi       F-02
  Dosen & Tendik           F-06
  Keuangan                 F-17
  Kerja Sama               F-19
  Pengguna & Hak Akses     F-22

PRODI: <nama>  ← berubah saat switch
  Mahasiswa                F-05
  Kurikulum & MK           F-07
  Sarana & Wahana          F-15
  Pustaka & Jurnal         F-16
  Uji Kompetensi           F-09
  Survei & Tracer          F-10
```

Alasan satu tree: menu jadi 2x kalau dipisah, dan tabel lintas prodi (9, 15, 35, 37)
tidak punya tempat.

**Prodi disimpan di URL**, bukan session: `/upps/1/prodi/2/tabel/5`. Link bisa dibagikan,
tab lama tidak salah konteks.

---

## 4. Kontrak data per layar

Ini bagian yang paling perlu dibaca backend. Tiap layar menuntut bentuk data tertentu.

### 01 · Dashboard

Menampilkan status kesiapan, bukan 4 kartu KPI kembar.

| Blok | Butuh dari backend |
|---|---|
| Peringkat + skala C/B/A/Unggul | `prodi.peringkat`, `prodi.tgl_berakhir`, selisih hari |
| Strip data dukung 32/4/2 | agregat status 38 tabel → `{lengkap, sebagian, kosong}` |
| Celah terbesar | per kriteria: `tabel_total`, `tabel_lengkap`, `tabel_na` |
| Menghalangi ekspor | hasil evaluasi 10 aturan lintas tabel |

Aturan: **satu angka satu tempat**. `32/38` muncul sekali, sisanya turunan.

### 02 · Data Dukung

38 tabel jadi **satu halaman checklist**, bukan 38 item menu.

Bentuk data per tabel:
```json
{ "no": 1, "judul": "Substansi Kurikulum", "kriteria": 2,
  "peruntukan": "Sesuaikan|Khusus Profesi", "status": "lengkap|sebagian|kosong|na|terkunci",
  "persen": 100, "catatan": "239 baris · impor Excel" }
```

Status `na` dihitung dari `prodi.punya_tahap_profesi`, bukan disimpan manual.

### 03 · Form Tabel 5

Kasus terumit: 25 kolom, tahun relatif TS-6..TS, penanda (a)–(g) wajib tercetak.

Backend harus kirim:
- `periode.tahun_ts` (absolut)
- baris per angkatan: `{ tahun_masuk, jumlah[7], lulusan_sd_ts }`
- **bukan** label "TS-2" sebagai teks — label dihitung dari `tahun_ts` saat tampil

Varian tabel dipilih dari `prodi.jenjang`, bukan input manual.

### 05 · Ringkasan 8 Kriteria

Tiga blok, urutan penting: **butuh tindakan → sudah lengkap → tanpa tabel**.

Bentuk:
```json
{ "kode": "K3", "nama": "Penilaian",
  "tabel_total": 7, "tabel_lengkap": 1, "tabel_na": 4,
  "dokumen_total": 8, "dokumen_terunggah": 5,
  "temuan": ["Tabel 5 · varian belum dipilih"] }
```

**Aturan yang mengikat:** status kriteria **diturunkan dari tabelnya sendiri**, bukan dari
tabel lain. Kriteria masuk "butuh tindakan" bila `tabel_lengkap < tabel_total - tabel_na`
**atau** `dokumen_terunggah < dokumen_total`.

Jumlah harus tutup: `Σ tabel_total = 38`, `Σ tabel_lengkap = 25`,
`Σ tabel_na = 6`, `Σ dokumen_total = 79`. Kalau tidak tutup, data backend salah.

### 06 · Dokumen Pendukung

79 butir, dikelompokkan 8 kriteria. Per butir butuh:
`nomor`, `kriteria`, `nama_dokumen`, `file`, `status`, `PIC`, `tgl_kadaluarsa`, `versi`.

### 07 · Login

Split panel. Konteks prodi ditampilkan read-only setelah masuk — user tidak memilih prodi
di halaman login.

### 08 · Identitas UPPS & Prodi (F-01)

**Halaman paling menentukan.** Dua field di sini memfilter seluruh sistem:
`prodi.jenjang` dan `prodi.punya_tahap_profesi`.

Konsekuensi yang harus dihitung backend (dan ditampilkan di halaman):

| Kondisi | Tabel tidak berlaku | Target tabel |
|---|---|---|
| D-III, tanpa profesi | 4, 6, 7, 8, 11, 30 | 32 |
| S1 + Profesi | — | 38 |

Field: 8 UPPS + 10 prodi (lihat `03 - Spesifikasi Isi Sistem` di vault).

### 09 · Periode Akreditasi (F-02)

Menyimpan **tahun absolut**, menghitung label relatif.

```json
{ "tahun_ts": "2025/2026", "tahun_ss": null,
  "tgl_pengajuan": "2026-11-08", "tgl_berakhir": "2027-08-12" }
```

Backend harus bisa mengembalikan pemetaan `TS → 2025/2026`, `TS-6 → 2019/2020`, dst.
**Jangan simpan label "TS-2" di kolom database.**

### 10 · Dosen & Tendik (F-06)

24 orang mengisi 8 tabel. Aturan keras: **satu baris per orang**.

Dosen yang mengajar di dua prodi = satu record + relasi many-to-many ke prodi,
**bukan dua baris**. Kalau backend membuat dua baris, Tabel 15 dan 16 langsung tidak
sinkron dan ekspor terblokir.

```json
{ "nidn": "0112 0587 01", "nama": "...", "jenis": "tetap|tidak_tetap|tendik",
  "jabatan": "Lektor", "prodi": ["kebidanan","keperawatan"],
  "sertifikat_pendidik": true, "no_str": "...", "tgl_str": "..." }
```

### 11 · Keuangan (F-17)

Dua tabel: 33 penerimaan, 34 penggunaan. Semua nominal **juta rupiah**, bukan rupiah penuh.

**Aturan penutup yang mengikat:** persen penggunaan per tahun harus **tepat 100%**.

Catatan teknis yang penting: jangan validasi dari persen yang sudah dibulatkan. Hitung dari
nilai rupiah mentah, lalu tampilkan persen dengan **largest remainder** supaya kolom
benar-benar menutup 100,0%. Desain ini pernah kena bug 99,9% yang memicu blokir palsu.

### 12 · Kerja Sama (F-19)

Tabel 38 + penopang Tabel 30. Tiap wahana praktik harus punya dokumen kerja sama berlaku.

```json
{ "instansi": "RSJ Banda Aceh", "jenis": "RS Jiwa", "negara": "dalam|luar",
  "kegiatan": ["Pendidikan","Penelitian","PkM"],
  "mulai": "2021-06", "berakhir": "2024-06",
  "dokumen": "MoU", "dokumen_berlaku": false, "laporan": false }
```

Aturan lintas tabel: wahana tanpa dokumen berlaku → **peringatan**, bukan blokir.

---

## 5. Aturan lintas tabel (10 aturan)

Ini yang diuji asesor. Backend wajib mengevaluasi, bukan hanya menyimpan.

| Aturan | Tabel terlibat | Penanganan |
|---|---|---|
| Jumlah dosen tetap = baris nama di Tabel 16 | 15, 16 | peringatan, blokir ekspor bila beda |
| Jumlah lulusan = akumulasi lulusan tepat waktu | 3, 5 | peringatan |
| Total mahasiswa UPPS = jumlah semua prodi | 9, 10 | peringatan |
| Total mahasiswa = baris master mahasiswa aktif | 10, master | **blokir simpan** |
| Total penerimaan vs penggunaan dana | 33, 34 | peringatan |
| Jumlah persen penggunaan per tahun = 100% | 34 | **blokir simpan** |
| Dosen di beban kerja harus terdaftar | 17, 16, 19 | **blokir simpan** |
| Wahana praktik punya dokumen kerja sama | 30, 38 | peringatan |
| Mata ajar harus ada di master MK | 16, 19, 1 | peringatan |
| Tabel khusus profesi wajib bila punya_tahap_profesi | 4, 6, 7, 8, 11, 30 | wajib di checklist |

Bedakan **blokir** (tombol nonaktif + alasan) dan **peringatan** (tetap bisa lanjut).

---

## 6. Status kelengkapan & alur

```
kosong → sebagian → lengkap → terkunci
                              ↑
                        (pimpinan kunci)
+ tidak_berlaku  ← prodi tanpa tahap profesi
```

Setelah terkunci, setiap perubahan masuk riwayat: siapa, kapan, field, nilai lama, nilai baru.
Identitas (F-01) yang sudah diverifikasi terkunci lebih keras — perlu buka kunci oleh pimpinan.

---

## 7. Yang sudah digambar (13 frame)

| # | Layar | File |
|---|---|---|
| 01 | Dashboard | `screens/01-dashboard.png` |
| 02 | Data Dukung 38 Tabel | `screens/02-data-dukung.png` |
| 03 | Form Tabel 5 | `screens/03-form-tabel-5.png` |
| 04 | Sidebar — Kebidanan | `screens/04-sidebar-kebidanan.png` |
| 04b | Sidebar — Keperawatan | `screens/04b-sidebar-keperawatan.png` |
| 05 | Ringkasan 8 Kriteria | `screens/05-ringkasan-8-kriteria.png` |
| 06 | Dokumen Pendukung | `screens/06-dokumen-pendukung.png` |
| 07 | Login | `screens/07-login.png` |
| 08 | Identitas UPPS & Prodi | `screens/08-identitas-upps-prodi.png` |
| 09 | Periode Akreditasi | `screens/09-periode-akreditasi.png` |
| 10 | Dosen & Tendik | `screens/10-dosen-tendik.png` |
| 11 | Keuangan | `screens/11-keuangan.png` |
| 12 | Kerja Sama | `screens/12-kerja-sama.png` |

---

## 8. Yang belum digambar

Sisa 6 halaman sidebar + 2 halaman sistem:

| Layar | Kode | Catatan |
|---|---|---|
| Pengguna & Hak Akses | F-22 | 4 peran: admin UPPS, operator prodi, pimpinan, asesor |
| Mahasiswa | F-05 | **paling berat** — impor massal + sumber 6 tabel |
| Kurikulum & MK | F-07 | Tabel 1, 239 baris, wajib impor Excel |
| Sarana & Wahana | F-15 | lab + Tabel 30 wahana praktik |
| Pustaka & Jurnal | F-16 | Tabel 31–32 |
| Uji Kompetensi | F-09 | Tabel 7–8, CBT & OSCE |
| Survei & Tracer | F-10 | Tabel 12–14, butuh modul survei daring |
| Konsistensi | F-21 | panel aturan lintas tabel |
| Ekspor | F-20 | mesin template PDF/XLSX |

---

## 9. Keputusan desain & alasannya

Dicatat supaya tidak diubah tanpa tahu konsekuensinya.

1. **38 tabel jadi satu checklist, bukan 38 menu.** Sidebar harus bisa dipindai 3 detik.
2. **Tahun disimpan absolut, label dihitung.** Kalau disimpan sebagai teks, data harus diisi
   ulang tiap pengajuan akreditasi.
3. **Tabel "tak berlaku" ditandai, bukan disembunyikan.** Asesor harus lihat bahwa itu
   keputusan, bukan kelalaian.
4. **Tema light-only.** Proyektor + cetak hitam-putih.
5. **Status selalu label + warna.** Buta warna & fotokopi.
6. **Satu angka satu tempat.** Duplikasi angka = kebocoran layout.
7. **Aksi beda per state.** `Perbaiki` vs `Lengkapi` vs `Lihat` — jangan seragam.
8. **Dosen lintas prodi = satu baris.** Kalau dua baris, Tabel 15/16 langsung gagal.

---

## 10. Catatan teknis untuk implementasi

- **Angka tabular wajib.** Pakai `font-variant-numeric: tabular-nums`.
- **Bar chart pakai skala bersama**, jangan max per baris.
- **Persen harus menutup 100.** Validasi dari nilai mentah + largest remainder untuk tampilan.
- **Jangan hardcode label TS-n** di database.
- **Status turunan jangan disimpan ganda** — simpan fakta, hitung status.
- **Tabel 5 punya 4 varian** (Sarjana TS-6..TS, Spesialis 4/5/6 tahun SS-9..SS-16).
  Varian dipilih dari jenjang, bukan manual.

---

## 11. Tooling

Desain dibuat di **FigCraft** (open-source, Rust) lewat MCP, jadi agent bisa menggambar
langsung ke file yang sama sementara manusia melihat canvas-nya.

- `mockup.figcraft` — buka di FigCraft.app
- `rules/` — script Python generator. Tiap layar satu file:
  `dash_v3.py`, `ringkasan8_v2.py`, `identitas.py`, `periode.py`, `dosen.py`,
  `keuangan.py`, `kerjasama.py`
- Semua script menurunkan angka dari satu sumber data + `assert` yang memastikan total
  tutup. Kalau assert gagal, script berhenti sebelum menggambar.

Cara menjalankan ulang salah satu layar:
```bash
python3 rules/keuangan.py    # butuh FigCraft jalan dengan --control 7979
```

---

## 12. Pertanyaan yang masih terbuka untuk dospen

1. **Jenjang yang diakreditasi** — D-III saja, atau Sarjana + Profesi? Menentukan 32 vs 38
   tabel wajib.
2. **Versi instrumen** — file yang diberikan identik untuk dua prodi, perlu dipastikan ini
   template versi mana.
3. **Ada instrumen naratif?** File ini hanya tabel data dukung; kalau ada uraian kualitatif
   per kriteria, sistem butuh modul teks tambahan.
4. **Cakupan institusi** — satu UPPS atau multi-institusi?
5. **Sumber data eksisting** — apakah mahasiswa & dosen bisa diimpor dari SIAKAD/PDDikti?

Selama pertanyaan 1 belum dijawab, desain memakai asumsi **D-III tanpa tahap profesi**
(target 32 tabel). Kalau jawabannya berubah, cukup ubah `jenjang` + `punya_tahap_profesi`
di F-01 — semua angka turunan menyesuaikan.

---

## Terkait

- [[00 - Overview]] — ringkasan proyek
- [[02 - Struktur Instrumen]] — isi 41 sheet, 38 tabel, 79 butir
- [[03 - Spesifikasi Isi Sistem]] — 14 master data, 243 field, aturan validasi
- [[04 - Jebakan dan Catatan]] — yang bikin salah paham instrumen
- [[05 - Log 8 November 2026]] — rancangan sidebar & tema awal
