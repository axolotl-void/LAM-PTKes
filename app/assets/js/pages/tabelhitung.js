/* tabelhitung — tabel yang angkanya dihitung dari master (#/tabelhitung/02).
 *
 * Bentuk tabel berbeda-beda (7 baris tahun, 3 baris IPK, matriks prodi), tapi
 * SEMUA keluar dari server dalam bentuk seragam:
 *   { judul, kolom:[...], baris:[{sel:[...], kelas}], catatan, kosong }
 *
 * Kenapa seragam: satu komponen tampilan untuk 15 tabel. Kalau tiap tabel
 * punya HTML sendiri, satu perubahan gaya harus disalin 15 kali dan pasti ada
 * yang tertinggal.
 *
 * CATATAN dari server SELALU ditampilkan. Isinya keterbatasan dan tafsir yang
 * diambil (mis. instrumen menulis "6 < MT < 1" yang mustahil). Menyembunyikan
 * itu membuat angka terlihat lebih pasti daripada kenyataannya — dan asesor
 * berhak tahu.
 */
import { esc, head, strip, card, cardHead, callout } from '../pagekit.js';

let kode = null;
let data = null;
let galat = '';

export async function muat() {
  const h = location.hash.replace('#/tabelhitung/', '').replace('#/tabelhitung', '');
  kode = h.replace(/[^0-9]/g, '').padStart(2, '0');
  if (!kode || kode === '00') { galat = 'Kode tabel tidak dikenal.'; return; }
  try {
    const r = await fetch(`/api/tabel/${kode}/hitung`, { credentials: 'same-origin' });
    const j = await r.json();
    if (!r.ok || j.error) { galat = j.error || `HTTP ${r.status}`; data = null; }
    else { data = j; galat = ''; }
  } catch (e) {
    galat = 'Server tidak merespons.';
  }
  if (window.__paint) window.__paint();
}

/* Nilai kosong ditampilkan "—", bukan 0. Nol berarti memang nol; kosong
   berarti tidak bisa dihitung. Dua hal itu berbeda dan asesor membedakannya. */
const tampil = (v) => {
  if (v === null || v === undefined || v === '') return '<span class="muted">—</span>';
  if (typeof v === 'number') {
    return Number.isInteger(v) ? String(v)
      : v.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  return esc(String(v));
};

function page() {
  if (!data) {
    return head(`Tabel ${kode || ''}`, '',
      '<a class="btn btn-ghost" href="#/datadukung">← Daftar tabel</a>')
      + (galat
        ? callout('bad', 'Tidak bisa dimuat', `<p>${esc(galat)}</p>`)
        : '<p class="muted">Memuat…</p>');
  }

  const { judul, kolom, baris, catatan, kosong } = data;
  const adaIsi = Array.isArray(baris) && baris.length > 0;

  return head(`Tabel ${kode}`,
    `${esc(judul || '')} · dihitung dari master`,
    '<a class="btn btn-ghost" href="#/datadukung">← Daftar tabel</a>')
    + callout('info', 'Tabel ini tidak diisi manual',
      `<p>Angka dihitung dari master yang sudah diisi di halaman lain. `
      + `Memperbaiki master langsung mengubah tabel ini.</p>`)
    + (catatan ? callout('warn', 'Yang perlu diketahui', `<p>${esc(catatan)}</p>`) : '')
    + (adaIsi
      ? card(cardHead(judul || `Tabel ${kode}`, `${baris.length} baris`)
        + `<div style="overflow-x:auto">
          <table class="tbl tbl-hitung">
            <thead><tr>${(kolom || []).map((k) => `<th>${esc(k)}</th>`).join('')}</tr></thead>
            <tbody>${baris.map((b) => `<tr class="${esc(b.kelas || '')}">${(b.sel || [])
              .map((v, i) => `<td${i > 0 ? ' class="r"' : ''}>${tampil(v)}</td>`).join('')}</tr>`).join('')}
            </tbody>
          </table>
        </div>`)
      : callout('info', 'Belum ada angka',
        `<p>${esc(kosong || 'Master yang dibutuhkan tabel ini belum diisi.')}</p>`));
}

export default page;
