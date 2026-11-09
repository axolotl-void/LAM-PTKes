/* Dump data tabel & dokumen ke JSON untuk seed SQLite.
   Satu sumber: impor dari modul halaman, jangan ketik ulang angka di Python.

   Jalankan: node dump-seed.mjs > seed.json
*/
import { KEDALUWARSA } from './assets/js/pages/dokumen.js';
import { KRITERIA, INSTRUMEN, PENGGUNA, IZIN, MATRIX, TABEL } from './assets/js/data.js';

const out = {
  instrumen: {
    total_tabel: INSTRUMEN.totalTabel,
    kriteria: INSTRUMEN.kriteria,
    total_dokumen: INSTRUMEN.totalDokumen,
    tabel_na: INSTRUMEN.tabelNA.map(String),
  },
  kriteria: KRITERIA.map((k) => ({
    kode: k.kode, nama: k.nama, singkat: k.singkat,
    butir: k.butir, temuan: k.temuan,
  })),
  // TABEL sudah memuat 38 baris dengan kriteria asli. `profesi` menentukan
  // berlaku: prodi UBBG punya tahap profesi, jadi semuanya berlaku.
  tabel: TABEL.map((t) => ({
    kode: t.no, judul: t.judul, kode_kriteria: t.krit,
    status: t.status, field_terisi: t.terisi, field_total: t.total,
    berlaku: 1,
  })),
  kedaluwarsa: KEDALUWARSA.map(([nama, kode_kriteria, berlaku_sampai]) => ({
    nama, kode_kriteria, berlaku_sampai,
  })),
  pengguna: PENGGUNA.map(([nama, peran, prodi, status, aktif_terakhir]) => ({
    nama, peran, prodi, status, aktif_terakhir,
  })),
  izin: IZIN,
  matrix: MATRIX,
};

process.stdout.write(JSON.stringify(out, null, 1));
