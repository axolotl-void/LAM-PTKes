#!/bin/bash
# Jalankan SEMUA uji berurutan, dari DB yang bersih, dan bersihkan sesudahnya.
#
# KENAPA BERSIH DULU: uji yang mengharapkan "belum ada data" akan gagal kalau
# uji sebelumnya meninggalkan baris. Itu bukan bug kode, tapi bug urutan — dan
# uji yang hasilnya bergantung pada urutan tidak membuktikan apa pun.
cd "$(dirname "$0")/../.." || exit 1

export LAMPTKES_ENV="${LAMPTKES_ENV:-lokal}"

echo "── bersihkan DB ──"
php web/uji/bersih.php || { echo "GAGAL membersihkan DB"; exit 1; }
echo

gagal=0
total=0
daftar_gagal=()

for f in web/uji/uji_*.php; do
  total=$((total + 1))
  nama=$(basename "$f")
  keluaran=$(timeout 120 php "$f" 2>&1)
  kode=$?
  ok=$(printf '%s' "$keluaran" | grep -c '^  ok  ')
  if [ $kode -eq 0 ]; then
    printf 'LULUS  %-28s %3d uji\n' "$nama" "$ok"
  else
    printf 'GAGAL  %-28s %3d uji  (exit %d)\n' "$nama" "$ok" "$kode"
    printf '%s\n' "$keluaran" | grep -E 'GAGAL|Fatal|Uncaught' | head -5 | sed 's/^/         /'
    gagal=$((gagal + 1))
    daftar_gagal+=("$nama")
  fi
  # Uji yang meninggalkan data akan merusak uji sesudahnya. Bersihkan tiap
  # kali, bukan hanya di akhir — kalau tidak, kegagalan pertama menular.
  php web/uji/bersih.php >/dev/null 2>&1
done

echo "──────────────────────────────────────────"
printf '%d/%d berkas uji lulus\n' "$((total - gagal))" "$total"

if [ $gagal -gt 0 ]; then
  echo "Gagal: ${daftar_gagal[*]}"
  exit 1
fi
echo "SEMUA UJI LULUS"
