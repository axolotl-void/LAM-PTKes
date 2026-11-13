/* Shell + registry halaman.
 *
 * Tiap halaman tinggal di `pages/<nama>.js` dan mengekspor:
 *   export default function page(state) { return '<html>'; }
 *   export const css = '...';        // opsional
 *
 * Di sini hanya: sidebar, topbar-ish helper, dan peta nama -> modul.
 * Tidak ada logika halaman di file ini — supaya beberapa orang/agent bisa
 * mengerjakan halaman berbeda tanpa bentrok.
 */
import { icon } from './icons.js';
import { D, PRODI, PERIODE, INSTRUMEN, KONSISTENSI_GAGAL, esc, badge } from './pagekit.js';
import { R, boleh } from './api.js';

export { esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table }
  from './pagekit.js';

import * as m_dashboard from './pages/dashboard.js';
import * as m_ringkasan from './pages/ringkasan.js';
import * as m_identitas from './pages/identitas.js';
import * as m_periode from './pages/periode.js';
import * as m_dosen from './pages/dosen.js';
import * as m_keuangan from './pages/keuangan.js';
import * as m_kerjasama from './pages/kerjasama.js';
import * as m_pengguna from './pages/pengguna.js';
import * as m_mahasiswa from './pages/mahasiswa.js';
import * as m_kurikulum from './pages/kurikulum.js';
import * as m_sarana from './pages/sarana.js';
import * as m_pustaka from './pages/pustaka.js';
import * as m_uji from './pages/uji.js';
import * as m_survei from './pages/survei.js';
import * as m_datadukung from './pages/datadukung.js';
import * as m_dokumen from './pages/dokumen.js';
import * as m_konsistensi from './pages/konsistensi.js';
import * as m_tabelisi from './pages/tabelisi.js';
import * as m_tabelmatriks from './pages/tabelmatriks.js';

/* Label status: sekarang di pagekit.js (satu definisi). views.js hanya
   meneruskan supaya pemanggil lama tetap jalan. */
export { ST_LABEL } from './pagekit.js';

/* ── peta halaman ─────────────────────────────────────────────────── */
const MODS = {
  dashboard: m_dashboard, ringkasan: m_ringkasan, identitas: m_identitas, periode: m_periode, dosen: m_dosen, keuangan: m_keuangan, kerjasama: m_kerjasama, pengguna: m_pengguna, mahasiswa: m_mahasiswa, kurikulum: m_kurikulum, sarana: m_sarana, pustaka: m_pustaka, uji: m_uji, survei: m_survei, datadukung: m_datadukung, dokumen: m_dokumen, konsistensi: m_konsistensi,
  // Halaman isian satu tabel. Tidak muncul di sidebar (tidak ada di NAV),
  // dibuka dari tombol "Isi" di daftar Data Dukung.
  tabelisi: m_tabelisi,
  tabelmatriks: m_tabelmatriks,
};
const PAGES = Object.fromEntries(Object.entries(MODS).map(([k, m]) => [k, m.default]));

/* CSS khusus halaman di-inject sekali saja, saat halaman pertama kali dibuka. */
const injected = new Set();
function ensureCss(page) {
  if (typeof document === 'undefined') return;   // dipakai di Node saat tes
  const mod = MODS[page];
  if (!mod || !mod.css || injected.has(page)) return;
  const el = document.createElement('style');
  el.dataset.page = page;
  el.textContent = mod.css;
  document.head.appendChild(el);
  injected.add(page);
}

/* ── NAV ──────────────────────────────────────────────────────────── */
const navItems = () => [
  ['MENU', null, [
    ['dashboard', 'Dashboard', null, 'dashboard'],
    ['ringkasan', 'Ringkasan 8 Kriteria', null, 'summary'],
  ]],
  ['BERSAMA UPPS', null, [
    ['identitas', 'Identitas UPPS & Prodi', 'F-01', 'identity'],
    ['periode', 'Periode Akreditasi', 'F-02', 'period'],
    ['dosen', 'Dosen & Tendik', 'F-06', 'lecturer'],
    ['keuangan', 'Keuangan', 'F-17', 'finance'],
    ['kerjasama', 'Kerja Sama', 'F-19', 'partner'],
    // hanya peran yang boleh mengelola akun yang melihat menunya
    ...(boleh('Kelola pengguna') ? [['pengguna', 'Pengguna & Hak Akses', 'F-22', 'access']] : []),
  ]],
  ['PRODI', 'prodi', [
    ['mahasiswa', 'Mahasiswa', 'F-05', 'student'],
    ['kurikulum', 'Kurikulum & MK', 'F-07', 'curriculum'],
    ['sarana', 'Sarana & Wahana', 'F-15', 'facility'],
    ['pustaka', 'Pustaka & Jurnal', 'F-16', 'library'],
    ['uji', 'Uji Kompetensi', 'F-09', 'competency'],
    ['survei', 'Survei & Tracer', 'F-10', 'survey'],
  ]],
  [null, null, [
    ['datadukung', 'Data Dukung', null, 'archive', `${R('lengkap', D.tabelLengkap)}/${INSTRUMEN.totalTabel}`, 'warn'],
    ['dokumen', 'Dokumen Pendukung', null, 'document', `${R('dokumen_terunggah', D.dokTerunggah)}/${INSTRUMEN.totalDokumen}`, 'warn'],
    ['konsistensi', 'Konsistensi', null, 'consistency', `${KONSISTENSI_GAGAL.length} gagal`, 'bad'],
  ]],
];

/* ── sidebar ──────────────────────────────────────────────────────── */
export function sidebar(page, state) {
  const prodi = state.prodi;
  const groups = navItems().map(([label, kind, items]) => {
    const lab = label === 'PRODI' ? `PRODI: ${prodi.toUpperCase()}` : label;
    const headHtml = label ? `<div class="nav-label ${kind === 'prodi' ? 'prodi' : ''}">${esc(lab)}</div>` : '';
    const rows = items.map(([id, name, code, ico, bLab, bKind]) => {
      const cur = page === id ? ' aria-current="page"' : '';
      const right = bLab ? badge(bKind, bLab) : (code ? `<span class="code">${esc(code)}</span>` : '');
      return `<button class="nav-item" data-nav="${id}"${cur}>${icon(ico)}<span class="lbl">${esc(name)}</span>${right}</button>`;
    }).join('');
    return headHtml + rows;
  }).join('<div class="nav-sep"></div>');

  return `
    <div class="switch">
      ${PRODI.map((p) => `<button data-prodi="${p.nama}" aria-pressed="${p.nama === prodi}">${p.nama}</button>`).join('')}
    </div>
    <button class="period"><b>TS ${PERIODE.ts}</b><span>· ${PRODI.find((p) => p.nama === prodi).jenjang}</span></button>
    ${groups}
    <div class="sb-foot">
      <div class="nav-label">EKSPOR PRODI INI</div>
      <div class="export-row">
        ${D.penghalang.length
          ? `<button class="btn btn-ghost" disabled title="Terkunci: ${D.penghalang.length} penghalang">PDF</button>
             <button class="btn btn-ghost" disabled title="Terkunci: ${D.penghalang.length} penghalang">XLSX</button>`
          : `<button class="btn btn-warn" data-export="pdf">PDF</button>
             <button class="btn btn-ghost" data-export="xlsx">XLSX</button>`}
      </div>
      ${D.blokir ? `<div class="foot-note" style="margin-top:6px;color:var(--warn)">terkunci · ${D.blokir} blokir${D.penghalang.length - D.blokir ? ` + ${D.penghalang.length - D.blokir} peringatan` : ''}</div>` : ''}
    </div>`;
}

/* ── render ───────────────────────────────────────────────────────── */
export function render(page, state) {
  const fn = PAGES[page] || dashboard;
  ensureCss(PAGES[page] ? page : 'dashboard');
  return fn(state);
}

export const PAGE_IDS = Object.keys(PAGES);

/* Angka live dari SQLite, null kalau server tidak jalan. Halaman membacanya
   secara opsional: kalau null, pakai konstanta data.js. */
export { LIVE } from './api.js';
