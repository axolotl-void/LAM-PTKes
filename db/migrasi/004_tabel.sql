-- LAM-PTKes — 003: 38 tabel data dukung (dibangkitkan, jangan disunting)
-- Sumber: db/field-spec.json + db/gen_skema_tabel.py
-- Angka: 38 tabel, 261 field (Input 125 / Hitung 68 / Master 71 / Tetap 1)
SET NAMES utf8mb4;

CREATE TABLE tabel_dukung (
  kode          CHAR(2) NOT NULL,
  nomor         TINYINT UNSIGNED NOT NULL,
  judul         VARCHAR(255) NOT NULL,
  kriteria      CHAR(2) NOT NULL,
  khusus_profesi TINYINT(1) NOT NULL DEFAULT 0,
  varian        VARCHAR(20) NULL,                -- 'sarjana' | 'spesialis4' | ...
  berlaku_untuk VARCHAR(20) NOT NULL DEFAULT 'semua',
  PRIMARY KEY (kode),
  UNIQUE KEY uq_tabel_nomor (nomor),
  -- FK ini yang membuat salah ketik kode kriteria gagal saat migrasi,
  -- bukan jadi angka 0 yang tidak ketahuan berbulan-bulan.
  CONSTRAINT fk_tabel_kriteria FOREIGN KEY (kriteria) REFERENCES kriteria(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE spec_field (
  tabel_kode    CHAR(2) NOT NULL,
  urut          SMALLINT UNSIGNED NOT NULL,
  label         VARCHAR(180) NOT NULL,
  kunci         VARCHAR(60) NOT NULL,
  tipe          VARCHAR(40) NOT NULL,
  asal          ENUM('Input','Hitung','Master','Tetap') NOT NULL,
  -- Field bertanda √ dikelompokkan: `pilih`='satu' berarti hanya boleh satu
  -- yang terisi dalam satu baris (satu penelitian tidak terjadi di dua tahun
  -- sekaligus); 'banyak' berarti boleh lebih dari satu. Dibaca saat validasi.
  kelompok      VARCHAR(40) NOT NULL DEFAULT '',
  pilih         ENUM('satu','banyak') NOT NULL DEFAULT 'banyak',
  keterangan    VARCHAR(255) NULL,
  PRIMARY KEY (tabel_kode, kunci),
  UNIQUE KEY uq_spec_urut (tabel_kode, urut),
  CONSTRAINT fk_spec_tabel FOREIGN KEY (tabel_kode) REFERENCES tabel_dukung(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satu baris = satu baris yang diketik operator. Jumlah baris per tabel
-- berbeda-beda (Tabel 1 = 239 baris), jadi tidak bisa dipaksa ke kolom tetap.
CREATE TABLE baris_tabel (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prodi_id      INT UNSIGNED NOT NULL,
  periode_id    INT UNSIGNED NOT NULL,
  tabel_kode    CHAR(2) NOT NULL,
  urut          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  dikunci_oleh  INT UNSIGNED NULL,
  dikunci_pada  DATETIME NULL,
  dibuat        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- prodi_id WAJIB ikut: dua prodi mengisi tabel yang sama pada urut yang
  -- sama. Tanpa prodi_id di kunci, prodi kedua gagal menyimpan (1062).
  UNIQUE KEY uq_baris (prodi_id, periode_id, tabel_kode, urut),
  KEY ix_baris_tabel (periode_id, tabel_kode),
  CONSTRAINT fk_baris_prodi FOREIGN KEY (prodi_id) REFERENCES prodi(id),
  CONSTRAINT fk_baris_periode FOREIGN KEY (periode_id) REFERENCES periode(id),
  CONSTRAINT fk_baris_tabel FOREIGN KEY (tabel_kode) REFERENCES tabel_dukung(kode),
  CONSTRAINT fk_baris_kunci FOREIGN KEY (dikunci_oleh) REFERENCES pengguna(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EAV: 243 field dengan tipe berbeda, dan jumlahnya berubah kalau versi
-- instrumen berubah. Kolom fisik per field -> ALTER TABLE tiap ganti versi.
-- Nilai disimpan terpisah angka/teks supaya SUM() dan perbandingan tidak
-- memaksa CAST pada 100 ribu baris.
CREATE TABLE sel (
  baris_id      BIGINT UNSIGNED NOT NULL,
  field_kunci   VARCHAR(60) NOT NULL,
  nilai_angka   DECIMAL(18,4) NULL,
  nilai_teks    VARCHAR(500) NULL,
  PRIMARY KEY (baris_id, field_kunci),
  KEY ix_sel_field (field_kunci),
  CONSTRAINT fk_sel_baris FOREIGN KEY (baris_id) REFERENCES baris_tabel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('01',1,'01. Substansi Kurikulum (kuliah/praktikum/praktik) Program Sarjana dan Program Profesi di Program Studi','K2',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',1,'No','no','Teks','Input','','banyak','Nomor urut');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',2,'Kode dan Nama MK','kode_dan_nama_mk','relasi ke Mata Kuliah','Master','','banyak','Kolom dari master MK');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',3,'Capaian Pembelajaran MK','capaian_pembelajaran_mk','teks panjang','Master','','banyak','Dari master MK');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',4,'Kuliah (sks)','kuliah_sks','angka','Master','','banyak','Dari master MK, 0 bila tidak ada kuliah');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',5,'Praktikum: Judul/Modul','praktikum_judul_modul','teks','Input','','banyak','Diisi bila ada praktikum');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',6,'Praktikum: Jam Pelaksanaan','praktikum_jam_pelaksanaan','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',7,'Aspek penilaian: Sikap','aspek_penilaian_sikap','centang','Master','aspek_penilaian','banyak','Dari master MK');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',8,'Aspek penilaian: Pengetahuan','aspek_penilaian_pengetahuan','centang','Master','aspek_penilaian','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',9,'Aspek penilaian: Keterampilan umum','aspek_penilaian_keterampilan_umum','centang','Master','aspek_penilaian','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',10,'Aspek penilaian: Keterampilan khusus','aspek_penilaian_keterampilan_khusus','centang','Master','aspek_penilaian','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',11,'RPS','rps','teks','Master','','banyak','Centang bila ada, dari master MK');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('01',12,'Tempat/Lokasi Praktikum','tempat_lokasi_praktikum','teks','Master','','banyak','Dari master MK');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('02',2,'02. Persentase Keberhasilan Studi (Bundling Sarjana dan Profesi)','K3',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('02',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('02',2,'Tahun Akademik','tahun_akademik','tahun akademik','Hitung','','banyak','TS-7 sampai TS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('02',3,'Jumlah Mahasiswa','jumlah_mahasiswa','angka','Hitung','','banyak','Hitung dari master mahasiswa: status aktif + lulus + DO pada tahun tsb');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('02',4,'Masa studi 5,5 - 6 th','masa_studi_5_5_6_th','angka','Hitung','','banyak','Jumlah lulusan dengan selisih tahun masuk-lulus dalam rentang');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('02',5,'Masa studi 6 - 10 th','masa_studi_6_10_th','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('02',6,'DO','do','angka','Hitung','','banyak','D = A - (B + C) sesuai catatan template');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('03',3,'03. Data IPK Lulusan untuk Program Diploma/Sarjana/Sarjana Terapan di Program Studi','K3',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',1,'Tahun Akademik','tahun_akademik','tahun akademik','Hitung','','banyak','TS-2, TS-1, TS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',2,'Jumlah lulusan reguler','jumlah_lulusan_reguler','angka','Hitung','','banyak','Dari master mahasiswa, jalur masuk = Reguler');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',3,'Jumlah lulusan transfer','jumlah_lulusan_transfer','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',4,'Jumlah lulusan asing','jumlah_lulusan_asing','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',5,'IPK minimum','ipk_minimum','desimal','Hitung','','banyak','MIN dari IPK lulusan tahun tsb');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',6,'IPK rata-rata','ipk_rata_rata','desimal','Hitung','','banyak','AVG');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',7,'IPK maksimum','ipk_maksimum','desimal','Hitung','','banyak','MAX');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',8,'Lulusan IPK 2,00-3,00','lulusan_ipk_2_00_3_00','angka','Hitung','','banyak','Hitung rentang');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',9,'Lulusan IPK >3,00-3,50','lulusan_ipk_3_00_3_50','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('03',10,'Lulusan IPK >3,50-4,00','lulusan_ipk_3_50_4_00','angka','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('04',4,'04. Data IPK Lulusan untuk Program Profesi/Spesialis/Subspesialis/Magister/Magister Terapan/Doktor/Doktor Terapan di Program Studi','K3',1);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('04',1,'Semester Akademik','semester_akademik','tahun akademik','Hitung','','banyak','SS-5 sampai SS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('04',2,'Jumlah lulusan reguler','jumlah_lulusan_reguler','angka','Hitung','','banyak','Dari mahasiswa tahap profesi');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('04',3,'Jumlah lulusan pindahan/transfer','jumlah_lulusan_pindahan_transfer','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('04',4,'Jumlah lulusan asing','jumlah_lulusan_asing','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('04',5,'IPK minimum / rata-rata / maksimum','ipk_minimum_rata_rata_maksimum','desimal','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('04',6,'Lulusan IPK 3,00-3,50','lulusan_ipk_3_00_3_50','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('04',7,'Lulusan IPK >3,50-3,75','lulusan_ipk_3_50_3_75','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('04',8,'Lulusan IPK >3,75-4,00','lulusan_ipk_3_75_4_00','angka','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('05',5,'05. Tabel 05','K3',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('05',1,'Tahun Masuk','tahun_masuk','tahun akademik','Hitung','','banyak','Satu baris per angkatan');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('05',2,'Jumlah mahasiswa per angkatan pada TS-6 ... TS','jumlah_mahasiswa_per_angkatan_pada_ts_6_ts','angka','Hitung','','banyak','Kolom tahun digeser otomatis mengikuti tahun_ts');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('05',3,'Jumlah lulusan s.d. TS','jumlah_lulusan_s_d_ts','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('05',4,'Penanda (a) - (g)','penanda_a_g','teks tetap','Tetap','','banyak','Wajib tercetak, tidak boleh dihapus saat ekspor');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('06',6,'06. Data Lulusan Tepat Waktu Tahap Profesi di Program Studi','K3',1);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('06',1,'Tahun Masuk','tahun_masuk','tahun akademik','Hitung','','banyak','TS-3 sampai TS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('06',2,'Jumlah mahasiswa per angkatan','jumlah_mahasiswa_per_angkatan','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('06',3,'Jumlah lulusan s.d. TS','jumlah_lulusan_s_d_ts','angka','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('07',7,'07. Uji Kompetensi Mahasiswa Program Diploma/Profesi/Spesialis/Subspesialis Program Studi (UKMPS-CBT)','K3',1);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',1,'Tahun/Semester','tahun_semester','tahun akademik','Input','','banyak','TS-2, TS-1, TS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',2,'Mahasiswa selesai praktik klinik/stase','mahasiswa_selesai_praktik_klinik_stase','angka','Hitung','','banyak','Dari master mahasiswa dan stase');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',3,'Jumlah peserta UKMPS-CBT dari prodi','jumlah_peserta_ukmps_cbt_dari_prodi','angka','Input','','banyak','Bukan peserta titipan');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',4,'First taker: jumlah peserta','first_taker_jumlah_peserta','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',5,'First taker: jumlah lulus','first_taker_jumlah_lulus','angka','Input','','banyak','Tidak boleh > peserta');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',6,'First taker: persentase','first_taker_persentase','persen','Hitung','','banyak','lulus/peserta x 100');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',7,'Re-taker: jumlah peserta','re_taker_jumlah_peserta','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',8,'Re-taker: jumlah lulus','re_taker_jumlah_lulus','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',9,'Re-taker: persentase','re_taker_persentase','persen','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',10,'Total lulusan','total_lulusan','angka','Hitung','','banyak','First taker lulus + re-taker lulus');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('07',11,'Persentase lulusan','persentase_lulusan','persen','Hitung','','banyak','Total lulus / jumlah peserta UKMPS');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('08',8,'08. UKMPS-OSCE (Objective Structured Clinical Examination) / Ujian Bersama Spesialis','K3',1);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('08',1,'Tahun','tahun','tahun akademik','Input','','banyak','Struktur identik Tabel 7');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('08',2,'Mahasiswa selesai stase','mahasiswa_selesai_stase','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('08',3,'Peserta UKMPS-OSCE','peserta_ukmps_osce','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('08',4,'First taker: peserta / lulus / persen','first_taker_peserta_lulus_persen','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('08',5,'Re-taker: peserta / lulus / persen','re_taker_peserta_lulus_persen','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('08',6,'Total lulusan','total_lulusan','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('08',7,'Persentase lulusan','persentase_lulusan','persen','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('09',9,'09. Data Total Mahasiswa pada Unit Pengelola Program Studi','K4',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('09',1,'Program Studi','program_studi','relasi','Master','','banyak','Satu baris per prodi dalam UPPS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('09',2,'Mahasiswa reguler','mahasiswa_reguler','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('09',3,'Non reguler','non_reguler','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('09',4,'Asing','asing','angka','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('10',10,'10. Data Mahasiswa Tahap Akademik pada Program Studi (5 tahun terakhir)','K4',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',1,'Tahun Akademik','tahun_akademik','tahun akademik','Input','','banyak','TS-4 sampai TS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',2,'Daya tampung','daya_tampung','angka','Input','','banyak','Input, tidak bisa dihitung');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',3,'Calon mahasiswa ikut seleksi','calon_mahasiswa_ikut_seleksi','angka','Input','','banyak','Input dari panitia penerimaan');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',4,'Calon mahasiswa lulus seleksi','calon_mahasiswa_lulus_seleksi','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',5,'Mahasiswa baru reguler','mahasiswa_baru_reguler','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',6,'Mahasiswa baru transfer','mahasiswa_baru_transfer','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',7,'Mahasiswa baru asing','mahasiswa_baru_asing','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',8,'Total mahasiswa aktif reguler','total_mahasiswa_aktif_reguler','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',9,'Total mahasiswa aktif transfer','total_mahasiswa_aktif_transfer','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('10',10,'Total mahasiswa aktif asing','total_mahasiswa_aktif_asing','angka','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('11',11,'11. Data Mahasiswa Tahap Profesi pada Program Studi (3 Tahun Terakhir)','K4',1);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('11',1,'Tahun Akademik','tahun_akademik','tahun akademik','Input','','banyak','TS-2 sampai TS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('11',2,'Daya tampung','daya_tampung','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('11',3,'Lulus tahap akademik','lulus_tahap_akademik','angka','Hitung','','banyak','Dari master mahasiswa');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('11',4,'Mahasiswa baru profesi reguler/transfer/asing','mahasiswa_baru_profesi_reguler_transfer_asing','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('11',5,'Total aktif profesi reguler/transfer/asing','total_aktif_profesi_reguler_transfer_asing','angka','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('12',12,'12. Tingkat Kepuasan Mahasiswa','K4',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('12',1,'No','no','Input','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('12',2,'Aspek yang diukur','aspek_yang_diukur','Teks','Master','','banyak','Dosen (4 sub), Tenaga kependidikan (4 sub), dst');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('12',3,'Sangat baik / Baik / Cukup / Kurang','sangat_baik_baik_cukup_kurang','persen','Hitung','','banyak','Agregasi dari modul survei');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('12',4,'Persentase','persentase','persen','Hitung','','banyak','Total skor aspek');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('12',5,'Rencana tindak lanjut','rencana_tindak_lanjut','teks panjang','Input','','banyak','Wajib diisi per aspek');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('13',13,'13. Masa Tunggu Lulusan Mendapatkan Pekerjaan','K4',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('13',1,'Tahun Lulus','tahun_lulus','tahun akademik','Input','','banyak','TS-2, TS-1, TS');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('13',2,'Jumlah lulusan','jumlah_lulusan','angka','Hitung','','banyak','Dari master mahasiswa');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('13',3,'Lulusan yang terlacak','lulusan_yang_terlacak','angka','Input','','banyak','Dari tracer study');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('13',4,'Masa tunggu <6 bulan','masa_tunggu_6_bulan','angka','Hitung','','banyak','Dihitung dari tanggal STR ke tanggal kerja pertama');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('13',5,'Masa tunggu 6-18 bulan','masa_tunggu_6_18_bulan','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('13',6,'Masa tunggu >=18 bulan','masa_tunggu_18_bulan','angka','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('14',14,'14. Tingkat Kepuasan Pengguna','K4',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('14',1,'No','no','Input','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('14',2,'Jenis kemampuan','jenis_kemampuan','Teks','Master','','banyak','Integritas, keahlian, bahasa Inggris, TI, komunikasi, kerja sama, pengembangan diri');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('14',3,'Sangat baik / Baik / Cukup / Kurang','sangat_baik_baik_cukup_kurang','angka','Hitung','','banyak','Dari survei pengguna');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('14',4,'Persentase','persentase','persen','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('14',5,'Rencana tindak lanjut','rencana_tindak_lanjut','teks panjang','Input','','banyak','-');
-- Tabel 15: baris tabel dibuat di sini, field-nya di migrasi/009_tabel15.sql (bentuk matriks).
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('15',15,'15. Dosen Tetap pada Unit Pengelola Program Studi','K5',0);
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('16',16,'16. Dosen Tetap Tahap Akademik dan Profesi pada Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',2,'Nama Dosen Tetap','nama_dosen_tetap','relasi','Master','','banyak','Tanda * bila sudah punya sertifikat pendidik');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',3,'Tahap: Akademik','tahap_akademik','centang','Master','tahap','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',4,'Tahap: Profesi','tahap_profesi','centang','Master','tahap','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',5,'NIDN atau NIDK','nidn_atau_nidk','Teks','Master','','banyak','Unik');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',6,'Tanggal lahir','tanggal_lahir','tanggal','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',7,'Jabatan akademik','jabatan_akademik','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',8,'Gelar akademik','gelar_akademik','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',9,'Pendidikan akademik/profesi dan asal PT','pendidikan_akademik_profesi_dan_asal_pt','Teks','Master','','banyak','Dari riwayat pendidikan');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',10,'Bidang keahlian per jenjang','bidang_keahlian_per_jenjang','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('16',11,'Mata ajar yang diampu','mata_ajar_yang_diampu','relasi','Master','','banyak','Ke Mata Kuliah');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('17',17,'17. Aktifitas Dosen Tetap pada Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',2,'Nama Dosen Tetap','nama_dosen_tetap','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',3,'sks pendidikan di PS sendiri','sks_pendidikan_di_ps_sendiri','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',4,'sks pendidikan di PS lain, PT sendiri','sks_pendidikan_di_ps_lain_pt_sendiri','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',5,'sks pendidikan di PT lain','sks_pendidikan_di_pt_lain','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',6,'sks penelitian','sks_penelitian','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',7,'sks pengabdian kepada masyarakat','sks_pengabdian_kepada_masyarakat','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',8,'sks manajemen PT sendiri','sks_manajemen_pt_sendiri','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',9,'sks manajemen PT lain','sks_manajemen_pt_lain','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('17',10,'Jumlah sks','jumlah_sks','angka','Hitung','','banyak','Penjumlahan 7 kolom sks');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('18',18,'18. Kegiatan Seminar Ilmiah/Lokakarya/Penataran/Pelatihan/Pameran Dosen Tetap pada Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('18',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('18',2,'Nama Dosen','nama_dosen','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('18',3,'Jenis kegiatan','jenis_kegiatan','pilihan','Input','','banyak','Seminar, lokakarya, penataran, pelatihan, pameran');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('18',4,'Tempat','tempat','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('18',5,'Waktu','waktu','tanggal','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('18',6,'Sebagai penyaji','sebagai_penyaji','centang','Input','peran_kegiatan','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('18',7,'Sebagai peserta','sebagai_peserta','centang','Input','peran_kegiatan','satu','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('19',19,'19. Data Dosen Tidak Tetap pada Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',2,'Nama (akademik / profesi)','nama_akademik_profesi','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',3,'NIDN / NIDK / NUP','nidn_nidk_nup','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',4,'Tanggal lahir','tanggal_lahir','tanggal','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',5,'Jabatan akademik','jabatan_akademik','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',6,'Gelar akademik','gelar_akademik','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',7,'Pendidikan dan sertifikat kompetensi','pendidikan_dan_sertifikat_kompetensi','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',8,'Bidang keahlian','bidang_keahlian','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('19',9,'Mata ajar yang diampu','mata_ajar_yang_diampu','relasi','Master','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('20',20,'20. Kegiatan Tenaga Ahli/Pakar pada Program Studi (3 Tahun Terakhir)','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('20',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('20',2,'Nama tenaga ahli/pakar','nama_tenaga_ahli_pakar','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('20',3,'Nama dan judul kegiatan','nama_dan_judul_kegiatan','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('20',4,'Waktu pelaksanaan','waktu_pelaksanaan','tanggal','Input','','banyak','3 tahun terakhir');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('21',21,'21. Pembimbingan Tugas Akhir','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('21',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('21',2,'Nama dosen pembimbing','nama_dosen_pembimbing','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('21',3,'Status: jenjang pendidikan','status_jenjang_pendidikan','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('21',4,'Status: jabatan akademik','status_jabatan_akademik','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('21',5,'Jumlah mahasiswa TS-2 / TS-1 / TS','jumlah_mahasiswa_ts_2_ts_1_ts','angka','Hitung','','banyak','Dari data bimbingan');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('21',6,'Jumlah pertemuan TS-2 / TS-1 / TS','jumlah_pertemuan_ts_2_ts_1_ts','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('21',7,'Lama penyelesaian (bulan) TS-2 / TS-1 / TS','lama_penyelesaian_bulan_ts_2_ts_1_ts','angka','Hitung','','banyak','Dari tanggal mulai dan tanggal selesai');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('22',22,'22. Data Kegiatan Penelitian Dosen Tetap Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',2,'Nama dosen','nama_dosen','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',3,'Judul penelitian','judul_penelitian','Teks','Input','','banyak','Wajib diisi');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',4,'Mata kuliah','mata_kuliah','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',5,'Bentuk integrasi','bentuk_integrasi','teks','Input','','banyak','Materi kuliah, studi kasus, modul, dst');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',6,'Tahun penelitian: TS-2','tahun_penelitian_ts_2','centang','Input','tahun_penelitian','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',7,'Tahun penelitian: TS-1','tahun_penelitian_ts_1','centang','Input','tahun_penelitian','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',8,'Tahun penelitian: TS','tahun_penelitian_ts','centang','Input','tahun_penelitian','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',9,'Tingkat penelitian: Internasional','tingkat_penelitian_internasional','centang','Input','tingkat_penelitian','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',10,'Tingkat penelitian: Nasional','tingkat_penelitian_nasional','centang','Input','tingkat_penelitian','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',11,'Tingkat penelitian: Wilayah','tingkat_penelitian_wilayah','centang','Input','tingkat_penelitian','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',12,'Kesesuaian roadmap: Sesuai','kesesuaian_roadmap_sesuai','centang','Input','kesesuaian_roadmap','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',13,'Kesesuaian roadmap: Kurang sesuai','kesesuaian_roadmap_kurang_sesuai','centang','Input','kesesuaian_roadmap','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('22',14,'Kesesuaian roadmap: Tidak sesuai','kesesuaian_roadmap_tidak_sesuai','centang','Input','kesesuaian_roadmap','satu','Satu centang per baris');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('23',23,'23. Data Kegiatan Pengabdian kepada Masyarakat (PkM) Dosen Tetap Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',1,'No / Nama dosen / Judul kegiatan PkM','no_nama_dosen_judul_kegiatan_pkm','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',2,'Mata kuliah','mata_kuliah','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',3,'Bentuk integrasi','bentuk_integrasi','teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',4,'Tahun PkM: TS-2','tahun_pkm_ts_2','centang','Input','tahun_pkm','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',5,'Tahun PkM: TS-1','tahun_pkm_ts_1','centang','Input','tahun_pkm','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',6,'Tahun PkM: TS','tahun_pkm_ts','centang','Input','tahun_pkm','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',7,'Kesesuaian PkM: Sesuai','kesesuaian_pkm_sesuai','centang','Input','kesesuaian_pkm','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',8,'Kesesuaian PkM: Kurang sesuai','kesesuaian_pkm_kurang_sesuai','centang','Input','kesesuaian_pkm','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('23',9,'Kesesuaian PkM: Tidak sesuai','kesesuaian_pkm_tidak_sesuai','centang','Input','kesesuaian_pkm','satu','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('24',24,'24. Artikel Ilmiah/Karya Ilmiah/Buku Tiga Tahun Terakhir','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('24',1,'Judul','judul','Teks','Input','','banyak','Wajib');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('24',2,'Nama dosen','nama_dosen','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('24',3,'Disajikan/dipublikasikan pada','disajikan_dipublikasikan_pada','Teks','Input','','banyak','Nama jurnal, penerbit, atau forum');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('24',4,'Tahun','tahun','angka','Input','','banyak','3 tahun terakhir');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('24',5,'Tingkat karya: Lokal','tingkat_karya_lokal','centang','Input','tingkat_karya','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('24',6,'Tingkat karya: Nasional','tingkat_karya_nasional','centang','Input','tingkat_karya','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('24',7,'Tingkat karya: Internasional','tingkat_karya_internasional','centang','Input','tingkat_karya','satu','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('25',25,'25. Jumlah Karya Dosen dan/atau Mahasiswa Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('25',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('25',2,'Nama dosen/mahasiswa/lembaga','nama_dosen_mahasiswa_lembaga','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('25',3,'HAKI, teknologi tepat guna, model/desain/rekayasa','haki_teknologi_tepat_guna_model_desain_rekayasa','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('25',4,'Karya yang mendapat pengakuan/penghargaan','karya_yang_mendapat_pengakuan_penghargaan','Teks','Input','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('26',26,'26. Jumlah Pengabdian kepada Masyarakat yang relevan dengan Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('26',1,'No / Nama / Karya HAKI / Karya berpenghargaan','no_nama_karya_haki_karya_berpenghargaan','Teks','Input','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('27',27,'27. Penghargaan/Rekognisi/Prestasi Dosen Tetap Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('27',1,'No / Nama dosen','no_nama_dosen','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('27',2,'Rekognisi/prestasi','rekognisi_prestasi','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('27',3,'Lembaga pemberi','lembaga_pemberi','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('27',4,'Waktu pencapaian (tahun)','waktu_pencapaian_tahun','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('27',5,'Tingkat: wilayah / nasional / internasional','tingkat_wilayah_nasional_internasional','pilihan','Input','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('28',28,'28. Jumlah Karya Mahasiswa di Program Studi','K5',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('28',1,'No / Nama kegiatan','no_nama_kegiatan','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('28',2,'Waktu penyelenggaraan','waktu_penyelenggaraan','tanggal','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('28',3,'Tingkat karya mahasiswa: Provinsi','tingkat_karya_mahasiswa_provinsi','centang','Input','tingkat_karya_mhs','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('28',4,'Tingkat karya mahasiswa: Nasional','tingkat_karya_mahasiswa_nasional','centang','Input','tingkat_karya_mhs','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('28',5,'Tingkat karya mahasiswa: Internasional','tingkat_karya_mahasiswa_internasional','centang','Input','tingkat_karya_mhs','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('28',6,'Prestasi yang dicapai','prestasi_yang_dicapai','Teks','Input','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('29',29,'29. Prasarana dan Peralatan Utama Laboratorium di Program Studi','K6',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',2,'Nama laboratorium','nama_laboratorium','relasi','Master','','banyak','Dari master laboratorium');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',3,'Jumlah unit lab','jumlah_unit_lab','angka','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',4,'Nama alat/peraga','nama_alat_peraga','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',5,'Jumlah unit alat','jumlah_unit_alat','angka','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',6,'Kepemilikan: SD / SW','kepemilikan_sd_sw','pilihan','Master','','banyak','SD = sendiri, SW = sewa');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',7,'Kondisi: terawat / tidak terawat','kondisi_terawat_tidak_terawat','pilihan','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',8,'Logbook: ada / tidak ada','logbook_ada_tidak_ada','pilihan','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('29',9,'Rata-rata waktu penggunaan (jam/minggu)','rata_rata_waktu_penggunaan_jam_minggu','angka','Master','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('30',30,'30. Wahana Praktik: Rumah Sakit/Sarana Pelayanan Kesehatan Lain/Industri di Program Studi','K6',1);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',2,'Nama wahana praktik','nama_wahana_praktik','relasi','Master','','banyak','RS, RB, BPM, Puskesmas, RSJ, panti');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',3,'Kualifikasi','kualifikasi','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',4,'Kompetensi program studi','kompetensi_program_studi','Teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',5,'Kecukupan variasi kasus','kecukupan_variasi_kasus','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',6,'Jumlah dosen pembimbing klinik','jumlah_dosen_pembimbing_klinik','angka','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',7,'Jumlah mahasiswa','jumlah_mahasiswa','angka','Hitung','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',8,'Kontribusi untuk pendidikan','kontribusi_untuk_pendidikan','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('30',9,'Dokumen kerja sama','dokumen_kerja_sama','relasi','Master','','banyak','Ke modul Kerja Sama');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('31',31,'31. Rekapitulasi Jumlah Ketersediaan Pustaka yang Relevan dengan Bidang Program Studi, Baik dalam Format Cetak maupun Elektronik.','K6',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('31',1,'No / Jenis pustaka','no_jenis_pustaka','pilihan','Master','','banyak','Buku teks, jurnal nasional terakreditasi, jurnal internasional, prosiding');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('31',2,'Jumlah judul','jumlah_judul','angka','Hitung','','banyak','COUNT dari master pustaka');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('31',3,'Jumlah copy','jumlah_copy','angka','Hitung','','banyak','SUM dari master pustaka');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('32',32,'32. Jurnal yang Tersedia/yang Diterima secara Teratur (Lengkap), Terbitan Tiga Tahun Terakhir dalam Format Cetak maupun Elektronik.','K6',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('32',1,'Jenis','jenis','pilihan','Input','','banyak','Jurnal terakreditasi Dikti, jurnal internasional bereputasi');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('32',2,'Nama jurnal','nama_jurnal','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('32',3,'Rincian tahun dan nomor','rincian_tahun_dan_nomor','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('32',4,'Jumlah','jumlah','angka','Input','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('33',33,'33. Jumlah Penerimaan Dana di Unit Pengelola Program Studi','K6',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('33',1,'Sumber dana','sumber_dana','pilihan','Master','','banyak','Mahasiswa, usaha sendiri, pemerintah pusat, pemerintah daerah, lain');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('33',2,'Jenis dana','jenis_dana','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('33',3,'Jumlah dana TS-2 / TS-1 / TS (juta Rp)','jumlah_dana_ts_2_ts_1_ts_juta_rp','angka','Input','','banyak','Satuan juta rupiah, bukan rupiah penuh');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('33',4,'Rata-rata per tahun','rata_rata_per_tahun','angka','Hitung','','banyak','Rata-rata 3 kolom');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('34',34,'34. Jumlah Penggunaan Dana di Unit Pengelola Program Studi','K6',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('34',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('34',2,'Jenis penggunaan','jenis_penggunaan','pilihan','Master','','banyak','Pendidikan (gaji dosen, gaji tendik, operasional pembelajaran, operasional kantor), penelitian, PkM');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('34',3,'Jumlah dana TS-2 / TS-1 / TS (juta Rp)','jumlah_dana_ts_2_ts_1_ts_juta_rp','angka','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('34',4,'Persentase TS-2 / TS-1 / TS','persentase_ts_2_ts_1_ts','persen','Hitung','','banyak','Dihitung dari total penggunaan tahun tsb');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('34',5,'Total dan rata-rata','total_dan_rata_rata','angka','Hitung','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('35',35,'35. Evaluasi dan Pengendalian Sistem Penjaminan Mutu Internal','K7',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',2,'Nama program studi','nama_program_studi','relasi','Master','','banyak','Satu baris per prodi');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',3,'Standar yang diacu','standar_yang_diacu','teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',4,'Pelaksanaan standar','pelaksanaan_standar','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',5,'Monev audit mutu internal','monev_audit_mutu_internal','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',6,'Umpan balik audit','umpan_balik_audit','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',7,'Tindak lanjut audit','tindak_lanjut_audit','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',8,'Tanggal audit mutu internal','tanggal_audit_mutu_internal','tanggal','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('35',9,'Keterangan','keterangan','Teks','Input','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('36',36,'36. Ketersediaan Dokumen/ Buku Sistem Penjaminan Mutu Internal','K7',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('36',1,'No','no','Input','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('36',2,'Jenis dokumen','jenis_dokumen','pilihan','Master','','banyak','Kebijakan, manual, standar, formulir SPMI');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('36',3,'Ketersediaan','ketersediaan','pilihan','Input','','banyak','Tersedia / Tidak');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('37',37,'37. Penjaminan Mutu Eksternal','K7',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',2,'Nama program studi','nama_program_studi','relasi','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',3,'Hasil akreditasi internasional','hasil_akreditasi_internasional','teks','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',4,'Hasil akreditasi kuantitatif: Minimum','hasil_akreditasi_kuantitatif_minimum','centang','Master','hasil_kuantitatif','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',5,'Hasil akreditasi kuantitatif: A-Unggul','hasil_akreditasi_kuantitatif_a_unggul','centang','Master','hasil_kuantitatif','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',6,'Hasil akreditasi kuantitatif: B-Baik Sekali','hasil_akreditasi_kuantitatif_b_baik_sekali','centang','Master','hasil_kuantitatif','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',7,'Hasil akreditasi kuantitatif: C-Baik','hasil_akreditasi_kuantitatif_c_baik','centang','Master','hasil_kuantitatif','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',8,'Hasil akreditasi kualitatif: Sementara','hasil_akreditasi_kualitatif_sementara','centang','Master','hasil_kualitatif','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',9,'Hasil akreditasi kualitatif: Terakreditasi','hasil_akreditasi_kualitatif_terakreditasi','centang','Master','hasil_kualitatif','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',10,'Hasil akreditasi kualitatif: Unggul','hasil_akreditasi_kualitatif_unggul','centang','Master','hasil_kualitatif','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',11,'Hasil akreditasi kualitatif: Kadaluwarsa','hasil_akreditasi_kualitatif_kadaluwarsa','centang','Master','hasil_kualitatif','satu','Satu centang per baris');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',12,'Tanggal penetapan akreditasi','tanggal_penetapan_akreditasi','tanggal','Master','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',13,'Tanggal berakhir masa akreditasi','tanggal_berakhir_masa_akreditasi','tanggal','Master','','banyak','Dasar peringatan kadaluarsa');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('37',14,'Keterangan','keterangan','Teks','Input','','banyak','-');
INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES ('38',38,'38. Kerja sama Unit Pengelola Program Studi yang Relevan dengan Program Studi yang sedang diakreditasi','K8',0);
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',1,'No','no','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',2,'Nama instansi','nama_instansi','Teks','Input','','banyak','Wajib');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',3,'Dalam negeri','dalam_negeri','centang','Input','lokasi_mitra','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',4,'Luar negeri','luar_negeri','centang','Input','lokasi_mitra','satu','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',5,'jenis_kegiatan: Pendidikan','jenis_kegiatan_pendidikan','centang','Input','jenis_kegiatan','banyak','Boleh lebih dari satu');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',6,'jenis_kegiatan: Penelitian','jenis_kegiatan_penelitian','centang','Input','jenis_kegiatan','banyak','Boleh lebih dari satu');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',7,'jenis_kegiatan: PkM','jenis_kegiatan_pkm','centang','Input','jenis_kegiatan','banyak','Boleh lebih dari satu');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',8,'Kurun waktu: mulai','kurun_waktu_mulai','tanggal','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',9,'Kurun waktu: berakhir','kurun_waktu_berakhir','tanggal','Input','','banyak','Boleh kosong bila berlaku terus');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',10,'Hasil atau manfaat','hasil_atau_manfaat','Teks','Input','','banyak','-');
INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES ('38',11,'Ketersediaan laporan pelaksanaan','ketersediaan_laporan_pelaksanaan','ya/tidak','Input','','banyak','-');

SELECT CONCAT('seed: ', (SELECT COUNT(*) FROM tabel_dukung), ' tabel, ', (SELECT COUNT(*) FROM spec_field), ' field') AS hasil;