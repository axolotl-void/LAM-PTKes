<?php
/**
 * Uji lapisan rekap bersama. Ini yang mencegah dashboard dan Data Dukung
 * menampilkan angka berbeda.
 *
 * Jalankan: php web/uji/uji_rekap.php
 */
declare(strict_types=1);

ob_start();
putenv('LAMPTKES_ENV=lokal');
require_once __DIR__ . '/../system/rekap.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) { $gagal++; }
}

$prodi = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1');
$per = periode_aktif($prodi);
cek('periode aktif ada', $per !== null);
$perId = (int) ($per['id'] ?? 0);

// Kondisi awal: belum ada baris, semua wajib kosong.
$r = rekap_ringkas($prodi, $perId);
cek('total 38 tabel', $r['total'] === 38, "dapat {$r['total']}");
cek('semua wajib (punya tahap profesi)', $r['wajib'] === 38, "dapat {$r['wajib']}");
cek('0 selesai di awal', $r['selesai'] === 0, "dapat {$r['selesai']}");
// Status ke-6: 'hitung' untuk tabel yang SELURUH field-nya dihitung sistem
// (0 field Input). Operator tidak punya pekerjaan mengisi tabel itu, jadi
// menyebutnya 'kosong' = menyalahkan orang atas pekerjaan yang tidak ada.
cek('angka tutup: lengkap+terkunci+sebagian+kosong+hitung = wajib',
    $r['lengkap'] + $r['terkunci'] + $r['sebagian'] + $r['kosong'] + $r['hitung'] === $r['wajib'], json_encode($r));
cek('selesai + belum = wajib', $r['selesai'] + $r['belum'] === $r['wajib']);

// Semua tabel kosong -> status kosong, bukan "lengkap" karena field_total 0
$semua = rekap_semua_tabel($prodi, $perId);
cek('38 baris rekap', count($semua) === 38);
cek('tidak ada yang lengkap tanpa baris',
    count(array_filter($semua, fn($t) => $t['status'] === 'lengkap')) === 0);

// Klasifikasi 'hitung' dikunci: tepat 6 tabel. Kalau daftar ini berubah, ada
// field Input yang hilang atau tabel baru yang seluruhnya jadi Hitung/Master —
// harus ketahuan, bukan lolos diam-diam.
$kodeHitung = array_values(array_map(fn($t) => $t['kode'],
    array_filter($semua, fn($t) => $t['status'] === 'hitung')));
sort($kodeHitung);
cek('tepat 8 tabel berstatus hitung', count($kodeHitung) === 8, implode(',', $kodeHitung));
// Daftar ini SENGAJA eksplisit — kalau ada tabel lain jadi hitung tanpa
// disengaja, itu berarti field Input-nya hilang dan harus ketahuan.
// 15 & 21 ikut: bentuknya matriks/hitungan dari master (dosen, bimbingan),
// bukan isian operator. 15 = 0 field Input; 21 punya satu "No" (nomor urut).
cek('tabel hitung = 03,04,05,06,09,15,21,31',
    $kodeHitung === ['03', '04', '05', '06', '09', '15', '21', '31'],
    implode(',', $kodeHitung));

// Bukan sekadar angka cocok: tiap tabel 'hitung' memang tidak punya kolom
// isian (kecuali "No"). Diperiksa di bawah lewat $fieldHitung.
// Tabel 21 punya satu field Input ("No") tapi statusnya tetap hitung karena
// varian='hitung'. Kunci invariannya: yang dihitung otomatis TIDAK BOLEH
// punya kolom isian selain "No".
$fieldHitung = q("SELECT tabel_kode, label FROM spec_field
                  WHERE asal = 'Input' AND tabel_kode IN ('03','04','05','06','09','15','21','31')
                  AND label <> 'No'");
cek('tabel hitung tidak punya kolom isian selain "No"', $fieldHitung === [],
    json_encode($fieldHitung));

// Tabel dengan asal Input hanya 109 dari 243 -> total per tabel tidak boleh 243
$t1 = q1("SELECT COUNT(*) n FROM spec_field WHERE tabel_kode = '01' AND asal = 'Input'");
cek('field Input tabel 01 terhitung', (int) $t1['n'] > 0, "dapat {$t1['n']}");

// Kriteria: K1 tidak punya tabel
$perKrit = rekap_per_kriteria($prodi, $perId);
$k1 = null;
foreach ($perKrit as $k) { if ($k['kriteria'] === 'K1') { $k1 = $k; } }
cek('K1 ada di rekap', $k1 !== null);
cek('K1 total 0 tabel (hanya dokumen)', $k1 !== null && $k1['total'] === 0, json_encode($k1));
$sumTotal = array_sum(array_column($perKrit, 'total'));
cek('jumlah tabel per kriteria = 38', $sumTotal === 38, "dapat $sumTotal");

// Dokumen: 78 butir, 0 terunggah
$d = rekap_dokumen($prodi, $perId);
cek('78 dokumen', $d['total'] === 78, "dapat {$d['total']}");
cek('0 terunggah di awal', $d['unggah'] === 0);
cek('belum = total - unggah', $d['belum'] === $d['total'] - $d['unggah']);

$perDok = rekap_dokumen_per_kriteria($prodi, $perId);
$sumDok = array_sum(array_column($perDok, 'total'));
cek('jumlah dokumen per kriteria = 78', $sumDok === 78, "dapat $sumDok");

// Janji inti: status diturunkan dari data, bukan disimpan.
// Sisipkan 1 baris + isi semua field Input tabel 02, lalu status harus lengkap.
// Pakai tabel dengan >1 field Input supaya "hapus satu -> sebagian" bermakna.
$kandidat = qv("SELECT tabel_kode FROM spec_field WHERE asal = 'Input'
                GROUP BY tabel_kode HAVING COUNT(*) > 1 ORDER BY tabel_kode LIMIT 1");
cek('ada tabel dengan >1 field Input', $kandidat !== null, (string) $kandidat);
$tabelUji = (string) $kandidat;
$fields = q("SELECT kunci FROM spec_field WHERE tabel_kode = ? AND asal = 'Input'", [$tabelUji]);
$bid = sisip('baris_tabel', [
    'prodi_id' => $prodi, 'periode_id' => $perId, 'tabel_kode' => $tabelUji, 'urut' => 1,
]);
foreach ($fields as $f) {
    sisip('sel', ['baris_id' => $bid, 'field_kunci' => $f['kunci'], 'nilai_teks' => 'x']);
}
$s = status_satu_tabel($tabelUji, $prodi, $perId);
cek('status jadi lengkap setelah semua field diisi', $s['status'] === 'lengkap', json_encode($s));
cek('terisi = total', $s['terisi'] === $s['total'], "{$s['terisi']}/{$s['total']}");

// Hapus satu sel -> harus turun jadi sebagian. Ini bukti status turunan.
ex('DELETE FROM sel WHERE baris_id = ? AND field_kunci = ?', [$bid, $fields[0]['kunci']]);
$s2 = status_satu_tabel($tabelUji, $prodi, $perId);
cek('hapus satu field -> sebagian (status turunan)', $s2['status'] === 'sebagian', json_encode($s2));

// Kunci baris -> status terkunci mengalahkan sebagian
ex('UPDATE baris_tabel SET dikunci_oleh = (SELECT id FROM pengguna WHERE peran_kode = "ADMIN" LIMIT 1) WHERE id = ?', [$bid]);
$s3 = status_satu_tabel($tabelUji, $prodi, $perId);
cek('terkunci mengalahkan sebagian', $s3['status'] === 'terkunci', json_encode($s3));

// Bersihkan
ex('DELETE FROM baris_tabel WHERE id = ?', [$bid]);

// Ringkasan kembali ke 0
$r2 = rekap_ringkas($prodi, $perId);
cek('kembali 0 selesai setelah bersih', $r2['selesai'] === 0, json_encode($r2));

// Tabel khusus profesi untuk prodi tanpa tahap profesi -> na
ex('UPDATE prodi SET punya_tahap_profesi = 0 WHERE id = ?', [$prodi]);
$s4 = status_satu_tabel('30', $prodi, $perId);
cek('tabel 30 jadi na bila tanpa tahap profesi', $s4['status'] === 'na', json_encode($s4));
$r3 = rekap_ringkas($prodi, $perId);
cek('wajib turun jadi 32 bila tanpa tahap profesi', $r3['wajib'] === 32, "dapat {$r3['wajib']}");
ex('UPDATE prodi SET punya_tahap_profesi = 1 WHERE id = ?', [$prodi]);

while (ob_get_level() > 0) { ob_end_flush(); }
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
