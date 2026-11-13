-- LAM-PTKes — 002: uji jaminan skema
-- Bukan uji fitur. Yang diuji di sini adalah hal-hal yang DIJANJIKAN skema:
-- peran ganda mustahil, IPK di luar 0–4 ditolak, nonce SSO tidak bisa dipakai
-- dua kali, dan seterusnya. Setiap uji yang tidak menolak akan menghentikan
-- skrip dengan SIGNAL, jadi menjalankan berkas ini = menjalankan pengujian.
--
-- Jalankan:  mysql -uroot -p bbg_lamptkes < 002_uji.sql
-- Hasil:     satu baris "LULUS n uji" atau berhenti dengan pesan uji mana.

SET NAMES utf8mb4;

-- Helper: jalankan satu INSERT yang SEHARUSNYA ditolak, lalu bersihkan
-- kalau ternyata lolos (supaya sisa data tidak mengganggu uji berikutnya).
DROP PROCEDURE IF EXISTS harus_tolak;
DELIMITER //
CREATE PROCEDURE harus_tolak(IN nama_uji VARCHAR(80), IN sql_uji TEXT)
BEGIN
  DECLARE ditolak INT DEFAULT 0;
  DECLARE pesan TEXT;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN
        SET ditolak = 1;
        GET DIAGNOSTICS CONDITION 1 pesan = MESSAGE_TEXT;
      END;
    SET @s = sql_uji;
    PREPARE st FROM @s;
    EXECUTE st;
    DEALLOCATE PREPARE st;
  END;
  IF ditolak = 0 THEN
    -- MESSAGE_TEXT SIGNAL tidak menerima CONCAT langsung; harus lewat variabel.
    SET pesan = CONCAT('GAGAL uji: ', nama_uji);
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = pesan;
  END IF;
  SELECT CONCAT('  ok  ', nama_uji) AS uji;
END//
DELIMITER ;

-- Helper kedua: periksa kenyataan di information_schema. IF/SIGNAL hanya sah
-- di dalam stored program, jadi pemeriksaan dibungkus prosedur.
DROP PROCEDURE IF EXISTS pastikan;
DELIMITER //
CREATE PROCEDURE pastikan(IN nama_uji VARCHAR(80), IN sql_hitung TEXT, IN harapan INT)
BEGIN
  -- `EXECUTE stmt INTO` tidak ada di MySQL; INTO harus jadi bagian teks SQL.
  SET @hasil = NULL;
  SET @h = CONCAT('SELECT (', sql_hitung, ') INTO @hasil');
  PREPARE sh FROM @h;
  EXECUTE sh;
  DEALLOCATE PREPARE sh;
  IF @hasil IS NULL OR @hasil <> harapan THEN
    SET @pesan = CONCAT('GAGAL uji: ', nama_uji, ' (dapat ', IFNULL(@hasil,'NULL'), ', harusnya ', harapan, ')');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @pesan;
  END IF;
  SELECT CONCAT('  ok  ', nama_uji) AS uji;
END//
DELIMITER ;

-- ── data dasar supaya foreign key bisa diuji ─────────────────────────
INSERT INTO upps (nama_pt, nama_upps) VALUES ('Universitas Uji', 'UPPS Uji');
INSERT INTO prodi (upps_id, nama_ps, jenjang, bidang, punya_tahap_profesi)
  VALUES (LAST_INSERT_ID(), 'Kebidanan', 'Sarjana', 'Kebidanan', 1);
SET @prodi = LAST_INSERT_ID();

INSERT INTO mahasiswa (prodi_id, nim, nama, tahun_masuk) VALUES (@prodi, 'UJI001', 'Mhs Uji', 2024);
INSERT INTO tracer (prodi_id, tahun_lulus, jumlah_lulusan, terlacak) VALUES (@prodi, 2024, 10, 8);
INSERT INTO uji_kompetensi (prodi_id, jenis, periode, peserta_total, ft_peserta, ft_lulus)
  VALUES (@prodi, 'CBT', 2025, 20, 20, 18);
INSERT INTO sso_nonce (nonce, email) VALUES ('0123456789abcdef0123456789abcdef', 'uji@bbg.ac.id');
INSERT INTO pengguna (email, nama, peran_kode) VALUES ('uji@bbg.ac.id', 'Akun Uji', 'OPERATOR');

-- ── uji ──────────────────────────────────────────────────────────────
CALL harus_tolak('IPK di atas 4,00 ditolak',
  "INSERT INTO mahasiswa (prodi_id,nim,nama,tahun_masuk,ipk) VALUES (@prodi,'UJI002','Mhs IPK',2024,4.50)");

CALL harus_tolak('IPK negatif ditolak',
  "INSERT INTO mahasiswa (prodi_id,nim,nama,tahun_masuk,ipk) VALUES (@prodi,'UJI003','Mhs IPK',2024,-0.20)");

CALL harus_tolak('NIM ganda ditolak',
  "INSERT INTO mahasiswa (prodi_id,nim,nama,tahun_masuk) VALUES (@prodi,'UJI001','Mhs Kembar',2024)");

CALL harus_tolak('terlacak > jumlah lulusan ditolak',
  "INSERT INTO tracer (prodi_id,tahun_lulus,jumlah_lulusan,terlacak) VALUES (@prodi,2023,10,11)");

CALL harus_tolak('lulus UKOM > peserta ditolak',
  "INSERT INTO uji_kompetensi (prodi_id,jenis,periode,ft_peserta,ft_lulus) VALUES (@prodi,'OSCE',2025,10,11)");

CALL harus_tolak('nonce SSO dipakai dua kali ditolak',
  "INSERT INTO sso_nonce (nonce,email) VALUES ('0123456789abcdef0123456789abcdef','lain@bbg.ac.id')");

CALL harus_tolak('akses izin di luar 0..2 ditolak',
  "INSERT INTO izin (peran_kode,aksi_kode,akses) VALUES ('OPERATOR','EKSPOR',9)");

CALL harus_tolak('peran tidak dikenal ditolak',
  "INSERT INTO pengguna (email,nama,peran_kode) VALUES ('x@bbg.ac.id','X','SUPERUSER')");

CALL harus_tolak('email akun ganda ditolak',
  "INSERT INTO pengguna (email,nama,peran_kode) VALUES ('uji@bbg.ac.id','A','OPERATOR')");

-- peran ganda mustahil secara bentuk: satu kolom `peran_kode`, bukan tabel
-- penghubung. Uji bahwa kolomnya memang tunggal.
CALL pastikan('peran ganda mustahil (satu kolom peran)',
  "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE()
     AND table_name='pengguna' AND column_name='peran_kode'", 1);

-- dan tidak ada tabel penghubung peran yang tertinggal
CALL pastikan('tidak ada tabel peran ganda',
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()
     AND table_name IN ('pengguna_peran','pengguna_roles')", 0);

CALL pastikan('matriks izin lengkap 24 baris', 'SELECT COUNT(*) FROM izin', 24);

-- pemisahan tugas: operator tidak verifikasi, verifikator tidak isi
CALL pastikan('operator tidak boleh verifikasi',
  "SELECT akses FROM izin WHERE peran_kode='OPERATOR' AND aksi_kode='VERIFIKASI'", 0);
CALL pastikan('verifikator tidak boleh isi',
  "SELECT akses FROM izin WHERE peran_kode='VERIFIKATOR' AND aksi_kode='ISI'", 0);
CALL pastikan('pimpinan hanya lihat (akses 2)',
  "SELECT akses FROM izin WHERE peran_kode='PIMPINAN' AND aksi_kode='ISI'", 2);

-- setiap peran punya tepat 6 baris izin; kalau ada aksi baru tanpa izin,
-- server akan menolak semua orang tanpa penjelasan
CALL pastikan('setiap peran punya 6 baris izin',
  "SELECT COUNT(*) FROM (SELECT peran_kode FROM izin GROUP BY peran_kode HAVING COUNT(*)<>6) x", 0);

-- bersihkan data uji, sisakan seed (peran, aksi, izin)
DELETE FROM uji_kompetensi; DELETE FROM tracer; DELETE FROM mahasiswa;
DELETE FROM sso_nonce; DELETE FROM pengguna; DELETE FROM prodi; DELETE FROM upps;
DROP PROCEDURE harus_tolak;
DROP PROCEDURE pastikan;

SELECT 'LULUS 15 uji skema' AS hasil;
