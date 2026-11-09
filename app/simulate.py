#!/usr/bin/env python3
"""Simulasi pengisian: isi DB bertahap seperti petugas sungguhan bekerja.

Tujuannya dua:
1. Membuktikan alur isi -> verifikasi -> ekspor terbuka benar-benar jalan.
2. Menyisakan sebagian tabel BELUM diisi, supaya demo tetap menunjukkan
   keadaan "sedang dikerjakan", bukan "semua beres" yang tidak realistis.

Aturan yang dipegang skrip ini:
- Hanya mengisi yang masuk akal diisi operator. Tabel khusus profesi ditolak
  oleh DB sendiri, bukan dilewati diam-diam.
- Tidak menyentuh kolom status. Status turunan dari field_terisi.
- Setiap aksi tercatat di tabel `catatan`, jadi riwayatnya bisa dilihat.
"""
import sys
import time

import db

SKENARIO = [
    # (kode, terisi, catatan) — urut seperti petugas mengerjakan
    ("23", 6,  "layanan non-akademik: 6 jenis layanan terdata"),
    ("34", 5,  "dosen industri: 5 praktisi dari RSUD + RSIA"),
    ("34b", 5, "penggunaan dana: 6 jenis, total 100,0%"),
    ("42", 4,  "audit internal: 4 siklus PPEPP"),
    ("14", 239, "Tabel 1: seluruh 239 baris MK diimpor"),
    ("28", 6,  "rasio dosen-mahasiswa: 6 kolom lengkap"),
    # sengaja disisakan 2 tabel kosong supaya dashboard tidak "sempurna"
]

PELAKU = "Siti Rahmawati"


def main():
    hasil = db.init("seed.json")
    print("init:", hasil)
    r0 = db.ringkasan()
    if r0["lengkap"] != 26 or r0["belum"] != 6:
        print("DB sudah pernah diisi. Jalankan `python3 db.py reset` dulu.")
        print(f"sekarang: {r0['lengkap']}/{r0['tabel_wajib']} selesai, "
              f"{r0['belum']} belum")

        return

    print(f"\nawal: {db.ringkasan()['lengkap']}/{db.ringkasan()['tabel_wajib']} selesai\n")

    for kode, terisi, catatan in SKENARIO:
        data, kode_http, err = db.isi_tabel(kode, terisi, PELAKU)
        if err:
            print(f"  T-{kode:<4} GAGAL: {err}")
            continue
        r = db.ringkasan()
        print(f"  T-{kode:<4} {data['field_terisi']:>3}/{data['field_total']:<3} "
              f"{data['status']:<9} -> {r['lengkap']}/{r['tabel_wajib']} selesai  ({catatan})")

    # verifikasi beberapa tabel yang sudah lengkap — ini yang membuka ekspor
    print("\nverifikasi (mengunci tabel yang sudah lengkap):")
    dikunci = 0
    for t in db.daftar_tabel():
        if t["status"] == "lengkap" and not t["dikunci_oleh"] and t["kode"] in {
            "1", "2", "23", "34", "34b", "42", "14", "28",
        }:
            _, kode_http, err = db.kunci_tabel(t["kode"], "Dr. Maimunah")
            if kode_http == 200:
                dikunci += 1
                print(f"  T-{t['kode']:<4} dikunci")

    r = db.ringkasan()
    print(f"\nhasil akhir:")
    print(f"  tabel wajib : {r['tabel_wajib']}")
    print(f"  selesai     : {r['lengkap']}  (lengkap {r['lengkap'] - r['terkunci']} + terkunci {r['terkunci']})")
    print(f"  sebagian    : {r['sebagian']}")
    print(f"  kosong      : {r['kosong']}")
    print(f"  belum       : {r['belum']}")
    assert r["lengkap"] + r["sebagian"] + r["kosong"] == r["tabel_wajib"], r
    assert r["terkunci"] <= r["lengkap"], r
    print("\nriwayat 3 terakhir:")
    for c in db.riwayat(3):
        print(f"  {c['waktu']} {c['oleh']:<18} {c['aksi']:<10} T-{c['kode']}")


if __name__ == "__main__":
    main()
