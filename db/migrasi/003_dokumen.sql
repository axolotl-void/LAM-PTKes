-- LAM-PTKes — 004: kriteria, 78 dokumen pendukung, 10 aturan konsistensi
-- Dibangkitkan dari db/dokumen-spec.json + db/gen_004.py. Jangan disunting tangan.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS kriteria (
  kode       CHAR(2) NOT NULL,
  nama       VARCHAR(160) NOT NULL,
  PRIMARY KEY (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 78 butir. `nomor` unik dalam satu kriteria; satu butir bisa punya beberapa
-- rincian di dalam sel yang sama (baris baru) — itu rincian, bukan butir baru.
CREATE TABLE dokumen_spec (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kriteria    CHAR(2) NOT NULL,
  nomor       TINYINT UNSIGNED NOT NULL,
  nama        VARCHAR(400) NOT NULL,
  rincian     TEXT NULL,
  wajib       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dok_spec (kriteria, nomor),
  CONSTRAINT fk_dok_spec_krit FOREIGN KEY (kriteria) REFERENCES kriteria(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satu dokumen boleh diunggah lebih dari sekali (revisi). Yang dipakai adalah
-- unggahan terbaru; yang lama tetap ada supaya bisa dibandingkan saat asesor
-- bertanya "yang mana yang dikirim".
CREATE TABLE dokumen_unggah (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dok_spec_id   INT UNSIGNED NOT NULL,
  prodi_id      INT UNSIGNED NOT NULL,
  periode_id    INT UNSIGNED NOT NULL,
  berkas        VARCHAR(255) NOT NULL,          -- nama berkas di folder unggah/
  nama_asli     VARCHAR(255) NOT NULL,
  ukuran        INT UNSIGNED NULL,
  mime          VARCHAR(120) NULL,
  berlaku_sampai DATE NULL,                     -- kedaluwarsa = peringatan, bukan hapus
  diunggah_oleh INT UNSIGNED NULL,
  diunggah_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  catatan       VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY ix_unggah_dok (dok_spec_id, prodi_id, periode_id),
  CONSTRAINT fk_unggah_spec FOREIGN KEY (dok_spec_id) REFERENCES dokumen_spec(id),
  CONSTRAINT fk_unggah_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id),
  CONSTRAINT fk_unggah_periode FOREIGN KEY (periode_id) REFERENCES periode(id),
  CONSTRAINT fk_unggah_oleh FOREIGN KEY (diunggah_oleh) REFERENCES pengguna(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10 aturan lintas tabel sebagai DATA, bukan kode: aturan berubah kalau versi
-- instrumen berubah, dan agent yang berbeda harus membaca daftar yang sama.
CREATE TABLE konsistensi_aturan (
  kode        CHAR(3) NOT NULL,
  nama        VARCHAR(255) NOT NULL,
  tabel_a     CHAR(2) NULL,
  tabel_b     CHAR(2) NULL,
  tingkat     ENUM('blokir','peringatan','wajib') NOT NULL,
  catatan     VARCHAR(255) NULL,
  aktif       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kriteria (kode,nama) VALUES ('K1','Visi, Misi, Tujuan, dan Strategi');
INSERT INTO kriteria (kode,nama) VALUES ('K2','Kurikulum');
INSERT INTO kriteria (kode,nama) VALUES ('K3','Penilaian');
INSERT INTO kriteria (kode,nama) VALUES ('K4','Mahasiswa');
INSERT INTO kriteria (kode,nama) VALUES ('K5','Dosen, Tendik, Penelitian, PkM');
INSERT INTO kriteria (kode,nama) VALUES ('K6','Sarana, Prasarana, dan Keuangan');
INSERT INTO kriteria (kode,nama) VALUES ('K7','Penjaminan Mutu');
INSERT INTO kriteria (kode,nama) VALUES ('K8','Tata Kelola dan Administrasi');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (1,'K1',1,'Laporan kegiatan atau notulen rapat pada saat perumusan visi, misi, dan unggulan Program Studi yang berasal dari visi, misi, dan unggulan fakultas dan universitas. Visi, misi, dan unggulan tersebut mencakup peran UPPS dalam meningkatkan derajat kesehatan masyarakat.','Laporan kegiatan atau notulen rapat pada saat perumusan visi, misi, dan unggulan Program Studi yang berasal dari visi, misi, dan unggulan fakultas dan universitas. Visi, misi, dan unggulan tersebut mencakup peran UPPS dalam meningkatkan derajat kesehatan masyarakat.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (2,'K1',2,'Bukti kegiatan keterlibatan pemangku kepentingan internal (mahasiswa, dosen, tendik, pengelola) dan eksternal (lulusan, pengguna lulusan, mitra, pakar, organisasi profesi dan pemerintah) seperti daftar hadir, rekaman foto/video pada saat pertemuan.','Bukti kegiatan keterlibatan pemangku kepentingan internal (mahasiswa, dosen, tendik, pengelola) dan eksternal (lulusan, pengguna lulusan, mitra, pakar, organisasi profesi dan pemerintah) seperti daftar hadir, rekaman foto/video pada saat pertemuan.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (3,'K1',3,'Media yang digunakan untuk publikasi/sosialisasi visi, misi, dan unggulan.','Media yang digunakan untuk publikasi/sosialisasi visi, misi, dan unggulan.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (4,'K1',4,'Dokumen rencana strategi (renstra) dan rencana operasional (renop)','Dokumen rencana strategi (renstra) dan rencana operasional (renop)');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (5,'K2',1,'Notulen rapat komite kurikulum untuk merumuskan capaian pembelajaran lulusan setiap mata kuliah (termasuk pengetahuan, keterampilan, dan perilaku) berdasarkan visi, misi, dan unggulan program studi dengan pelibatan pemangku kepentingan internal dan eksternal.','Notulen rapat komite kurikulum untuk merumuskan capaian pembelajaran lulusan setiap mata kuliah (termasuk pengetahuan, keterampilan, dan perilaku) berdasarkan visi, misi, dan unggulan program studi dengan pelibatan pemangku kepentingan internal dan eksternal.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (6,'K2',2,'Buku kurikulum (kurikulum: prinsip, struktur, isi, urutan), peta kompetensi, rencana pembelajaran semester (RPS), hasil pembelajaran, metode pendidikan, penilaian.','Buku kurikulum (kurikulum: prinsip, struktur, isi, urutan), peta kompetensi, rencana pembelajaran semester (RPS), hasil pembelajaran, metode pendidikan, penilaian.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (7,'K2',3,'Daftar departemen klinis untuk penempatan mahasiswa Daftar penempatan mahasiswa untuk orientasi pembelajaran klinik profesional (early clinical exposure) pada tahap akademik dan praktek klinik profesional tahap profesi.','Daftar departemen klinis untuk penempatan mahasiswa; Daftar penempatan mahasiswa untuk orientasi pembelajaran klinik profesional  (early clinical exposure) pada tahap akademik dan praktek klinik profesional  tahap profesi.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (8,'K2',4,'Daftar rumah sakit pendidikan Daftar wahana praktek yang digunakan dan memenuhi persyaratan praktek klinik profesional mahasiswa','Daftar rumah sakit pendidikan; Daftar wahana praktek yang digunakan dan memenuhi persyaratan praktek klinik profesional mahasiswa');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (9,'K2',5,'Notulen rapat komite kurikulum tentang metode pendidikan, telaah kurikulum, evaluasi dan peninjauan kurikulum.','Notulen rapat komite kurikulum tentang metode pendidikan, telaah kurikulum, evaluasi dan peninjauan kurikulum.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (10,'K2',6,'Modul dan Panduan praktek klinik profesional mahasiswa','Modul dan Panduan praktek klinik profesional mahasiswa');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (11,'K2',7,'Risalah rapat dan laporan keterlibatan pemangku kepentingan eksternal dalam sistem manajemen mutu dan strategi keselamatan pasien','Risalah rapat dan laporan keterlibatan pemangku kepentingan eksternal dalam sistem manajemen mutu dan strategi keselamatan pasien');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (12,'K2',8,'Pedoman pelaksanaan RCA (Root Cause Analysis) meliputi a. Identifikasi Masalah, b. Pengumpulan Data, c. Analisis Penyebab, d. Identifikasi Akar Penyebab, e. Pengembangan Solusi, f. Implementasi Solusi, g. Pemantauan dan Tindak Lanjut','Pedoman pelaksanaan RCA (Root Cause Analysis) meliputi a. Identifikasi Masalah, b. Pengumpulan Data, c. Analisis Penyebab, d. Identifikasi Akar Penyebab, e. Pengembangan Solusi, f. Implementasi Solusi, g. Pemantauan dan Tindak Lanjut');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (13,'K2',9,'Kebijakan dan prosedur mitigasi kasus risiko kecelakaan','Kebijakan dan prosedur mitigasi kasus risiko kecelakaan');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (14,'K3',1,'Prosedur operasional standar penilaian','Prosedur operasional standar penilaian');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (15,'K3',2,'Buku catatan mahasiswa (logbook), dokumen revisi strategi pengajaran: penilaian mahasiswa (evaluasi dan pemantauan kemajuan mahasiswa) dan umpan balik dosen (strategi mengajar dosen)','Buku catatan mahasiswa (logbook), dokumen revisi strategi pengajaran: penilaian mahasiswa (evaluasi dan pemantauan kemajuan mahasiswa) dan umpan balik dosen (strategi mengajar dosen)');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (16,'K3',3,'Mekanisme remedial dan konseling','Mekanisme remedial dan konseling');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (17,'K3',4,'Cetak biru (blueprint) penilaian','Cetak biru (blueprint) penilaian');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (18,'K3',5,'Prosedur mekanisme banding','Prosedur mekanisme banding');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (19,'K3',6,'Dokumen sistem Penjaminan Mutu: perencanaan dan pelaksanaan','Dokumen sistem Penjaminan Mutu: perencanaan dan pelaksanaan');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (20,'K3',7,'Kebijakan dan prosedur penilaian sesuai tempat pembelajaran','Kebijakan dan prosedur penilaian sesuai tempat pembelajaran');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (21,'K3',8,'Lampiran hasil Uji Kompetensi CBT dan OSCE','Lampiran hasil Uji Kompetensi CBT dan OSCE');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (22,'K4',1,'Peraturan tentang kebijakan seleksi dan penerimaan: penyelarasan dengan misi dan akreditasi/persyaratan, publisitas/sosialisasi, peninjauan, dan revisi.','Peraturan tentang kebijakan seleksi dan penerimaan: penyelarasan dengan misi dan akreditasi/persyaratan, publisitas/sosialisasi, peninjauan, dan revisi.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (23,'K4',2,'Kebijakan, peraturan, prosedur dukungan/layanan mahasiswa, dan keselamatan lingkungan kerja','Kebijakan, peraturan, prosedur dukungan/layanan mahasiswa, dan keselamatan lingkungan kerja');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (24,'K4',3,'Kebijakan, peraturan, dan prosedur konseling mahasiswa.','Kebijakan, peraturan, dan prosedur konseling mahasiswa.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (25,'K4',4,'Pendukung sumber daya manusia, fasilitas, dan keuangan untuk sistem pendukung/layanan mahasiswa.','Pendukung sumber daya manusia, fasilitas, dan keuangan untuk sistem pendukung/layanan mahasiswa.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (26,'K4',5,'Monitoring dan evaluasi penerapan sistem pendukung/layanan kemahasiswaan.','Monitoring dan evaluasi penerapan sistem pendukung/layanan kemahasiswaan.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (27,'K4',6,'Dokumen hasil survey kepuasaan mahasiswa terhadap layanan mahasiswa.','Dokumen hasil survey kepuasaan mahasiswa terhadap layanan mahasiswa.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (28,'K4',7,'Dokumen hasil survey kepuasaan mahasiswa terhadap layanan manajemen','Dokumen hasil survey kepuasaan mahasiswa terhadap layanan manajemen');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (29,'K4',8,'Kebijakan, peraturan mengenai ‘kampus sehat’ termasuk bebas dari kekerasan seksual, perundungan, intoleransi, bebas dari rokok dan narkotika.','Kebijakan, peraturan mengenai ‘kampus sehat’ termasuk bebas dari kekerasan seksual, perundungan, intoleransi, bebas dari rokok dan narkotika.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (30,'K4',9,'Pedoman pelaksanaan RCA (Root Cause Analysis) meliputi a. Identifikasi Masalah, b. Pengumpulan Data, c. Analisis Penyebab, d. Identifikasi Akar Penyebab, e. Pengembangan Solusi, f. Implementasi Solusi, g. Pemantauan dan Tindak Lanjut','Pedoman pelaksanaan RCA (Root Cause Analysis) meliputi a. Identifikasi Masalah, b. Pengumpulan Data, c. Analisis Penyebab, d. Identifikasi Akar Penyebab, e. Pengembangan Solusi, f. Implementasi Solusi, g. Pemantauan dan Tindak Lanjut');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (31,'K4',10,'Pemantauan dan evaluasi penerapan sistem pendukung mahasiswa dan keselamatan lingkungan kerja.','Pemantauan dan evaluasi penerapan sistem pendukung mahasiswa dan keselamatan lingkungan kerja.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (32,'K4',11,'Pedoman RCA (Root Cause Analysis).','Pedoman RCA (Root Cause Analysis).');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (33,'K5',1,'Rencana pengembangan sumber daya manusia (SDM) sesuai dengan kebutuhan masing-masing disiplin ilmu dan perkembangan ilmu pengetahuan.','Rencana pengembangan sumber daya manusia (SDM) sesuai dengan kebutuhan masing-masing disiplin ilmu dan perkembangan ilmu pengetahuan.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (34,'K5',2,'Kebijakan dan prosedur pengembangan SDM (dosen dan tendik).','Kebijakan dan prosedur pengembangan SDM (dosen dan tendik).');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (35,'K5',3,'Notulen/risalah rapat dan daftar kehadiran terkait kegiatan pengembangan SDM.','Notulen/risalah rapat dan daftar kehadiran terkait kegiatan pengembangan SDM.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (36,'K5',4,'Pemetaan disiplin kurikulum (kesesuaian bidang ilmu dengan mata kuliah yang diampu dan beban kerja).','Pemetaan disiplin kurikulum (kesesuaian bidang ilmu dengan mata kuliah yang diampu dan beban kerja).');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (37,'K5',5,'Formulir monitoring dan evaluasi kinerja dosen, sampel formulir yang sudah diisi dari beberapa dosen, hasil penilaian kinerja setiap semester.','Formulir monitoring dan evaluasi kinerja dosen, sampel formulir yang sudah diisi dari beberapa dosen, hasil penilaian kinerja setiap semester.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (38,'K5',6,'Laporan program pelatihan orientasi.','Laporan program pelatihan orientasi.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (39,'K5',7,'Laporan program pelatihan untuk dosen baru dan lama.','Laporan program pelatihan untuk dosen baru dan lama.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (40,'K5',8,'Roadmap penelitian, dan Pengabdian kepada Masyarakat dosen.','Roadmap penelitian, dan Pengabdian kepada Masyarakat dosen.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (41,'K5',9,'Laporan penelitian dosen dan PkM dosen serta publikasinya.','Laporan penelitian dosen dan PkM  dosen serta publikasinya.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (42,'K5',10,'Bukti penghargaan atau pengakuan atas hasil penelitian (termasuk menerima: Hibah penelitian, HaKi, dan Paten).','Bukti penghargaan atau pengakuan atas hasil penelitian (termasuk menerima: Hibah penelitian, HaKi, dan Paten).');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (43,'K5',11,'Kebijakan penelitian dan PkM serta integrasinya.','Kebijakan penelitian dan PkM serta integrasinya.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (44,'K5',12,'Sertifikat Pendidik/Dosen, Sertifikat Kompetensi, dan Ijazah','Sertifikat Pendidik/Dosen, Sertifikat Kompetensi, dan Ijazah');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (45,'K5',13,'HaKI atau surat pengakuan/penghargaan dari lembaga nasional/internasional','HaKI atau surat pengakuan/penghargaan dari lembaga nasional/internasional');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (46,'K5',14,'Formulir monitoring dan evaluasi kinerja tendik.','Formulir monitoring dan evaluasi kinerja tendik.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (47,'K5',15,'Laporan program pelatihan tendik.','Laporan program pelatihan tendik.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (48,'K6',1,'Daftar infrastruktur fisik/sarana dan prasarana','Daftar infrastruktur fisik/sarana dan prasarana');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (49,'K6',2,'Daftar sistem pendukung pembelajaran lainnya. Sistem manajemen pembelajaran dan dukungan internet','Daftar sistem pendukung pembelajaran lainnya. Sistem manajemen pembelajaran dan dukungan internet');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (50,'K6',3,'Daftar rumah sakit pendidikan dan wahana praktek klinik.','Daftar rumah sakit pendidikan dan wahana praktek klinik.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (51,'K6',4,'Daftar fasilitas di rumah sakit pendidikan dan pengajaran klinik (ruang diskusi, ruang shift malam, perpustakaan, dll.)','Daftar fasilitas di rumah sakit pendidikan dan pengajaran klinik (ruang diskusi, ruang shift malam, perpustakaan, dll.)');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (52,'K6',5,'Daftar manekin yang tersedia untuk pelatihan keterampilan klinis mahasiswa.','Daftar manekin yang tersedia untuk pelatihan keterampilan klinis mahasiswa.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (53,'K6',6,'Kebijakan mengenai keselamatan dan kesehatan kerja civitas akademika.','Kebijakan mengenai keselamatan dan kesehatan kerja civitas akademika.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (54,'K6',7,'Daftar pasien standar dan laporan pelatihannya.','Daftar pasien standar dan laporan pelatihannya.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (55,'K6',8,'Daftar pelatihan dan laporannya dari dosen klinis dan pembimbing','Daftar pelatihan dan laporannya dari dosen klinis dan pembimbing');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (56,'K6',9,'Daftar database jurnal yang tersedia','Daftar database jurnal yang tersedia');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (57,'K6',10,'Formulir evaluasi dan umpan balik dari mahasiswa dan staf akademik serta administrasi untuk sumber informasi yang tersedia','Formulir evaluasi dan umpan balik dari mahasiswa dan staf akademik serta administrasi untuk sumber informasi yang tersedia');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (58,'K6',11,'Fasilitas untuk mengakses sumber informasi dan sumber belajar.','Fasilitas untuk mengakses sumber informasi dan sumber belajar.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (59,'K6',12,'Data hasil survei kepuasan atas pelayanan yang diberikan manajemen kepada seluruh pemangku kepentingan (mahasiswa, dosen, pegawai, rekanan, dan pemberi kerja alumni).','Data hasil survei kepuasan atas pelayanan yang diberikan manajemen kepada seluruh pemangku kepentingan (mahasiswa, dosen, pegawai, rekanan, dan pemberi kerja alumni).');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (60,'K6',13,'Data hasil survei kepuasan terhadap kecukupan, kualitas dan akses terhadap fasilitas dan peralatan fisik serta sumber informasi pendidikan dan pelatihan klinis.','Data hasil survei kepuasan terhadap kecukupan, kualitas dan akses terhadap fasilitas dan peralatan fisik serta sumber informasi pendidikan dan pelatihan klinis.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (61,'K6',14,'Dokumen audit: keuangan dan sarana prasarana.','Dokumen audit: keuangan dan sarana prasarana.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (62,'K7',1,'Sistem penjaminan mutu: struktur dan tupoksi.','Sistem penjaminan mutu: struktur dan tupoksi.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (63,'K7',2,'Dokumen mutu: kebijakan, standar, manual, formulir, dan dokumen pendukung lainnya.','Dokumen mutu: kebijakan, standar, manual, formulir, dan dokumen pendukung lainnya.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (64,'K7',3,'Laporan audit mutu internal.','Laporan audit mutu internal.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (65,'K7',4,'Laporan rapat tinjauan manajemen.','Laporan rapat tinjauan manajemen.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (66,'K7',5,'Sumber daya yang dialokasikan untuk penjaminan mutu.','Sumber daya yang dialokasikan untuk penjaminan mutu.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (67,'K7',6,'Notulen/risalah rapat dan laporan keterlibatan pemangku kepentingan eksternal dalam sistem penjaminan mutu dan strategi keselamatan pasien.','Notulen/risalah rapat dan laporan keterlibatan pemangku kepentingan eksternal dalam sistem penjaminan mutu dan strategi keselamatan pasien.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (68,'K7',7,'Dokumen tindak lanjut atas umpan balik penjaminan mutu untuk peningkatan mutu berkelanjutan.','Dokumen tindak lanjut atas umpan balik penjaminan mutu untuk peningkatan mutu berkelanjutan.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (69,'K7',8,'Pedoman pelaksanaan RCA (Root Cause Analysis) meliputi a. Identifikasi Masalah, b. Pengumpulan Data, c. Analisis Penyebab, d. Identifikasi Akar Penyebab, e. Pengembangan Solusi, f. Implementasi Solusi, g. Pemantauan dan Tindak Lanjut','Pedoman pelaksanaan RCA (Root Cause Analysis) meliputi a. Identifikasi Masalah, b. Pengumpulan Data, c. Analisis Penyebab, d. Identifikasi Akar Penyebab, e. Pengembangan Solusi, f. Implementasi Solusi, g. Pemantauan dan Tindak Lanjut');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (70,'K7',9,'Kebijakan dan prosedur mitigasi kasus risiko.','Kebijakan dan prosedur mitigasi kasus risiko.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (71,'K8',1,'Bagan organisasi pengelolaan dan administrasi beserta tupoksi.','Bagan organisasi pengelolaan dan administrasi beserta tupoksi.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (72,'K8',2,'Prosedur operasional standar pengalokasian anggaran.','Prosedur operasional standar pengalokasian anggaran.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (73,'K8',3,'Laporkan tinjauan kinerja institusi/UPPS','Laporkan tinjauan kinerja institusi/UPPS');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (74,'K8',4,'Dokumen identifikasi dan mitigasi risiko.','Dokumen identifikasi dan mitigasi risiko.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (75,'K8',5,'Laporan/risalah rapat keterlibatan mahasiswa dan dosen dalam pengambilan keputusan dan fungsi UPPS','Laporan/risalah rapat keterlibatan mahasiswa dan dosen dalam pengambilan keputusan dan fungsi UPPS');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (76,'K8',6,'Standar prosedur operasional (SPO) untuk proses pengambilan keputusan.','Standar prosedur operasional (SPO) untuk proses pengambilan keputusan.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (77,'K8',7,'Standar prosedur operasional (SPO) pelaporan pembelajaran, penelitian, dan pengabdian kepada masyarakat.','Standar prosedur operasional (SPO) pelaporan pembelajaran, penelitian, dan pengabdian kepada masyarakat.');
INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (78,'K8',8,'Dokumen indikator kinerja utama dan kinerja tambahan.','Dokumen indikator kinerja utama dan kinerja tambahan.');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R01','Jumlah dosen tetap di Tabel 15 = jumlah baris nama di Tabel 16','15','16','peringatan','blokir ekspor bila berbeda');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R02','Jumlah lulusan di Tabel 3 = akumulasi lulusan di Tabel 5','03','05','peringatan','peringatan saja');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R03','Total mahasiswa Tabel 9 = jumlah seluruh prodi di Tabel 10','09','10','peringatan','peringatan saja');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R04','Jumlah mahasiswa Tabel 10 = jumlah baris master mahasiswa aktif','10',NULL,'blokir','tolak simpan');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R05','Total penerimaan dana Tabel 33 = sumber dana mahasiswa di Tabel 34','33','34','peringatan','peringatan saja');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R06','Jumlah persen Tabel 34 per tahun = 100%','34',NULL,'blokir','tolak simpan');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R07','Dosen di Tabel 17 harus ada di Tabel 16 atau 19','17','16','blokir','tolak simpan');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R08','Wahana di Tabel 30 harus punya dokumen kerja sama di Tabel 38','30','38','peringatan','peringatan saja');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R09','Mata ajar di Tabel 16 dan 19 harus ada di master Mata Kuliah','16',NULL,'peringatan','peringatan saja');
INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES ('R10','Tabel khusus profesi wajib lengkap bila punya_tahap_profesi = ya','04',NULL,'wajib','masuk checklist wajib');

SELECT CONCAT('seed: ', (SELECT COUNT(*) FROM kriteria), ' kriteria, ',
  (SELECT COUNT(*) FROM dokumen_spec), ' dokumen, ',
  (SELECT COUNT(*) FROM konsistensi_aturan), ' aturan') AS hasil;