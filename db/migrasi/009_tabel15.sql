-- LAM-PTKES — 009: Tabel 15 dibentuk ulang sebagai matriks
--
-- MASALAH: Tabel 15 dibangun 4 field (Program Studi, Mahasiswa reguler, Non
-- reguler, Asing). Itu KELIRU. Instrumen aslinya matriks:
--
--   baris  = 5 jabatan fungsional (Belum Memiliki, Asisten Ahli, Lektor,
--            Lektor Kepala, Guru Besar/Profesor)
--          + 3 pendidikan tertinggi (Profesi, Sp-1/S-2, Sp-2/S-3)
--   kolom  = satu per prodi + Total UPPS
--
-- Catatan instrumen yang menentukan cara hitungnya:
--   1) Satu dosen tetap BISA terdaftar di lebih dari satu prodi.
--   2) Kolom (7) Total: setiap dosen tetap dihitung SATU KALI.
--
-- Artinya kolom prodi boleh berjumlah lebih besar dari total — dan itu benar,
-- bukan salah hitung. Tabel ini seluruhnya HITUNG dari master dosen; tidak ada
-- yang diketik. Karena itu tidak ada kolom isian sama sekali.

SET NAMES utf8mb4;

-- Field lama tabel 15 dihapus: bentuknya salah, dan tidak ada data yang perlu
-- diselamatkan (belum pernah dipakai mengisi).
DELETE FROM sel WHERE baris_id IN (SELECT id FROM baris_tabel WHERE tabel_kode = '15');
DELETE FROM baris_tabel WHERE tabel_kode = '15';
DELETE FROM spec_field WHERE tabel_kode = '15';

INSERT INTO spec_field (tabel_kode, urut, label, kunci, tipe, asal, kelompok, pilih, keterangan) VALUES
  ('15', 1, 'No', 'no', 'Teks', 'Hitung', '', 'banyak',
   'Nomor urut baris matriks (A1..A5, B1..B3)'),
  ('15', 2, 'Hal', 'hal', 'Teks', 'Hitung', '', 'banyak',
   'Jabatan fungsional / pendidikan tertinggi'),
  ('15', 3, 'Jumlah dosen', 'jumlah_dosen', 'angka', 'Hitung', '', 'banyak',
   'Dihitung per prodi dari master dosen. Satu dosen bisa masuk lebih dari satu kolom prodi (catatan instrumen 1).'),
  ('15', 4, 'Total di UPPS', 'total_upps', 'angka', 'Hitung', '', 'banyak',
   'Setiap dosen dihitung SEKALI walau mengajar di beberapa prodi (catatan instrumen 2)')
ON DUPLICATE KEY UPDATE label = VALUES(label), tipe = VALUES(tipe), asal = VALUES(asal),
  keterangan = VALUES(keterangan);

UPDATE tabel_dukung SET
  judul = '15. Tabel 15. Dosen Tetap pada Unit Pengelola Program Studi',
  varian = 'matriks'
WHERE kode = '15';
