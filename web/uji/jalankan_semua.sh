#!/bin/bash
# Jalankan SEMUA uji berurutan. Dipakai setelah swarm selesai: uji yang lulus
# sendiri-sendiri bisa gagal saat dijalankan berurutan karena meninggalkan data.
cd "$(dirname "$0")/../.." || exit 1
gagal=0
total=0
for f in web/uji/*.php; do
  total=$((total + 1))
  nama=$(basename "$f")
  keluaran=$(timeout 120 php "$f" 2>&1)
  kode=$?
  ok=$(printf '%s' "$keluaran" | grep -c '^  ok  ')
  if [ $kode -eq 0 ]; then
    printf 'LULUS  %-28s %3d uji\n' "$nama" "$ok"
  else
    printf 'GAGAL  %-28s %3d uji  (exit %d)\n' "$nama" "$ok" "$kode"
    printf '%s\n' "$keluaran" | grep -E 'GAGAL|Fatal|Error' | head -5 | sed 's/^/         /'
    gagal=$((gagal + 1))
  fi
done
echo "──────────────────────────────────────────"
echo "$((total - gagal))/$total berkas uji lulus"
exit $gagal
