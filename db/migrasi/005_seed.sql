-- LAM-PTKes — 005: data awal yang dibutuhkan agar aplikasi bisa dibuka
--
-- Isinya HANYA identitas institusi, 2 prodi, periode, dan 1 akun per peran.
-- TIDAK ada data akreditasi contoh: angka karangan di sistem akreditasi lebih
-- berbahaya daripada halaman kosong — orang bisa lupa itu karangan.
--
-- Sandi semua akun: demo1234 (hash di bawah = password_hash('demo1234')).
-- WAJIB diganti sebelum dipasang di server kampus.

SET NAMES utf8mb4;

INSERT INTO upps (id, nama_pt, nama_upps, alamat, kota, telepon, email, website, narahubung)
VALUES (1,
  'Universitas Bina Bangsa Getsempena',
  'Fakultas Ilmu Kesehatan',
  'Jl. Tanggul Krueng Aceh No. 34, Rukoh, Darussalam',
  'Banda Aceh', '0651-7551234', 'fik@bbg.ac.id', 'https://bbg.ac.id',
  'Bagian Akademik FIK');

INSERT INTO prodi (id, upps_id, nama_ps, jenjang, bidang, punya_tahap_profesi,
                   nama_profesi_lulusan, lama_studi_semester, nama_uji_kompetensi,
                   peringkat_akreditasi, tgl_akreditasi, tgl_berakhir_akreditasi)
VALUES
  (1, 1, 'Kebidanan', 'Sarjana', 'Kebidanan', 1, 'Bidan', 8, 'UKOM Bidan',
   'Unggul', '2026-09-26', '2030-09-26'),
  (2, 1, 'Keperawatan', 'Sarjana', 'Keperawatan', 1, 'Ners', 8, 'Uji Kompetensi Ners',
   'Baik Sekali', '2025-02-28', '2030-02-28');

-- TS 2025/2026 untuk dua prodi. Semua label TS-n dihitung dari sini.
INSERT INTO periode (id, prodi_id, tahun_ts, tahun_ss, tgl_pengajuan, aktif)
VALUES (1, 1, 2025, 2020, '2026-11-30', 1),
       (2, 2, 2025, 2020, '2026-11-30', 1);

-- Akun: satu peran per akun, sengaja. Peran ganda mustahil di skema.
-- Sandi: demo1234
INSERT INTO pengguna (email, nama, sandi, peran_kode, prodi_id) VALUES
  ('siti@bbg.ac.id',      'Siti Rahmawati, S.ST., M.Keb',      '$2y$12$E35d9GWSREN8p8aHGp/tSO9Or8OnI8c4qIbeDBypJqDoMsNT5CE/i', 'OPERATOR',    1),
  ('nurhayati@bbg.ac.id', 'Nurhayati, S.Kep., M.Kep',          '$2y$12$E35d9GWSREN8p8aHGp/tSO9Or8OnI8c4qIbeDBypJqDoMsNT5CE/i', 'OPERATOR',    2),
  ('maimunah@bbg.ac.id',  'Dr. Maimunah, M.Keb',               '$2y$12$E35d9GWSREN8p8aHGp/tSO9Or8OnI8c4qIbeDBypJqDoMsNT5CE/i', 'VERIFIKATOR', 1),
  ('yusrizal@bbg.ac.id',  'Yusrizal, S.Kep., Ns., M.Kep',      '$2y$12$E35d9GWSREN8p8aHGp/tSO9Or8OnI8c4qIbeDBypJqDoMsNT5CE/i', 'VERIFIKATOR', 2),
  ('zulfikar@bbg.ac.id',  'Dr. Ir. Zulfikar, M.Pd',            '$2y$12$E35d9GWSREN8p8aHGp/tSO9Or8OnI8c4qIbeDBypJqDoMsNT5CE/i', 'ADMIN',       NULL),
  ('zahriah@bbg.ac.id',   'Prof. Dr. Cut Zahriah, M.Pd',       '$2y$12$E35d9GWSREN8p8aHGp/tSO9Or8OnI8c4qIbeDBypJqDoMsNT5CE/i', 'PIMPINAN',    NULL);

SELECT CONCAT('seed: ', (SELECT COUNT(*) FROM prodi), ' prodi, ',
  (SELECT COUNT(*) FROM periode), ' periode, ',
  (SELECT COUNT(*) FROM pengguna), ' akun') AS hasil;
