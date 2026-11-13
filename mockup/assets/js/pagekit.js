/* Kit bersama untuk modul halaman di `pages/`.
 *
 * Tiap halaman punya file sendiri (pages/nama.js) yang mengekspor:
 *   export const css = `...`;            // opsional, di-inject sekali
 *   export default function page(state) { return '<html string>'; }
 *
 * Aturan angka: JANGAN hitung di halaman. Ambil dari `D`, `KRITERIA`, `INSTRUMEN`
 * di data.js. Data khusus halaman boleh dideklarasikan lokal di file halaman itu
 * (const DATA = [...]) dengan komentar "// dipindah ke data.js saat backend ada".
 */

export { D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
         WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
         KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
         FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
         PERMINTAAN, K3, NIM_GANDA, UNIK, TABEL, TABEL_NA, rekapKriteria } from './data.js';

/* ── escape & format ─────────────────────────────────────────────── */
export const esc = (s) => String(s).replace(/[&<>"]/g,
  (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

/** angka gaya Indonesia: 1234 -> "1.234", 2.5 -> "2,50" */
export const nf = (n) => (typeof n === 'number' && !Number.isInteger(n))
  ? n.toFixed(2).replace('.', ',')
  : String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

/* ── komponen yang sudah ada di app.css ──────────────────────────── */

/** judul halaman + subjudul + tombol kanan */
export const head = (title, sub, actions = '') => `
  <div class="page-head">
    <div><h1>${esc(title)}</h1><div class="page-sub">${sub}</div></div>
    <div class="actions">${actions}</div>
  </div>`;

/** strip angka besar: [[nilai, label, kelas?], ...] kelas: '' | ok | warn | bad | muted */
export const strip = (cells) => `<div class="strip">${cells.map(([v, l, cls]) =>
  `<div class="cell"><div class="val ${cls || ''}">${v}</div><div class="lab">${esc(l)}</div></div>`).join('')}</div>`;

/** badge status: kind = ok | warn | bad | lock | na */
const BADGE_KIND = {
  lengkap: 'ok', sebagian: 'warn', kosong: 'muted', terkunci: 'lock',
  na: 'na', gagal: 'bad', blokir: 'bad', peringatan: 'warn',
};
/* Terima nama status mentah ('lengkap') maupun kelas langsung ('ok').
   Halaman memanggil dengan status mentah; CSS hanya punya .ok/.warn/.bad/.lock. */
export const badge = (kind, label) =>
  `<span class="badge ${BADGE_KIND[kind] || kind}">${esc(label)}</span>`;

/** kartu putih */
export const card = (inner, cls = '') => `<div class="card ${cls}">${inner}</div>`;

/** kepala kartu: judul + catatan kanan */
export const cardHead = (title, note = '', right = '') => `
  <div class="card-head"><h2>${esc(title)}</h2>
    ${note ? `<span class="note">${note}</span>` : ''}
    ${right ? `<span style="margin-left:auto">${right}</span>` : ''}
  </div>`;

/** banner: kind = bad | warn | lock, dengan tombol opsional */
export const callout = (kind, title, body, action = '') => `
  <div class="callout ${kind}"><div class="c-body">
    <h3>${esc(title)}</h3>${body}
  </div>${action}</div>`;

/** daftar titik: [[judul, keterangan], ...] */
export const dlist = (items) => `<div class="dlist">${items.map(([h, p]) =>
  `<div class="item"><span class="dot"></span><div><h4>${esc(h)}</h4>
   <p>${p}</p></div></div>`).join('')}</div>`;

/** bar tipis: pct 0..100 */
export const mbar = (pct, cls = 'ok', h = 5) =>
  `<div class="mbar" style="height:${h}px"><i class="${cls}" style="width:${Math.max(0, Math.min(100, pct)).toFixed(1)}%"></i></div>`;

/** placeholder jujur — hanya dipakai kalau benar-benar belum bisa dibuat */
export const empty = (title, needs) => `
  <div class="card">
    <div class="card-head"><h2>${esc(title)}</h2><span class="note">belum dibangun</span></div>
    <p class="foot-note">Data yang dibutuhkan dari backend:</p>
    <ul class="foot-note" style="padding-left:18px;margin-top:6px">
      ${needs.map((n) => `<li>${esc(n)}</li>`).join('')}
    </ul>
  </div>`;

/** tabel standar: headers = [[label, align]], rows = string[][] (sudah di-escape sendiri) */
export const table = (headers, rows, opts = {}) => {
  const { foot = null, cls = '' } = opts;
  return `<table class="tbl ${cls}">
    <thead><tr>${headers.map(([l, a]) => `<th class="${a === 'r' ? 'r' : ''}">${esc(l)}</th>`).join('')}</tr></thead>
    <tbody>${rows.map((r) => `<tr${r.cls ? ` class="${r.cls}"` : ''}>${
      (r.cells || r).map((c, i) => `<td class="${headers[i] && headers[i][1] === 'r' ? 'r' : ''}">${c}</td>`).join('')
    }</tr>`).join('')}</tbody>
    ${foot ? `<tfoot><tr>${foot.map((c, i) => `<td class="${headers[i] && headers[i][1] === 'r' ? 'r' : ''}">${c}</td>`).join('')}</tr></tfoot>` : ''}
  </table>`;
};
