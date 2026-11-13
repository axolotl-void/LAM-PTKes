# LAM-PTKes — UI

App statis: HTML + CSS + ES modules. **Tanpa build step, tanpa dependency.**

Buka `index.html` lewat server apa pun (ES module butuh http, bukan `file://`).

```bash
python3 -m http.server 8123     # buka http://127.0.0.1:8123
node --test test/data.test.js   # 10 cek: angka tutup, semua halaman render
```

## Struktur

```
index.html              shell kosong
assets/css/app.css      token tema + komponen
assets/js/data.js       SATU sumber angka + validate()
assets/js/icons.js      path ikon 16×16 (sama dengan versi FigCraft)
assets/js/views.js      satu fungsi per halaman
assets/js/app.js        router hash + boot
test/data.test.js       gerbang: gagal = app tidak render
```

## Aturan

1. **Tidak ada angka diketik di view.** Semua turunan dari `D` di `data.js`.
2. `validate()` jalan sebelum render. Gagal → layar error, bukan angka salah.
3. Halaman baru: tambah data di `data.js`, fungsi di `views.js`, daftar di `NAV`
   (views.js) dan `PAGES` (app.js).

## Tema

Token di `:root`. Indigo `#2F2A95` struktural (nav aktif, tombol utama), oranye
`#FF8200` aksen langka. Di atas oranye pakai teks `--on-orn` `#2B1600` — putih
cuma 2,49:1 dan gagal WCAG AA.

## Yang belum ada

- Backend/API. Tombol impor & ekspor belum tersambung.
- 11 halaman masih placeholder: Identitas, Periode, Dosen, Keuangan, Kerja Sama,
  Ringkasan, Data Dukung, Dokumen, Konsistensi, Uji Kompetensi, Survei.
- Login & hak akses belum ditegakkan (memang harus di server, bukan di klien).
