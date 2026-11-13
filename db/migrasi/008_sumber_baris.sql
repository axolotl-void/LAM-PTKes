-- LAM-PTKES — 008: penanda baris hasil tarikan master
--
-- KENAPA PERLU: Tabel 16, 19, 29, 30, 31 sebagian besar kolomnya `Master` —
-- datanya sudah ada di master dosen/lab/wahana/pustaka. Kalau operator harus
-- mengetik ulang, isinya akan berbeda dari master dan asesor menemukan dua
-- angka berbeda untuk hal yang sama.
--
-- `sumber_id` = id baris di tabel master asalnya. Dipakai untuk:
--   1. tarik ulang TIDAK menduplikasi baris (idempoten)
--   2. isian manual operator tidak tertimpa saat tarik ulang
--   3. ketahuan baris mana yang berasal dari master, mana yang diketik tangan
--
-- NULL = baris diketik operator sendiri (bukan hasil tarikan).

ALTER TABLE baris_tabel
  ADD COLUMN sumber_tabel VARCHAR(32) NULL AFTER tabel_kode,
  ADD COLUMN sumber_id INT UNSIGNED NULL AFTER sumber_tabel,
  ADD UNIQUE KEY uq_baris_sumber (periode_id, prodi_id, tabel_kode, sumber_tabel, sumber_id);
