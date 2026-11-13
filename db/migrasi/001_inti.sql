-- LAM-PTKes — 001: inti sistem
-- Bagian yang TIDAK bergantung versi instrumen: akun, peran, izin, sesi,
-- nonce SSO, audit, dan entitas master. Tabel data dukung (T-01..T-38)
-- menyusul di 003 setelah versi instrumen dikonfirmasi dospen.
--
-- Target: MySQL 8.0 / MariaDB 10.4+ (cPanel). InnoDB, utf8mb4.
-- Semua waktu disimpan DATETIME waktu lokal server, bukan UTC: satu server,
-- satu zona waktu, dan laporan akreditasi memakai tanggal lokal.

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ─────────────────────────────────────────────────────────────────────────
-- UPPS & PROGRAM STUDI
-- Satu UPPS, dua prodi. `punya_tahap_profesi` menentukan 6 tabel khusus
-- profesi wajib atau tidak — ini atribut prodi, bukan teks bebas.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE upps (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_pt       VARCHAR(160) NOT NULL,
  nama_upps     VARCHAR(160) NOT NULL,
  alamat        VARCHAR(255) NULL,
  kota          VARCHAR(80)  NULL,
  telepon       VARCHAR(40)  NULL,
  email         VARCHAR(120) NULL,
  website       VARCHAR(120) NULL,
  narahubung    VARCHAR(120) NULL,
  dibuat        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prodi (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  upps_id                INT UNSIGNED NOT NULL,
  nama_ps                VARCHAR(120) NOT NULL,
  jenjang                ENUM('D-III','Sarjana','Profesi','Sarjana Terapan','Magister','Spesialis') NOT NULL,
  bidang                 VARCHAR(80)  NOT NULL,
  punya_tahap_profesi    TINYINT(1)   NOT NULL DEFAULT 0,
  nama_profesi_lulusan   VARCHAR(80)  NULL,
  lama_studi_semester    TINYINT UNSIGNED NULL,
  nama_uji_kompetensi    VARCHAR(120) NULL,
  peringkat_akreditasi   VARCHAR(40)  NULL,
  tgl_akreditasi         DATE NULL,
  tgl_berakhir_akreditasi DATE NULL,
  aktif                  TINYINT(1) NOT NULL DEFAULT 1,
  dibuat                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prodi_nama (upps_id, nama_ps),
  CONSTRAINT fk_prodi_upps FOREIGN KEY (upps_id) REFERENCES upps(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Periode akreditasi. `tahun_ts` satu-satunya sumber label TS-n / SS-n.
-- Tahun absolut disimpan; label dihitung saat tampil. Kalau TS diganti,
-- data tidak berubah — hanya labelnya.
CREATE TABLE periode (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id      INT UNSIGNED NOT NULL,
  tahun_ts      SMALLINT UNSIGNED NOT NULL,          -- mis. 2025 untuk TS 2025/2026
  tahun_ss      SMALLINT UNSIGNED NULL,              -- tahun mulai studi, untuk label SS-n
  tgl_pengajuan DATE NULL,
  catatan       VARCHAR(255) NULL,
  aktif         TINYINT(1) NOT NULL DEFAULT 1,
  dibuat        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_periode (prodi_id, tahun_ts),
  CONSTRAINT fk_periode_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id),
  CONSTRAINT ck_periode_ts CHECK (tahun_ts BETWEEN 2000 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- AKUN, PERAN, IZIN
-- Peran disimpan sebagai tabel, bukan ENUM: matriks izin harus bisa diubah
-- tanpa ALTER TABLE, dan 4 peran itu keputusan bisnis, bukan tipe data.
-- Matriks izin satu sumber untuk server dan UI.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE peran (
  kode          VARCHAR(32) NOT NULL,                -- OPERATOR, VERIFIKATOR, ADMIN, PIMPINAN
  nama          VARCHAR(64) NOT NULL,
  PRIMARY KEY (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aksi (
  kode          VARCHAR(32) NOT NULL,                -- ISI, IMPOR, VERIFIKASI, BUKA_KUNCI, KELOLA_PENGGUNA, EKSPOR
  nama          VARCHAR(64) NOT NULL,
  PRIMARY KEY (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- akses: 0 tidak boleh, 1 boleh, 2 hanya lihat
CREATE TABLE izin (
  peran_kode    VARCHAR(32) NOT NULL,
  aksi_kode     VARCHAR(32) NOT NULL,
  akses         TINYINT NOT NULL,
  PRIMARY KEY (peran_kode, aksi_kode),
  CONSTRAINT fk_izin_peran FOREIGN KEY (peran_kode) REFERENCES peran(kode),
  CONSTRAINT fk_izin_aksi  FOREIGN KEY (aksi_kode)  REFERENCES aksi(kode),
  CONSTRAINT ck_izin_akses CHECK (akses BETWEEN 0 AND 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satu akun satu peran. Peran ganda sengaja MUSTAHIL di skema, bukan cuma
-- dilarang di kode: aturan "pengisi tidak boleh menyetujui pekerjaannya
-- sendiri" jadi tidak bisa dilanggar lewat data. Admin UPPS memegang
-- keduanya lewat izin, bukan dua peran.
CREATE TABLE pengguna (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(160) NOT NULL,               -- dari SSO SPMI
  nama          VARCHAR(160) NOT NULL,
  sandi         VARCHAR(255) NULL,                   -- password_hash(); NULL = hanya SSO
  peran_kode    VARCHAR(32) NOT NULL,
  prodi_id      INT UNSIGNED NULL,                   -- NULL = semua prodi (Admin, Pimpinan)
  aktif         TINYINT(1) NOT NULL DEFAULT 1,
  dibuat        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  masuk_terakhir DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pengguna_email (email),
  CONSTRAINT fk_pengguna_peran FOREIGN KEY (peran_kode) REFERENCES peran(kode),
  CONSTRAINT fk_pengguna_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sesi (
  token         CHAR(64) NOT NULL,                   -- hash sha256 dari token cookie
  pengguna_id   INT UNSIGNED NOT NULL,
  prodi_id      INT UNSIGNED NULL,                   -- prodi yang sedang dibuka
  dibuat        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  kedaluwarsa   DATETIME NOT NULL,
  ip            VARCHAR(45) NULL,
  PRIMARY KEY (token),
  KEY ix_sesi_pengguna (pengguna_id),
  CONSTRAINT fk_sesi_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- SSO DARI SPMI
-- Nonce disimpan supaya satu tautan SSO tidak bisa dipakai dua kali.
-- Tanpa tabel ini, "tanpa login ulang" berarti "siapa pun yang pernah
-- menyalin URL bisa masuk sebagai orang itu".
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE sso_nonce (
  nonce         CHAR(32) NOT NULL,                   -- bin2hex(16 byte)
  email         VARCHAR(160) NOT NULL,
  dipakai       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nonce),
  KEY ix_nonce_waktu (dipakai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- AUDIT
-- Satu baris per perubahan field. Menyimpan nilai lama DAN baru sebagai teks
-- supaya bisa dibaca tanpa tahu tipe kolom asalnya.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE audit (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  waktu         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  pengguna_id   INT UNSIGNED NULL,                   -- NULL = sistem (seed/migrasi)
  prodi_id      INT UNSIGNED NULL,
  aksi          VARCHAR(32) NOT NULL,                -- isi, kunci, buka_kunci, impor, ubah_akun
  entitas       VARCHAR(40) NOT NULL,                -- tabel, dokumen, pengguna, master
  entitas_id    VARCHAR(40) NULL,
  field         VARCHAR(80) NULL,
  nilai_lama    TEXT NULL,
  nilai_baru    TEXT NULL,
  PRIMARY KEY (id),
  KEY ix_audit_waktu (waktu),
  KEY ix_audit_entitas (entitas, entitas_id),
  CONSTRAINT fk_audit_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- MASTER DATA — 13 entitas, 103 field (docx §3.1–3.13)
-- Semua master punya prodi_id kecuali upps. Dosen boleh lintas prodi lewat
-- tabel penghubung, supaya satu orang tetap satu baris.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE mahasiswa (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id       INT UNSIGNED NOT NULL,
  nim            VARCHAR(24) NOT NULL,
  nama           VARCHAR(160) NOT NULL,
  tahun_masuk    SMALLINT UNSIGNED NOT NULL,
  jalur_masuk    ENUM('Reguler','Transfer','Asing') NOT NULL DEFAULT 'Reguler',
  tahap          ENUM('Akademik','Profesi') NOT NULL DEFAULT 'Akademik',
  status         ENUM('Aktif','Lulus','DO','Cuti') NOT NULL DEFAULT 'Aktif',
  ipk            DECIMAL(3,2) NULL,
  tanggal_lulus  DATE NULL,
  tahun_lulus    SMALLINT UNSIGNED NULL,
  dibuat         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mhs_nim (nim),
  KEY ix_mhs_prodi_status (prodi_id, status),
  CONSTRAINT fk_mhs_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id),
  CONSTRAINT ck_mhs_ipk CHECK (ipk IS NULL OR (ipk >= 0 AND ipk <= 4))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dosen (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nidn_nidk_nup      VARCHAR(32) NULL,
  nama               VARCHAR(160) NOT NULL,
  jenis              ENUM('Dosen Tetap','Dosen Tidak Tetap','Tendik') NOT NULL,
  jabatan_fungsional ENUM('Belum Memiliki','Asisten Ahli','Lektor','Lektor Kepala','Guru Besar') NULL,
  gelar_akademik     VARCHAR(120) NULL,
  tanggal_lahir      DATE NULL,
  sertifikat_pendidik TINYINT(1) NOT NULL DEFAULT 0,
  profesi            ENUM('Bidan','Perawat','Ners') NULL,
  no_str             VARCHAR(60) NULL,
  tanggal_str        DATE NULL,
  tahap_mengajar     ENUM('Akademik','Profesi','Keduanya') NULL,
  email              VARCHAR(160) NULL,
  dibuat             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dosen_nidn (nidn_nidk_nup)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dosen lintas prodi: satu orang, banyak prodi.
CREATE TABLE dosen_prodi (
  dosen_id      INT UNSIGNED NOT NULL,
  prodi_id      INT UNSIGNED NOT NULL,
  PRIMARY KEY (dosen_id, prodi_id),
  CONSTRAINT fk_dp_dosen FOREIGN KEY (dosen_id) REFERENCES dosen(id) ON DELETE CASCADE,
  CONSTRAINT fk_dp_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dosen_pendidikan (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dosen_id      INT UNSIGNED NOT NULL,
  jenjang       VARCHAR(40) NOT NULL,
  bidang        VARCHAR(120) NULL,
  asal_pt       VARCHAR(160) NULL,
  tahun_lulus   SMALLINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_dpdd_dosen (dosen_id),
  CONSTRAINT fk_dpdd_dosen FOREIGN KEY (dosen_id) REFERENCES dosen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mata_kuliah (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id       INT UNSIGNED NOT NULL,
  kode_mk        VARCHAR(32) NOT NULL,
  nama_mk        VARCHAR(160) NOT NULL,
  sks_kuliah     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sks_praktik    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  cpl            TEXT NULL,
  aspek_sikap    TINYINT(1) NOT NULL DEFAULT 0,
  aspek_pengetahuan TINYINT(1) NOT NULL DEFAULT 0,
  aspek_ket_umum TINYINT(1) NOT NULL DEFAULT 0,
  aspek_ket_khusus TINYINT(1) NOT NULL DEFAULT 0,
  ada_rps        TINYINT(1) NOT NULL DEFAULT 0,
  lokasi_praktik VARCHAR(160) NULL,
  tahap          ENUM('Akademik','Profesi') NOT NULL DEFAULT 'Akademik',
  PRIMARY KEY (id),
  UNIQUE KEY uq_mk (prodi_id, kode_mk),
  CONSTRAINT fk_mk_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wahana (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id           INT UNSIGNED NOT NULL,
  nama               VARCHAR(160) NOT NULL,
  kualifikasi        VARCHAR(120) NULL,
  variasi_kasus      VARCHAR(255) NULL,
  jumlah_pembimbing  TINYINT UNSIGNED NULL,
  dokumen_kerja_sama VARCHAR(255) NULL,              -- dasar Tabel 38
  berlaku_sampai     DATE NULL,                      -- kedaluwarsa = peringatan, bukan hapus
  PRIMARY KEY (id),
  CONSTRAINT fk_wahana_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stase bergantung pada wahana DAN dosen, jadi dibuat setelah keduanya.
CREATE TABLE stase (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id      INT UNSIGNED NOT NULL,
  nama_stase    VARCHAR(120) NOT NULL,
  wahana_id     INT UNSIGNED NULL,
  durasi_minggu TINYINT UNSIGNED NULL,
  pembimbing_id INT UNSIGNED NULL,
  kuota         SMALLINT UNSIGNED NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_stase_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id),
  CONSTRAINT fk_stase_wahana FOREIGN KEY (wahana_id) REFERENCES wahana(id),
  CONSTRAINT fk_stase_pembimbing FOREIGN KEY (pembimbing_id) REFERENCES dosen(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE laboratorium (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id      INT UNSIGNED NOT NULL,
  nama_lab      VARCHAR(120) NOT NULL,
  alat          VARCHAR(160) NOT NULL,
  jumlah_unit   SMALLINT UNSIGNED NULL,
  kepemilikan   ENUM('SD','SW') NULL,                -- SD = sendiri, SW = sewa/pinjam
  kondisi       ENUM('Baik','Rusak Ringan','Rusak Berat') NULL,
  ada_logbook   TINYINT(1) NOT NULL DEFAULT 0,
  jam_pakai_per_minggu TINYINT UNSIGNED NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_lab_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pustaka (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id      INT UNSIGNED NOT NULL,
  jenis         ENUM('Buku','Jurnal','E-book','Prosiding') NOT NULL,
  judul         VARCHAR(255) NOT NULL,
  jumlah_judul  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  jumlah_copy   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  tahun         SMALLINT UNSIGNED NULL,
  nomor         VARCHAR(60) NULL,                    -- ISSN/ISBN/volume
  PRIMARY KEY (id),
  CONSTRAINT fk_pustaka_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE survei (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id      INT UNSIGNED NOT NULL,
  jenis         ENUM('Mahasiswa','Pengguna Lulusan','Alumni') NOT NULL,
  aspek         VARCHAR(160) NULL,
  periode       SMALLINT UNSIGNED NULL,
  skor_1        TINYINT UNSIGNED NULL,
  skor_2        TINYINT UNSIGNED NULL,
  skor_3        TINYINT UNSIGNED NULL,
  skor_4        TINYINT UNSIGNED NULL,
  tindak_lanjut VARCHAR(255) NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_survei_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tracer (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id      INT UNSIGNED NOT NULL,
  tahun_lulus   SMALLINT UNSIGNED NOT NULL,
  jumlah_lulusan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  terlacak      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  tunggu_lt3    SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- < 3 bulan
  tunggu_3_6    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  tunggu_gt6    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tracer (prodi_id, tahun_lulus),
  CONSTRAINT fk_tracer_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id),
  CONSTRAINT ck_tracer_terlacak CHECK (terlacak <= jumlah_lulusan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE uji_kompetensi (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id      INT UNSIGNED NOT NULL,
  jenis         ENUM('CBT','OSCE') NOT NULL,
  periode       SMALLINT UNSIGNED NOT NULL,
  selesai_stase SMALLINT UNSIGNED NULL,
  peserta_total SMALLINT UNSIGNED NULL,
  ft_peserta    SMALLINT UNSIGNED NULL,
  ft_lulus      SMALLINT UNSIGNED NULL,
  rt_peserta    SMALLINT UNSIGNED NULL,
  rt_lulus      SMALLINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ukom (prodi_id, jenis, periode),
  CONSTRAINT fk_ukom_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id),
  CONSTRAINT ck_ukom_ft CHECK (ft_lulus IS NULL OR ft_peserta IS NULL OR ft_lulus <= ft_peserta),
  CONSTRAINT ck_ukom_rt CHECK (rt_lulus IS NULL OR rt_peserta IS NULL OR rt_lulus <= rt_peserta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- SEED: peran, aksi, matriks izin
-- Matriks ini SAMA dengan yang sudah diuji di prototipe. Kalau berubah,
-- ubah di sini dan di uji — jangan di dua tempat.
-- ─────────────────────────────────────────────────────────────────────────
INSERT INTO peran (kode, nama) VALUES
  ('OPERATOR',    'Operator Prodi'),
  ('VERIFIKATOR', 'Verifikator'),
  ('ADMIN',       'Admin UPPS'),
  ('PIMPINAN',    'Pimpinan');

INSERT INTO aksi (kode, nama) VALUES
  ('ISI',              'Isi'),
  ('IMPOR',            'Impor'),
  ('VERIFIKASI',       'Verifikasi'),
  ('BUKA_KUNCI',       'Buka kunci'),
  ('KELOLA_PENGGUNA',  'Kelola pengguna'),
  ('EKSPOR',           'Ekspor');

INSERT INTO izin (peran_kode, aksi_kode, akses) VALUES
  ('OPERATOR','ISI',1),             ('OPERATOR','IMPOR',1),            ('OPERATOR','VERIFIKASI',0),
  ('OPERATOR','BUKA_KUNCI',0),      ('OPERATOR','KELOLA_PENGGUNA',0),  ('OPERATOR','EKSPOR',1),
  ('VERIFIKATOR','ISI',0),          ('VERIFIKATOR','IMPOR',0),         ('VERIFIKATOR','VERIFIKASI',1),
  ('VERIFIKATOR','BUKA_KUNCI',0),   ('VERIFIKATOR','KELOLA_PENGGUNA',0),('VERIFIKATOR','EKSPOR',1),
  ('ADMIN','ISI',1),                ('ADMIN','IMPOR',1),               ('ADMIN','VERIFIKASI',1),
  ('ADMIN','BUKA_KUNCI',1),         ('ADMIN','KELOLA_PENGGUNA',1),     ('ADMIN','EKSPOR',1),
  ('PIMPINAN','ISI',2),             ('PIMPINAN','IMPOR',2),            ('PIMPINAN','VERIFIKASI',2),
  ('PIMPINAN','BUKA_KUNCI',0),      ('PIMPINAN','KELOLA_PENGGUNA',0),  ('PIMPINAN','EKSPOR',1);
