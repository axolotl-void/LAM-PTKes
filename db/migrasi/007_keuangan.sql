-- LAM-PTKES — 007: keuangan (F-17, Tabel 30–31)
--
-- Kenapa dipisah dua tabel, bukan satu baris panjang: instrumen menanyakan dua
-- hal berbeda. `keuangan_terima` = uang masuk per sumber; `keuangan_guna` =
-- uang keluar per jenis penggunaan. Satu tabel gabungan memaksa kolom NULL
-- dan membuat "total penerimaan" bisa keliru menjumlahkan pengeluaran.
--
-- Tidak ada kolom `total` yang disimpan: total dijumlahkan dari baris. Kalau
-- disimpan, cepat atau lambat angkanya tidak sama dengan rinciannya.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS keuangan_terima (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  prodi_id      INT UNSIGNED NOT NULL,
  tahun         SMALLINT UNSIGNED NOT NULL,
  sumber        VARCHAR(120) NOT NULL,
  jumlah_juta   DECIMAL(14,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_terima (prodi_id, tahun, sumber),
  CONSTRAINT fk_terima_prodi FOREIGN KEY (prodi_id) REFERENCES prodi (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS keuangan_guna (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  prodi_id      INT UNSIGNED NOT NULL,
  tahun         SMALLINT UNSIGNED NOT NULL,
  jenis         VARCHAR(120) NOT NULL,
  jumlah_juta   DECIMAL(14,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_guna (prodi_id, tahun, jenis),
  CONSTRAINT fk_guna_prodi FOREIGN KEY (prodi_id) REFERENCES prodi (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tidak perlu baris di tabel `aksi`: keuangan hanya baca/tulis biasa,
-- tidak menambah aksi khusus.
