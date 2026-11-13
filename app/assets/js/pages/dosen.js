/* dosen — Dosen & Tendik (F-06). Baca & tulis lewat /api/dosen.
 *
 * ATURAN YANG MENENTUKAN BENTUK HALAMAN INI: satu orang = satu baris, walau
 * mengajar di dua prodi. Prodi disimpan many-to-many di dosen_prodi. Prototipe
 * lama menduplikasi baris per prodi sehingga jumlah dosen terhitung dobel dan
 * rasio dosen:mahasiswa (Tabel 15) salah — rasio itu dibaca asesor.
 *
 * Status STR dihitung dari tanggal, bukan diketik: lewat tanggal = kedaluwarsa.
 * Dosen dengan STR kedaluwarsa masih boleh mengajar, tapi tidak bisa jadi
 * pembimbing klinik. Asesor menanyakan ini.
 */
import { esc, head, strip, badge, card, cardHead, callout, empty } from '../pagekit.js';

const API = '/api/dosen';

let data = null;
let galat = '';
let sedangSimpan = false;
let saring = { jenis: '', prodi: '', cari: '' };

export const css = '';

export async function muat() {
  try {
    const qs = new URLSearchParams(Object.entries(saring).filter(([, v]) => v));
    const r = await fetch(API + (qs.toString() ? '?' + qs : ''), { credentials: 'same-origin' });
    if (!r.ok) {
      galat = (await r.json().catch(() => ({}))).error || `HTTP ${r.status}`;
      return;
    }
    data = await r.json();
    galat = '';
  } catch (e) {
    galat = 'Server tidak merespons.';
  }
  if (typeof window !== 'undefined' && window.__paint) window.__paint();
}

/* ── panel isian ───────────────────────────────────────────────── */
function opsi(daftar, dipilih, kosong = '—') {
  return `<option value="">${esc(kosong)}</option>`
    + (daftar || []).map((v) => `<option value="${esc(v)}"${v === dipilih ? ' selected' : ''}>${esc(v)}</option>`).join('');
}

function barisPendidikan(p = {}) {
  return `<div class="frm-row pdd" style="margin-bottom:8px">
    <div class="frm-fld"><label class="frm-lab">Jenjang</label>
      <select name="p_jenjang">${opsi(data?.pilihan?.jenjang, p.jenjang, '—')}</select></div>
    <div class="frm-fld"><label class="frm-lab">Bidang</label>
      <input class="input" name="p_bidang" maxlength="120" value="${esc(p.bidang || '')}"></div>
    <div class="frm-fld"><label class="frm-lab">Asal PT</label>
      <input class="input" name="p_asal" maxlength="160" value="${esc(p.asal_pt || '')}"></div>
    <div class="frm-fld"><label class="frm-lab">Tahun lulus</label>
      <input class="input" name="p_tahun" type="number" min="1950" max="2100" value="${p.tahun_lulus || ''}"></div>
    <button class="btn btn-ghost btn-sm hapus-pdd" type="button">Hapus baris</button>
  </div>`;
}

function formPanel(d = null) {
  const x = d || {};
  const p = data?.pilihan || {};
  const boleh = data?.boleh_ubah;
  const dis = boleh ? '' : 'disabled';
  const prodiDipilih = new Set((x.prodi || []).map((v) => Number(v.id)));
  const pdd = (x.pendidikan || []).length ? x.pendidikan : [{}];

  return `<div class="panel baca" id="panelDosen" role="dialog" aria-modal="true" aria-labelledby="panelJudulDosen">
    <div class="panel-tirai" data-tutup="1"></div>
    <div class="panel-isi">
      <div class="panel-kepala">
        <h2 id="panelJudulDosen">${d ? 'Ubah data dosen' : 'Tambah dosen / tendik'}</h2>
        <span class="note">F-06</span>
        <button class="panel-x" type="button" data-tutup="1" aria-label="Tutup">×</button>
      </div>
      <div class="panel-badan">
        ${boleh ? '' : callout('warn', 'Hanya lihat', '<p>Peran Anda tidak boleh mengubah data.</p>')}
        <div class="frm" id="frmDosen">
          <input type="hidden" name="id" value="${d ? d.id : ''}">
          <div class="frm-fld">
            <label class="frm-lab" for="dNama">Nama <span class="wajib">*</span></label>
            <input class="input" id="dNama" name="nama" maxlength="160" value="${esc(x.nama || '')}"
                   placeholder="Siti Rahmawati" ${dis}>
          </div>
          <div class="frm-row">
            <div class="frm-fld"><label class="frm-lab" for="dGelar">Gelar akademik</label>
              <input class="input" id="dGelar" name="gelar_akademik" maxlength="120" value="${esc(x.gelar_akademik || '')}"
                     placeholder="S.ST., M.Keb" ${dis}></div>
            <div class="frm-fld"><label class="frm-lab" for="dNidn">NIDN / NIDK / NUP</label>
              <input class="input" id="dNidn" name="nidn_nidk_nup" maxlength="32" value="${esc(x.nidn_nidk_nup || '')}"
                     placeholder="0012345678" ${dis}>
              <span class="frm-hint">Harus unik. Kosongkan kalau belum ada.</span></div>
          </div>
          <div class="frm-row">
            <div class="frm-fld"><label class="frm-lab" for="dJenis">Jenis <span class="wajib">*</span></label>
              <select id="dJenis" name="jenis" ${dis}>${opsi(p.jenis, x.jenis || 'Dosen Tetap', '—')}</select></div>
            <div class="frm-fld"><label class="frm-lab" for="dJabatan">Jabatan fungsional</label>
              <select id="dJabatan" name="jabatan_fungsional" ${dis}>${opsi(p.jabatan, x.jabatan_fungsional, '—')}</select></div>
          </div>
          <div class="frm-fld">
            <span class="frm-lab">Prodi tempat mengajar</span>
            <div style="display:grid;gap:6px">
              ${(data?.prodi || []).map((pr) => `<label style="display:flex;gap:8px;align-items:center;font-size:var(--fs-sm)">
                <input type="checkbox" name="prodi" value="${pr.id}" ${prodiDipilih.has(Number(pr.id)) ? 'checked' : ''} ${dis}>
                <span>${esc(pr.nama_ps)}</span></label>`).join('')}
            </div>
            <span class="frm-hint">Boleh dua-duanya. Satu orang tetap satu baris di daftar.</span>
          </div>
          <div class="frm-row">
            <div class="frm-fld"><label class="frm-lab" for="dProfesi">Profesi</label>
              <select id="dProfesi" name="profesi" ${dis}>${opsi(p.profesi, x.profesi, '—')}</select></div>
            <div class="frm-fld"><label class="frm-lab" for="dTahap">Tahap mengajar</label>
              <select id="dTahap" name="tahap_mengajar" ${dis}>${opsi(p.tahap, x.tahap_mengajar, '—')}</select></div>
          </div>
          <div class="frm-row">
            <div class="frm-fld"><label class="frm-lab" for="dStr">No. STR</label>
              <input class="input" id="dStr" name="no_str" maxlength="60" value="${esc(x.no_str || '')}" ${dis}></div>
            <div class="frm-fld"><label class="frm-lab" for="dStrTgl">Berlaku sampai STR</label>
              <input class="input" id="dStrTgl" type="date" name="tanggal_str" value="${x.tanggal_str || ''}" ${dis}></div>
          </div>
          <div class="frm-row">
            <div class="frm-fld"><label class="frm-lab" for="dLahir">Tanggal lahir</label>
              <input class="input" id="dLahir" type="date" name="tanggal_lahir" value="${x.tanggal_lahir || ''}" ${dis}></div>
            <div class="frm-fld"><label class="frm-lab" for="dEmail">Email</label>
              <input class="input" id="dEmail" type="email" name="email" maxlength="160" value="${esc(x.email || '')}" ${dis}></div>
          </div>
          <label style="display:flex;gap:8px;align-items:center;font-size:var(--fs-sm)">
            <input type="checkbox" name="sertifikat_pendidik" ${x.sertifikat_pendidik ? 'checked' : ''} ${dis}>
            <span>Sudah punya sertifikat pendidik (ditandai * di Tabel 16)</span>
          </label>

          <div>
            <div class="frm-lab" style="margin-bottom:6px">Riwayat pendidikan</div>
            <div id="pddList">${pdd.map(barisPendidikan).join('')}</div>
            ${boleh ? '<button class="btn btn-ghost btn-sm" type="button" id="tambahPdd">+ Tambah baris</button>' : ''}
          </div>
          <div class="frm-err" id="frmErrDosen" role="alert"></div>
        </div>
      </div>
      <div class="panel-kaki">
        <button class="btn btn-ghost" type="button" data-tutup="1">Batal</button>
        ${boleh ? '<button class="btn btn-primary" type="button" id="btnSimpanDosen">Simpan</button>' : ''}
      </div>
    </div>
  </div>`;
}

function bukaPanel(d = null) {
  document.getElementById('panelDosen')?.remove();
  document.body.insertAdjacentHTML('beforeend', formPanel(d));
  document.getElementById('panelDosen')?.classList.add('buka');
}

function tutupPanel() {
  document.getElementById('panelDosen')?.remove();
}

async function simpan() {
  const frm = document.getElementById('frmDosen');
  if (!frm || sedangSimpan) return;
  const err = document.getElementById('frmErrDosen');
  const btn = document.getElementById('btnSimpanDosen');

  const kirim = {
    id: frm.querySelector('[name=id]').value || '0',
    nama: frm.querySelector('[name=nama]').value,
    gelar_akademik: frm.querySelector('[name=gelar_akademik]').value,
    nidn_nidk_nup: frm.querySelector('[name=nidn_nidk_nup]').value,
    jenis: frm.querySelector('[name=jenis]').value,
    jabatan_fungsional: frm.querySelector('[name=jabatan_fungsional]').value,
    profesi: frm.querySelector('[name=profesi]').value,
    tahap_mengajar: frm.querySelector('[name=tahap_mengajar]').value,
    no_str: frm.querySelector('[name=no_str]').value,
    tanggal_str: frm.querySelector('[name=tanggal_str]').value,
    tanggal_lahir: frm.querySelector('[name=tanggal_lahir]').value,
    email: frm.querySelector('[name=email]').value,
    sertifikat_pendidik: frm.querySelector('[name=sertifikat_pendidik]').checked ? 1 : 0,
    prodi: [...frm.querySelectorAll('[name=prodi]:checked')].map((c) => c.value),
    pendidikan: [...frm.querySelectorAll('.pdd')].map((r) => ({
      jenjang: r.querySelector('[name=p_jenjang]').value,
      bidang: r.querySelector('[name=p_bidang]').value,
      asal_pt: r.querySelector('[name=p_asal]').value,
      tahun_lulus: r.querySelector('[name=p_tahun]').value,
    })).filter((p) => p.jenjang),
  };

  if (!kirim.nama.trim()) {
    err.textContent = 'Nama wajib diisi.';
    return;
  }

  sedangSimpan = true;
  btn.disabled = true;
  err.textContent = '';
  try {
    const r = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(kirim),
    });
    const j = await r.json();
    if (!r.ok || j.error) {
      err.textContent = j.error || `Gagal menyimpan (HTTP ${r.status}).`;
      return;
    }
    tutupPanel();
    await muat();
  } catch (e) {
    err.textContent = 'Server tidak merespons. Data belum tersimpan.';
  } finally {
    sedangSimpan = false;
    btn.disabled = false;
  }
}

/* ── halaman ───────────────────────────────────────────────────── */
function page() {
  if (!data) {
    if (galat) {
      return head('Dosen & Tendik', 'F-06')
        + callout('bad', 'Data tidak bisa dimuat', `<p>${esc(galat)}</p>`)
        + '<button class="btn btn-primary" data-muat-ulang="1">Coba lagi</button>';
    }
    return head('Dosen & Tendik', 'F-06') + '<p class="muted">Memuat…</p>';
  }

  const { dosen, ringkas, boleh_ubah, pilihan } = data;
  const aksi = boleh_ubah ? '<button class="btn btn-primary" data-tambah-dosen="1">+ Tambah dosen</button>' : '';

  const saringHtml = `<div class="frm-row" style="margin-bottom:10px">
    <div class="frm-fld"><label class="frm-lab">Cari</label>
      <input class="input" id="dCari" value="${esc(saring.cari)}" placeholder="nama atau NIDN"></div>
    <div class="frm-fld"><label class="frm-lab">Jenis</label>
      <select id="dSaringJenis">${['', ...(pilihan.jenis || [])].map((v) => `<option value="${esc(v)}"${saring.jenis === v ? ' selected' : ''}>${esc(v || 'Semua')}</option>`).join('')}</select></div>
    <div class="frm-fld"><label class="frm-lab">Prodi</label>
      <select id="dSaringProdi"><option value="">Semua</option>${(data.prodi || []).map((p) => `<option value="${p.id}"${String(saring.prodi) === String(p.id) ? ' selected' : ''}>${esc(p.nama_ps)}</option>`).join('')}</select></div>
  </div>`;

  if (dosen.length === 0) {
    const adaSaring = saring.cari || saring.jenis || saring.prodi;
    return head('Dosen & Tendik', `F-06 · ${ringkas.total} orang`, aksi)
      + card(saringHtml)
      + (adaSaring
        ? callout('info', 'Tidak ada yang cocok', '<p>Coba kosongkan saringan atau ubah kata kunci.</p>')
        : callout('info', 'Belum ada data dosen',
          '<p>Master ini dipakai Tabel 15–19 dan menentukan rasio dosen:mahasiswa. '
          + 'Mulai dari dosen tetap prodi, lalu tambahkan tendik.</p>'
          + '<p>Satu orang cukup diisi sekali walau mengajar di dua prodi — '
          + 'centang kedua prodi di panel isian.</p>'))
      + empty('Daftar dosen', ['Nama dan jenis (Dosen Tetap / Tidak Tetap / Tendik)',
        'NIDN/NIDK/NUP (unik)', 'Prodi tempat mengajar', 'Riwayat pendidikan (opsional)']);
  }

  const bermasalah = dosen.filter((d) => d.status_str === 'kedaluwarsa');
  const banner = bermasalah.length
    ? callout('bad', 'STR kedaluwarsa',
      `<p>${bermasalah.length} dosen punya STR lewat tanggal: `
      + bermasalah.slice(0, 3).map((d) => `<b>${esc(d.nama)}</b>`).join(', ')
      + (bermasalah.length > 3 ? `, +${bermasalah.length - 3} lain` : '')
      + '. Mereka masih boleh mengajar, tapi tidak bisa jadi pembimbing klinik '
      + 'sampai STR diperpanjang — asesor menanyakan ini di Tabel 16.</p>')
    : '';

  const baris = dosen.map((d) => {
    const pr = d.prodi.map((p) => p.nama).join(' + ') || '—';
    const cls = d.status_str === 'kedaluwarsa' ? 'is-bad' : (!d.nidn_nidk_nup ? 'is-warn' : '');
    const tombol = boleh_ubah
      ? `<button class="lnk" data-ubah-dosen="${d.id}">Ubah</button>
         <button class="lnk" data-hapus-dosen="${d.id}" data-nama="${esc(d.nama)}">Hapus</button>`
      : '';
    return `<tr class="${cls}">
      <td><span class="strong">${esc(d.nama)}</span>
        ${d.gelar_akademik ? `<span class="muted">, ${esc(d.gelar_akademik)}</span>` : ''}
        ${d.nidn_nidk_nup ? `<div class="muted" style="font-size:10px">${esc(d.nidn_nidk_nup)}</div>`
          : '<div class="muted" style="font-size:10px">NIDN belum ada</div>'}</td>
      <td class="muted">${esc(d.jenis)}</td>
      <td class="muted">${esc(d.jabatan_fungsional || '—')}</td>
      <td class="muted">${esc(pr)}</td>
      <td>${d.sertifikat_pendidik ? badge('ok', 'ada') : badge('muted', 'belum')}</td>
      <td>${badge(d.status_str === 'berlaku' ? 'ok' : d.status_str === 'kedaluwarsa' ? 'bad' : 'warn',
        d.status_str === 'berlaku' ? 'berlaku' : d.status_str)}</td>
      <td class="r">${tombol}</td>
    </tr>`;
  }).join('');

  return head('Dosen & Tendik',
    `F-06 · ${ringkas.total} orang · ${ringkas.tetap} dosen tetap · ${ringkas.tendik} tendik`, aksi)
    + banner
    + strip([
      [`${ringkas.tetap}`, 'dosen tetap', ''],
      [`${ringkas.tidak_tetap}`, 'tidak tetap', 'muted'],
      [`${ringkas.tendik}`, 'tendik', 'muted'],
      [`${ringkas.sertifikat}`, 'punya sertifikat pendidik', 'ok'],
      [`${ringkas.str_kedaluwarsa}`, 'STR kedaluwarsa', ringkas.str_kedaluwarsa ? 'bad' : 'muted'],
    ])
    + card(cardHead('Daftar dosen & tendik',
      `${dosen.length} baris · satu orang satu baris walau lintas prodi`)
      + saringHtml
      + `<table class="tbl">
        <thead><tr>
          <th>Nama</th><th>Jenis</th><th>Jabatan</th><th>Prodi</th>
          <th>Sertifikat</th><th>STR</th><th></th>
        </tr></thead>
        <tbody>${baris}</tbody>
      </table>
      <p class="foot-note">Baris merah = STR kedaluwarsa. Baris kuning = NIDN belum diisi.
        Hapus hanya berhasil kalau orang ini belum dipakai di catatan lain.</p>`);
}

/* ── peristiwa ─────────────────────────────────────────────────── */
if (typeof document !== 'undefined' && !window.__dosenTerpasang) {
  window.__dosenTerpasang = true;

  document.addEventListener('click', async (e) => {
    if (e.target.closest('[data-tambah-dosen]')) { bukaPanel(null); return; }

    const ubah = e.target.closest('[data-ubah-dosen]');
    if (ubah) {
      const id = Number(ubah.dataset.ubahDosen);
      const d = (data?.dosen || []).find((x) => Number(x.id) === id);
      if (d) bukaPanel(d);
      return;
    }

    const hapus = e.target.closest('[data-hapus-dosen]');
    if (hapus) {
      const id = Number(hapus.dataset.hapusDosen);
      if (!confirm(`Hapus "${hapus.dataset.nama}"? Hanya bisa kalau belum dipakai catatan lain.`)) return;
      hapus.disabled = true;
      try {
        const r = await fetch(API, {
          method: 'DELETE', headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin', body: JSON.stringify({ id }),
        });
        const j = await r.json();
        if (!r.ok || j.error) { alert(j.error || 'Gagal.'); hapus.disabled = false; return; }
        await muat();
      } catch (_) { alert('Server tidak merespons.'); hapus.disabled = false; }
      return;
    }

    if (e.target.closest('#tambahPdd')) {
      document.getElementById('pddList')?.insertAdjacentHTML('beforeend', barisPendidikan({}));
      return;
    }
    if (e.target.closest('.hapus-pdd')) {
      const baris = e.target.closest('.pdd');
      if (document.querySelectorAll('.pdd').length > 1) baris.remove();
      return;
    }
    if (e.target.closest('[data-tutup]')) { tutupPanel(); return; }
    if (e.target.closest('#btnSimpanDosen')) { simpan(); return; }

    if (e.target.closest('[data-muat-ulang]')) {
      data = null; galat = '';
      if (window.__paint) window.__paint();
      await muat();
    }
  });

  // Saringan: kirim ulang saat berubah.
  document.addEventListener('change', async (e) => {
    if (e.target.id === 'dSaringJenis') { saring.jenis = e.target.value; await muat(); }
    if (e.target.id === 'dSaringProdi') { saring.prodi = e.target.value; await muat(); }
  });
  document.addEventListener('input', (e) => {
    if (e.target.id !== 'dCari') return;
    clearTimeout(window.__cariTimer);
    window.__cariTimer = setTimeout(async () => { saring.cari = e.target.value; await muat(); }, 350);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') tutupPanel();
  });
}

export default page;
