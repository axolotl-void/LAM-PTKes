-- LAM-PTKES — 006: mitra kerja sama (Tabel 38)
--
-- Kenapa tabel sendiri, bukan menumpang `wahana`: satu MoU bisa menaungi
-- beberapa wahana, dan satu wahana bisa punya lebih dari satu dokumen
-- (MoU induk + PKS teknis). `wahana.dokumen_kerja_sama` tetap ada sebagai
-- teks ringkas, tapi sumber kebenaran masa berlaku ada di sini.
--
-- Rujukan instrumen: Tabel 38 "Kerja sama UPPS yang relevan dengan program
-- studi". Aturan konsistensi R08 membandingkan wahana (Tabel 30) dengan
-- dokumen kerja sama (Tabel 38).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS mitra (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  upps_id        INT UNSIGNED NOT NULL,
  nama           VARCHAR(160) NOT NULL,
  jenis          ENUM('Rumah Sakit','Rumah Sakit Jiwa','Puskesmas','Klinik','BPM','PMB','Industri','PT Mitra','Pemerintah','Lainnya') NOT NULL,
  lingkup        ENUM('Pendidikan','Penelitian','PkM','Ketiganya') NOT NULL DEFAULT 'Pendidikan',
  alamat         VARCHAR(255) NULL,
  kota           VARCHAR(80) NULL,
  -- Nomor dan tanggal dokumen: yang ditanya asesor lebih dari sekadar "ada MoU"
  no_dokumen     VARCHAR(120) NULL,
  tgl_mulai      DATE NULL,
  tgl_berakhir   DATE NULL,
  status_dokumen ENUM('berlaku','kedaluwarsa','belum ada') NOT NULL DEFAULT 'belum ada',
  ada_laporan    TINYINT(1) NOT NULL DEFAULT 0,
  catatan        VARCHAR(255) NULL,
  aktif          TINYINT(1) NOT NULL DEFAULT 1,
  dibuat         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mitra_upps (upps_id),
  KEY ix_mitra_berakhir (tgl_berakhir),
  CONSTRAINT fk_mitra_upps FOREIGN KEY (upps_id) REFERENCES upps(id),
  CONSTRAINT ck_mitra_tanggal CHECK (tgl_berakhir IS NULL OR tgl_mulai IS NULL OR tgl_berakhir >= tgl_mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wahana yang dilayani satu mitra. Many-to-many: RSUD bisa jadi wahana
-- untuk Kebidanan DAN Keperawatan, dan itu satu MoU, bukan dua.
CREATE TABLE IF NOT EXISTS mitra_wahana (
  mitra_id   INT UNSIGNED NOT NULL,
  wahana_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (mitra_id, wahana_id),
  CONSTRAINT fk_mw_mitra FOREIGN KEY (mitra_id) REFERENCES mitra(id) ON DELETE CASCADE,
  CONSTRAINT fk_mw_wahana FOREIGN KEY (wahana_id) REFERENCES wahana(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kegiatan yang sudah berjalan per mitra (Tabel 38 kolom pendidikan/
-- penelitian/PkM). Disimpan per tahun supaya bisa dilaporkan per TS-n.
CREATE TABLE IF NOT EXISTS mitra_kegiatan (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mitra_id   INT UNSIGNED NOT NULL,
  tahun      SMALLINT UNSIGNED NOT NULL,
  pendidikan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  penelitian TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pkm        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kegiatan (mitra_id, tahun),
  CONSTRAINT fk_mk_mitra FOREIGN KEY (mitra_id) REFERENCES mitra(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'mitra, mitra_wahana, mitra_kegiatan dibuat' AS hasil;
