-- LAM-PTKES — 010: bimbingan tugas akhir (Tabel 21) + bentuk ulang spec-nya
--
-- BENTUK INSTRUMEN (13 kolom, bukan 7):
--   No | Nama Dosen | Jenjang Pendidikan | Jabatan Akademik
--   | Jumlah Mahasiswa TS-2 | TS-1 | TS
--   | Jumlah Pertemuan TS-2 | TS-1 | TS
--   | Lama Penyelesaian (bulan) TS-2 | TS-1 | TS
--   + baris Jumlah dan Rata-rata
--
-- Spec lama salah bentuk: satu field "Jumlah mahasiswa TS-2 / TS-1 / TS"
-- menaungi tiga kolom sekaligus, sehingga tiga angka tidak bisa dibedakan.
-- Sama seperti Tabel 22, labelnya harus dipecah per kolom.
--
-- PERTEMUAN dan LAMA PENYELESAIAN tidak bisa dihitung tanpa data bimbingan,
-- dan skema belum punya tempatnya. Jadi dibuat tabel `bimbingan`:
-- satu baris = satu mahasiswa dibimbing satu dosen pada satu tahun akademik.
-- Dari situ jumlah mahasiswa, pertemuan, dan lama penyelesaian bisa dihitung
-- per dosen per tahun, bukan diketik ulang.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bimbingan (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  prodi_id       INT UNSIGNED NOT NULL,
  dosen_id       INT UNSIGNED NOT NULL,
  mahasiswa_id   INT UNSIGNED NULL,
  tahun_akademik SMALLINT UNSIGNED NOT NULL,       -- 2025 = TA 2025/2026
  topik          VARCHAR(255) NULL,
  jumlah_pertemuan SMALLINT UNSIGNED NULL,
  mulai          DATE NULL,
  selesai        DATE NULL,
  status         ENUM('Berjalan','Selesai','Gagal') NOT NULL DEFAULT 'Berjalan',
  KEY ix_bimb_dosen (prodi_id, dosen_id, tahun_akademik),
  KEY ix_bimb_mhs (mahasiswa_id),
  CONSTRAINT fk_bimb_prodi FOREIGN KEY (prodi_id) REFERENCES prodi (id) ON DELETE RESTRICT,
  CONSTRAINT fk_bimb_dosen FOREIGN KEY (dosen_id) REFERENCES dosen (id) ON DELETE RESTRICT,
  CONSTRAINT fk_bimb_mhs FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bentuk ulang spec Tabel 21. Tidak ada data yang perlu diselamatkan.
DELETE FROM sel WHERE baris_id IN (SELECT id FROM baris_tabel WHERE tabel_kode = '21');
DELETE FROM baris_tabel WHERE tabel_kode = '21';
DELETE FROM spec_field WHERE tabel_kode = '21';

INSERT INTO spec_field (tabel_kode, urut, label, kunci, tipe, asal, kelompok, pilih, keterangan) VALUES
  ('21', 1, 'No', 'no', 'Teks', 'Input', '', 'banyak', '-'),
  ('21', 2, 'Nama dosen pembimbing', 'nama_dosen_pembimbing', 'relasi', 'Master', '', 'banyak',
   'Dari master dosen; satu baris per dosen yang membimbing'),
  ('21', 3, 'Jenjang pendidikan', 'jenjang_pendidikan', 'Teks', 'Master', '', 'banyak',
   'Pendidikan tertinggi dosen, dari riwayat pendidikan'),
  ('21', 4, 'Jabatan akademik', 'jabatan_akademik', 'Teks', 'Master', '', 'banyak',
   'Jabatan fungsional dosen'),
  ('21', 5, 'Jumlah mahasiswa TS-2', 'jumlah_mahasiswa_ts_2', 'angka', 'Hitung', 'tahun_bimbingan', 'satu',
   'Dihitung dari tabel bimbingan'),
  ('21', 6, 'Jumlah mahasiswa TS-1', 'jumlah_mahasiswa_ts_1', 'angka', 'Hitung', 'tahun_bimbingan', 'satu',
   'Dihitung dari tabel bimbingan'),
  ('21', 7, 'Jumlah mahasiswa TS', 'jumlah_mahasiswa_ts', 'angka', 'Hitung', 'tahun_bimbingan', 'satu',
   'Dihitung dari tabel bimbingan'),
  ('21', 8, 'Jumlah pertemuan TS-2', 'jumlah_pertemuan_ts_2', 'angka', 'Hitung', 'tahun_pertemuan', 'satu',
   'Jumlah semua pertemuan pada TA tersebut'),
  ('21', 9, 'Jumlah pertemuan TS-1', 'jumlah_pertemuan_ts_1', 'angka', 'Hitung', 'tahun_pertemuan', 'satu',
   '-'),
  ('21', 10, 'Jumlah pertemuan TS', 'jumlah_pertemuan_ts', 'angka', 'Hitung', 'tahun_pertemuan', 'satu',
   '-'),
  ('21', 11, 'Lama penyelesaian TS-2 (bulan)', 'lama_ts_2', 'desimal', 'Hitung', 'tahun_lama', 'satu',
   'Rata-rata bulan dari mulai sampai selesai'),
  ('21', 12, 'Lama penyelesaian TS-1 (bulan)', 'lama_ts_1', 'desimal', 'Hitung', 'tahun_lama', 'satu',
   '-'),
  ('21', 13, 'Lama penyelesaian TS (bulan)', 'lama_ts', 'desimal', 'Hitung', 'tahun_lama', 'satu',
   '-');

UPDATE tabel_dukung SET varian = 'hitung' WHERE kode = '21';
