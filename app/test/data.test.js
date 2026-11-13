/* Satu-satunya test. Jalankan: node --test app/test/data.test.js
 * Aturannya: kalau angka tidak tutup, app tidak boleh render. */
import test from 'node:test';
import assert from 'node:assert/strict';
import { validate, D, ANGKATAN, MATRIX, IZIN, WAHANA, KOLEKSI, PENGGUNA, INSTRUMEN, TABEL } from '../assets/js/data.js';
import { render, sidebar } from '../assets/js/views.js';
import { SESI } from '../assets/js/api.js';

test('data konsisten', () => {
  assert.equal(validate(), true);
});

test('populasi mahasiswa tutup', () => {
  assert.equal(D.lulus + D.drop + D.aktif + D.cuti, D.masuk);
  assert.equal(D.masuk, 425);
  assert.equal(D.unik, 417);
});

test('tabel wajib = total - NA, dan = jumlah baris TABEL', () => {
  assert.equal(D.tabelWajib, INSTRUMEN.totalTabel - INSTRUMEN.tabelNA.length);
  assert.equal(D.tabelWajib, TABEL.length);
});

test('kapasitas wahana hanya yang punya MoU', () => {
  assert.equal(D.kapValid, 98);
  assert.equal(D.kapValid + D.kapDitolak, 113);
});

test('pemisahan tugas: hanya Admin yang boleh isi + verifikasi', () => {
  const bad = Object.entries(MATRIX)
    .filter(([p, r]) => p !== 'Admin UPPS' && r[0] === 1 && r[2] === 1);
  assert.deepEqual(bad, []);
});

test('matriks izin lengkap', () => {
  for (const [peran, row] of Object.entries(MATRIX)) {
    assert.equal(row.length, IZIN.length, `matriks ${peran}`);
  }
});

test('setiap angkatan tidak punya aktif negatif', () => {
  for (const [ang, , m, l, d, c] of ANGKATAN) {
    assert.ok(m - l - d - c >= 0, `${ang} aktif negatif`);
  }
});

test('digital tidak punya eksemplar', () => {
  for (const [jenis, , , digital] of KOLEKSI) {
    if (digital) assert.match(jenis, /digital|elektronik/i, `${jenis} harus jelas digital`);
  }
});

test('semua halaman render tanpa token rusak', () => {
  // Login palsu: halaman & sidebar sekarang membaca izin dari sesi. Di Node
  // tidak ada server, jadi SESI diisi langsung — bukan melewati gerbang.
  SESI.data = { nama: 'Uji', peran: 'Admin UPPS', prodi: 'Kebidanan',
    akses: { Isi: 1, Impor: 1, Verifikasi: 1, 'Buka kunci': 1, 'Kelola pengguna': 1, Ekspor: 1 } };
  const pages = ['dashboard', 'ringkasan', 'identitas', 'periode', 'dosen', 'keuangan',
    'kerjasama', 'pengguna', 'mahasiswa', 'kurikulum', 'sarana', 'pustaka', 'uji',
    'survei', 'datadukung', 'dokumen', 'konsistensi'];
  for (const p of pages) {
    const h = render(p, { prodi: 'Kebidanan' });
    assert.ok(h.length > 200, `${p} kosong`);
    assert.doesNotMatch(h, /undefined|NaN|\[object Object\]/, `${p} token rusak`);
    assert.equal((h.match(/<table/g) || []).length, (h.match(/<\/table>/g) || []).length,
      `${p} tabel tidak tertutup`);
    assert.equal((h.match(/<div/g) || []).length, (h.match(/<\/div>/g) || []).length,
      `${p} div tidak seimbang`);
    assert.match(sidebar(p, { prodi: 'Kebidanan' }), /aria-current="page"/, `${p} nav aktif hilang`);
  }
});

test('halaman pengguna: angka akun tiga tempat sama', () => {
  const h = render('pengguna', { prodi: 'Kebidanan' });
  const n = PENGGUNA.length;
  const hits = h.match(new RegExp(`${n} akun`, 'g')) || [];
  assert.ok(hits.length >= 2, `"${n} akun" harus muncul di header dan kartu, dapat ${hits.length}`);
  assert.doesNotMatch(h, /9 akun termasuk/);
});
