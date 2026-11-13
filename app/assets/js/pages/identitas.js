/* identitas — F-01. Tepat satu baris UPPS + satu prodi. Tidak ada "tambah".
 *
 * Kenapa tanpa daftar: instrumen menanyakan identitas institusi, bukan banyak
 * institusi. Daftar di sini akan mengundang orang menambah baris kedua, dan
 * semua tabel lain jadi tidak tahu harus merujuk yang mana.
 */
import { esc, head, card, cardHead, callout } from '../pagekit.js';

const API = '/api/identitas';
let data = null;
let galat = '';
let sedang = false;

export async function muat() {
  try {
    const r = await fetch(API, { credentials: 'same-origin' });
    if (!r.ok) { galat = (await r.json().catch(() => ({}))).error || `HTTP ${r.status}`; }
    else { data = await r.json(); galat = ''; }
  } catch (e) { galat = 'Server tidak merespons.'; }
  if (window.__paint) window.__paint();
}

const fld = (id, label, nilai, opts = {}) => `<div class="frm-fld">
  <label class="frm-lab" for="${id}">${esc(label)}${opts.wajib ? ' <span class="wajib">*</span>' : ''}</label>
  <input class="input" id="${id}" name="${id}" type="${opts.tipe || 'text'}"
    ${opts.maks ? `maxlength="${opts.maks}"` : ''} value="${esc(nilai ?? '')}" ${opts.dis || ''}></div>`;

function page() {
  if (!data) {
    if (galat) return head('Identitas UPPS & Prodi', 'F-01')
      + callout('bad', 'Data tidak bisa dimuat', `<p>${esc(galat)}</p>`)
      + '<button class="btn btn-primary" data-muat-ulang="1">Coba lagi</button>';
    return head('Identitas UPPS & Prodi', 'F-01') + '<p class="muted">Memuat…</p>';
  }

  const u = data.upps || {};
  const p = data.prodi || {};
  const boleh = data.boleh_ubah;
  const dis = boleh ? '' : 'disabled';

  return head('Identitas UPPS & Prodi', 'F-01 · satu baris, bukan daftar',
    boleh ? '<button class="btn btn-primary" id="btnSimpanId">Simpan</button>' : '')
    + (boleh ? '' : callout('warn', 'Hanya lihat', '<p>Peran Anda tidak boleh mengubah data.</p>'))
    + card(cardHead('Perguruan tinggi & UPPS')
      + `<div class="frm" id="frmIdentitas" data-upps="${u.id || ''}" data-prodi="${p.id || ''}">
        ${fld('id_nama_pt', 'Nama perguruan tinggi', u.nama_pt, { wajib: 1, maks: 160, dis })}
        ${fld('id_nama_upps', 'Nama UPPS', u.nama_upps, { wajib: 1, maks: 160, dis })}
        <div class="frm-row">
          ${fld('id_kota', 'Kota', u.kota, { maks: 80, dis })}
          ${fld('id_telepon', 'Telepon', u.telepon, { maks: 40, dis })}
        </div>
        <div class="frm-row">
          ${fld('id_email', 'Email', u.email, { tipe: 'email', maks: 120, dis })}
          ${fld('id_website', 'Website', u.website, { maks: 120, dis })}
        </div>
        ${fld('id_alamat', 'Alamat', u.alamat, { maks: 255, dis })}
        ${fld('id_narahubung', 'Narahubung', u.narahubung, { maks: 120, dis })}
        <div class="frm-err" id="idErr" role="alert"></div>
      </div>`)
    + card(cardHead('Program studi', 'yang sedang dipilih di sidebar')
      + `<div class="frm">
        ${fld('id_nama_ps', 'Nama program studi', p.nama_ps, { maks: 120, dis })}
        <div class="frm-row">
          <div class="frm-fld">
            <label class="frm-lab">Jenjang</label>
            <input class="input" value="${esc(p.jenjang || '—')}" disabled>
            <span class="frm-hint">Jenjang tidak diubah di sini — itu data resmi prodi.</span>
          </div>
          ${fld('id_lama_studi', 'Lama studi (semester)', p.lama_studi_semester, { tipe: 'number', dis })}
        </div>
        <label class="frm-centang">
          <input type="checkbox" id="id_profesi" ${p.punya_tahap_profesi ? 'checked' : ''} ${dis}>
          <span>Prodi ini punya tahap profesi (mahasiswa lanjut ke stase)</span>
        </label>
        <div class="frm-err" id="idErr2" role="alert"></div>
      </div>
      <p class="foot-note">Akreditasi prodi (peringkat & tanggal) diisi di halaman
        Periode Akreditasi, bukan di sini — supaya tidak ada dua tempat untuk angka yang sama.</p>`);
}

if (typeof document !== 'undefined' && !window.__idPasang) {
  window.__idPasang = true;

  document.addEventListener('click', async (e) => {
    if (!data) return;
    if (e.target.closest('[data-muat-ulang]')) {
      data = null; galat = ''; if (window.__paint) window.__paint(); await muat(); return;
    }
    if (!e.target.closest('#btnSimpanId')) return;

    const frm = document.getElementById('frmIdentitas');
    const err = document.getElementById('idErr');
    const btn = document.getElementById('btnSimpanId');
    const v = (id) => document.getElementById(id)?.value ?? '';

    const kirim = {
      upps_id: frm.dataset.upps || '0',
      prodi_id: frm.dataset.prodi || '0',
      nama_pt: v('id_nama_pt'), nama_upps: v('id_nama_upps'),
      kota: v('id_kota'), telepon: v('id_telepon'), email: v('id_email'),
      website: v('id_website'), alamat: v('id_alamat'), narahubung: v('id_narahubung'),
      nama_ps: v('id_nama_ps'), lama_studi_semester: v('id_lama_studi'),
      punya_tahap_profesi: document.getElementById('id_profesi')?.checked ? 1 : 0,
    };
    if (!kirim.nama_pt.trim()) { err.textContent = 'Nama perguruan tinggi wajib diisi.'; return; }
    if (!kirim.nama_upps.trim()) { err.textContent = 'Nama UPPS wajib diisi.'; return; }

    sedang = true; btn.disabled = true; err.textContent = '';
    try {
      const r = await fetch(API, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(kirim),
      });
      const j = await r.json();
      if (!r.ok || j.error) { err.textContent = j.error || `Gagal (HTTP ${r.status}).`; return; }
      await muat();
    } catch (_) { err.textContent = 'Server tidak merespons. Data belum tersimpan.'; }
    finally { sedang = false; btn.disabled = false; }
  });
}

export default page;
