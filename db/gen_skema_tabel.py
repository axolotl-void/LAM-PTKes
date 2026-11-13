"""Hasilkan spesifikasi 38 tabel + sel data dari field-spec.json.

Keluaran:
  migrasi/003_tabel.sql   tabel_dukung, spec_field, baris_tabel, sel
  ../web/spesifikasi/tabel.php  spec yang dibaca PHP saat runtime

Kenapa spec disimpan di DB DAN di PHP: DB untuk query (rekap, validasi),
PHP untuk render form tanpa query berulang. Sumbernya tetap satu berkas ini,
jadi tidak mungkin bertentangan.

Label field di docx bukan nama kolom — ada spasi, tanda kurung, garis miring.
`kunci` = slug yang dipakai sebagai nama kolom logis di tabel `sel`.
"""
import json
import re
import sys
import unicodedata

sys.path.insert(0, __file__.rsplit("/", 1)[0])
from aturan_kelompok import pecah_label  # noqa: E402

SRC = "field-spec.json"
OUT_SQL = "migrasi/004_tabel.sql"
OUT_PHP = "../web/spesifikasi/tabel.php"

# Peruntukan per sheet dari docx; 38 sheet = tabel 01..38.
PERUNTUKAN_KHUSUS = {4, 6, 7, 8, 11, 30}

# Tipe -> kolom penyimpanan. Hanya field ber-asal Input yang disimpan.
SIMPAN_ANGKA = {"angka", "desimal", "tahun akademik", "tanggal"}


def slug(s):
    s = unicodedata.normalize("NFKD", s)
    s = re.sub(r"[^a-zA-Z0-9]+", "_", s).strip("_").lower()
    s = re.sub(r"_+", "_", s)
    return s[:60] or "field"


def esc(s):
    return s.replace("\\", "\\\\").replace("'", "\\'")


def main():
    d = json.load(open(SRC))
    tabel = []
    for h in d:
        head = h["header"][0].lower()
        if head != "field isian":
            continue
        m = re.search(r"Sheet (\d+)", h["judul"])
        if not m:
            continue
        no = int(m.group(1))
        krit = re.search(r"Kriteria (\d+)", h["judul"]).group(1)
        khusus = "Khusus Profesi" in h["judul"]
        fields = []
        dipakai = set()
        for r in h["rows"]:
            label, tipe, asal = r[0], r[1], r[2]
            ket = r[3] if len(r) > 3 else ""
            # Satu label instrumen bisa menaungi beberapa kolom (Tabel 22:
            # "Tahun Penelitian" di atas TS-2 | TS-1 | TS). Dipecah dulu supaya
            # tiap kolom punya baris sendiri — tanpa itu kolomnya tidak bisa
            # dibedakan dan aturan "pilih satu" tidak bisa diterapkan.
            for label_final, kel, pilih in pecah_label(f"{no:02d}", label):
                urut = len(fields) + 1
                kunci = slug(label_final)
                dasar, n = kunci, 2
                while kunci in dipakai:                 # label kembar dalam satu tabel
                    kunci = f"{dasar}_{n}"
                    n += 1
                dipakai.add(kunci)
                fields.append({
                    "urut": urut, "label": label_final, "kunci": kunci, "tipe": tipe,
                    "asal": asal if asal in ("Input", "Hitung", "Master") else "Tetap",
                    "ket": ket, "kelompok": kel, "pilih": pilih,
                })
        assert no not in [t["no"] for t in tabel], f"sheet {no} dobel"
        # `kriteria` di docx hanya angka ("2"); tabel `kriteria` memakai 'K2'.
        # Tanpa awalan 'K', join per kriteria tidak pernah cocok — ini bug nyata
        # yang tertangkap uji kerangka (semua kriteria jadi 0).
        tabel.append({"no": no, "kode": f"{no:02d}", "kriteria": "K" + krit,
                      "khusus": khusus, "fields": fields})

    tabel.sort(key=lambda t: t["no"])
    assert [t["no"] for t in tabel] == list(range(1, 39)), "harus 38 tabel 1..38"

    total = sum(len(t["fields"]) for t in tabel)
    hitung = {"Input": 0, "Hitung": 0, "Master": 0, "Tetap": 0}
    for t in tabel:
        for f in t["fields"]:
            hitung[f["asal"]] += 1
    print("tabel:", len(tabel), "| field:", total, "|", hitung)

    # ── SQL ──────────────────────────────────────────────────────────
    sql = ["-- LAM-PTKes — 003: 38 tabel data dukung (dibangkitkan, jangan disunting)",
           "-- Sumber: db/field-spec.json + db/gen_skema_tabel.py",
           "-- Angka: 38 tabel, %d field (Input %d / Hitung %d / Master %d / Tetap %d)"
           % (total, hitung["Input"], hitung["Hitung"], hitung["Master"], hitung["Tetap"]),
           "SET NAMES utf8mb4;", ""]

    # `kriteria` sudah dibuat + di-seed oleh 003_dokumen.sql, jadi di sini
    # hanya dipakai sebagai FK.
    sql.append("""CREATE TABLE tabel_dukung (
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
""")

    for t in tabel:
        sql.append("INSERT INTO tabel_dukung (kode,nomor,judul,kriteria,khusus_profesi) VALUES "
                   "('%s',%d,'%s','%s',%d);" % (t["kode"], t["no"], esc(t["kode"] + ". Tabel " + t["kode"]), t["kriteria"], 1 if t["khusus"] else 0))
        for f in t["fields"]:
            sql.append("INSERT INTO spec_field (tabel_kode,urut,label,kunci,tipe,asal,kelompok,pilih,keterangan) VALUES "
                       "('%s',%d,'%s','%s','%s','%s','%s','%s','%s');"
                       % (t["kode"], f["urut"], esc(f["label"]), f["kunci"],
                          esc(f["tipe"]), f["asal"], esc(f["kelompok"]),
                          f["pilih"] or "banyak", esc(f["ket"])))
    sql.append("")
    sql.append("SELECT CONCAT('seed: ', (SELECT COUNT(*) FROM tabel_dukung), ' tabel, ', "
               "(SELECT COUNT(*) FROM spec_field), ' field') AS hasil;")

    open(OUT_SQL, "w").write("\n".join(sql))

    # ── PHP ──────────────────────────────────────────────────────────
    import os
    os.makedirs(os.path.dirname(OUT_PHP), exist_ok=True)
    php = ["<?php",
           "declare(strict_types=1);",
           "/** Spesifikasi 38 tabel — DIBANGKITKAN dari db/gen_skema_tabel.py.",
           " *  Jangan disunting tangan: ubah field-spec.json lalu jalankan ulang.",
           " *  Angka: %d tabel, %d field. */" % (len(tabel), total),
           "return ["]
    for t in tabel:
        php.append("    '%s' => [" % t["kode"])
        php.append("        'nomor' => %d, 'kriteria' => '%s', 'khusus' => %s,"
                   % (t["no"], t["kriteria"], "true" if t["khusus"] else "false"))
        php.append("        'fields' => [")
        for f in t["fields"]:
            php.append("            ['urut'=>%d,'label'=>'%s','kunci'=>'%s','tipe'=>'%s','asal'=>'%s','kelompok'=>'%s','pilih'=>'%s','ket'=>'%s'],"
                       % (f["urut"], esc(f["label"]), f["kunci"], esc(f["tipe"]),
                          f["asal"], esc(f["kelompok"]), f["pilih"] or "banyak",
                          esc(f["ket"])))
        php.append("        ],")
        php.append("    ],")
    php.append("];")
    open(OUT_PHP, "w").write("\n".join(php) + "\n")
    print("tulis:", OUT_SQL, "dan", OUT_PHP)


if __name__ == "__main__":
    main()
