"""Hasilkan migrasi 004: kriteria, 78 dokumen pendukung, 10 aturan konsistensi."""
import json

SPEC = "dokumen-spec.json"
OUT = "migrasi/003_dokumen.sql"

dok = json.load(open(SPEC))

KRITERIA = [
    ("K1", "Visi, Misi, Tujuan, dan Strategi"),
    ("K2", "Kurikulum"),
    ("K3", "Penilaian"),
    ("K4", "Mahasiswa"),
    ("K5", "Dosen, Tendik, Penelitian, PkM"),
    ("K6", "Sarana, Prasarana, dan Keuangan"),
    ("K7", "Penjaminan Mutu"),
    ("K8", "Tata Kelola dan Administrasi"),
]

# 10 aturan lintas tabel — sumber: docx §6 dan catatan 03 - Spesifikasi Isi Sistem
ATURAN = [
    ("R01", "Jumlah dosen tetap di Tabel 15 = jumlah baris nama di Tabel 16", "15", "16", "peringatan", "blokir ekspor bila berbeda"),
    ("R02", "Jumlah lulusan di Tabel 3 = akumulasi lulusan di Tabel 5", "03", "05", "peringatan", "peringatan saja"),
    ("R03", "Total mahasiswa Tabel 9 = jumlah seluruh prodi di Tabel 10", "09", "10", "peringatan", "peringatan saja"),
    ("R04", "Jumlah mahasiswa Tabel 10 = jumlah baris master mahasiswa aktif", "10", None, "blokir", "tolak simpan"),
    ("R05", "Total penerimaan dana Tabel 33 = sumber dana mahasiswa di Tabel 34", "33", "34", "peringatan", "peringatan saja"),
    ("R06", "Jumlah persen Tabel 34 per tahun = 100%", "34", None, "blokir", "tolak simpan"),
    ("R07", "Dosen di Tabel 17 harus ada di Tabel 16 atau 19", "17", "16", "blokir", "tolak simpan"),
    ("R08", "Wahana di Tabel 30 harus punya dokumen kerja sama di Tabel 38", "30", "38", "peringatan", "peringatan saja"),
    ("R09", "Mata ajar di Tabel 16 dan 19 harus ada di master Mata Kuliah", "16", None, "peringatan", "peringatan saja"),
    ("R10", "Tabel khusus profesi wajib lengkap bila punya_tahap_profesi = ya", "04", None, "wajib", "masuk checklist wajib"),
]

def esc(s):
    return str(s).replace("\\", "\\\\").replace("'", "\\'")

sql = ["""-- LAM-PTKes — 004: kriteria, 78 dokumen pendukung, 10 aturan konsistensi
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
"""]

for kode, nama in KRITERIA:
    sql.append("INSERT INTO kriteria (kode,nama) VALUES ('%s','%s');" % (kode, esc(nama)))

for i, d in enumerate(dok, start=1):
    sql.append("INSERT INTO dokumen_spec (id,kriteria,nomor,nama,rincian) VALUES (%d,'%s',%d,'%s','%s');"
               % (i, d["kriteria"] and f"K{d['kriteria']}" or "K1", d["nomor"],
                  esc(d["nama"]), esc("; ".join(d["rincian"]))))

for kode, nama, ta, tb, tingkat, catatan in ATURAN:
    sql.append("INSERT INTO konsistensi_aturan (kode,nama,tabel_a,tabel_b,tingkat,catatan) VALUES "
               "('%s','%s',%s,%s,'%s','%s');"
               % (kode, esc(nama), f"'{ta}'" if ta else "NULL",
                  f"'{tb}'" if tb else "NULL", tingkat, esc(catatan)))

sql.append("""
SELECT CONCAT('seed: ', (SELECT COUNT(*) FROM kriteria), ' kriteria, ',
  (SELECT COUNT(*) FROM dokumen_spec), ' dokumen, ',
  (SELECT COUNT(*) FROM konsistensi_aturan), ' aturan') AS hasil;""")

open(OUT, "w").write("\n".join(sql))
print("tulis", OUT, "|", len(kriteria := KRITERIA), "kriteria,", len(dok), "dokumen,", len(ATURAN), "aturan")
