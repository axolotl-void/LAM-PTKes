/* kerjasama — Tabel 38 (F-19). Baca & tulis lewat /api/kerjasama.
 *
 * Sebelumnya halaman ini memakai array MITRA karangan di dalam file. Sekarang
 * angkanya dari database. Form isian memakai panel mengambang (`.panel` di
 * app.css) supaya tabel tetap terlihat saat mengisi.
 *
 * Aturan yang dipegang:
 *  - status MoU DIHITUNG dari tgl_berakhir, bukan diketik. Tanggal lewat =
 *    kedaluwarsa, apa pun isi kolom status.
 *  - "kedaluwarsa" beda dengan "belum ada": yang pertama punya riwayat dan
 *    bisa diperpanjang, yang kedua tidak punya dokumen sama sekali. Asesor
 *    membedakan keduanya.
 *  - tombol Hapus menonaktifkan, tidak menghapus baris.
 */
import { esc, head, strip, badge, card, cardHead, callout, empty } from '../pagekit.js';

const API = '/api/kerjasama';

let data = null;      // { mitra, ringkas, pilihan, wahana, boleh_ubah }
let galat = '';
let sedangSimpan = false;

export const css = '';

/* ── ambil data ──────────────────────────────────────────────────── */
export async function muat() {
  try {
    const r = await fetch(API, { credentials: 'same-origin' });
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

/* ── panel isian ─────────────────────────────────────────────────── */
function opsi(daftar, dipilih) {
  return daftar.map((v) => `<option value="${esc(v)}"${v === dipilih ? ' selected' : ''}>${esc(v)}</option>`).join('');
}

function formPanel(m = null) {
  const d = m || {};
  const p = data?.pilihan || { jenis: [], lingkup: [] };
  const boleh = data?.boleh_ubah;
  const judul = m ? `Ubah mitra` : 'Tambah mitra kerja sama';
  const wahana = data?.wahana || [];
  const dipilihWahana = new Set((m?.wahana || []).map(Number));

  return `<div class="panel baca" id="panelMitra" role="dialog" aria-modal="true" aria-labelledby="panelJudul">
    <div class="panel-tirai" data-tutup="1"></div>
    <div class="panel-isi">
      <div class="panel-kepala">
        <h2 id="panelJudul">${esc(judul)}</h2>
        <span class="note">Tabel 38</span>
        <button class="panel-x" type="button" data-tutup="1" aria-label="Tutup">×</button>
      </div>
      <div class="panel-badan">
        ${boleh ? '' : callout('warn', 'Tidak boleh mengubah data',
          '<p>Peran Anda hanya bisa melihat. Hubungi Operator Prodi atau Admin UPPS.</p>')}
        <div class="frm" id="frmMitra">
          <input type="hidden" name="id" value="${m ? m.id : ''}">
          <div class="frm-fld">
            <label class="frm-lab" for="fNama">Nama mitra <span class="wajib">*</span></label>
            <input class="input" id="fNama" name="nama" maxlength="160" value="${esc(d.nama || '')}"
                   placeholder="RSUD Zainoel Abidin" ${boleh ? '' : 'disabled'}>
          </div>
          <div class="frm-row">
            <div class="frm-fld">
              <label class="frm-lab" for="fJenis">Jenis <span class="wajib">*</span></label>
              <select id="fJenis" name="jenis" ${boleh ? '' : 'disabled'}>${opsi(p.jenis, d.jenis)}</select>
            </div>
            <div class="frm-fld">
              <label class="frm-lab" for="fLingkup">Lingkup</label>
              <select id="fLingkup" name="lingkup" ${boleh ? '' : 'disabled'}>${opsi(p.lingkup, d.lingkup || 'Pendidikan')}</select>
            </div>
          </div>
          <div class="frm-row">
            <div class="frm-fld">
              <label class="frm-lab" for="fKota">Kota</label>
              <input class="input" id="fKota" name="kota" maxlength="80" value="${esc(d.kota || '')}" ${boleh ? '' : 'disabled'}>
            </div>
            <div class="frm-fld">
              <label class="frm-lab" for="fNo">Nomor dokumen (MoU/PKS)</label>
              <input class="input" id="fNo" name="no_dokumen" maxlength="120" value="${esc(d.no_dokumen || '')}"
                     placeholder="001/MoU/RSZA/2021" ${boleh ? '' : 'disabled'}>
            </div>
          </div>
          <div class="frm-row">
            <div class="frm-fld">
              <label class="frm-lab" for="fMulai">Tanggal mulai</label>
              <input class="input" id="fMulai" type="date" name="tgl_mulai" value="${d.tgl_mulai || ''}" ${boleh ? '' : 'disabled'}>
            </div>
            <div class="frm-fld">
              <label class="frm-lab" for="fAkhir">Tanggal berakhir</label>
              <input class="input" id="fAkhir" type="date" name="tgl_berakhir" value="${d.tgl_berakhir || ''}" ${boleh ? '' : 'disabled'}>
            </div>
          </div>
          <p class="frm-hint">Status MoU dihitung dari tanggal berakhir — tidak diketik manual.
            Kosongkan tanggal kalau dokumennya memang belum ada.</p>
          <div class="frm-fld">
            <label class="frm-lab" for="fAlamat">Alamat</label>
            <input class="input" id="fAlamat" name="alamat" maxlength="255" value="${esc(d.alamat || '')}" ${boleh ? '' : 'disabled'}>
          </div>
          ${wahana.length ? `<div class="frm-fld">
            <span class="frm-lab">Wahana praktik yang dilayani (Tabel 30)</span>
            <div style="display:grid;gap:6px">
              ${wahana.map((w) => `<label style="display:flex;gap:8px;align-items:center;font-size:var(--fs-sm)">
                <input type="checkbox" name="wahana" value="${w.id}" ${dipilihWahana.has(Number(w.id)) ? 'checked' : ''} ${boleh ? '' : 'disabled'}>
                <span>${esc(w.nama)}</span></label>`).join('')}
            </div>
            <span class="frm-hint">Kosong berarti mitra ini belum dipakai untuk praktik.</span>
          </div>` : ''}
          <div class="frm-fld">
            <label class="frm-lab" for="fCatatan">Catatan</label>
            <textarea id="fCatatan" name="catatan" maxlength="255" ${boleh ? '' : 'disabled'}>${esc(d.catatan || '')}</textarea>
          </div>
          <label style="display:flex;gap:8px;align-items:center;font-size:var(--fs-sm)">
            <input type="checkbox" name="ada_laporan" ${d.ada_laporan ? 'checked' : ''} ${boleh ? '' : 'disabled'}>
            <span>Laporan pelaksanaan kerja sama sudah ada</span>
          </label>
          <div class="frm-err" id="frmErr" role="alert"></div>
        </div>
      </div>
      <div class="panel-kaki">
        <button class="btn btn-ghost" type="button" data-tutup="1">Batal</button>
        ${boleh ? `<button class="btn btn-primary" type="button" id="btnSimpanMitra">Simpan</button>` : ''}
      </div>
    </div>
  </div>`;
}

/* ── aksi panel ──────────────────────────────────────────────────── */
function bukaPanel(m = null) {
  const lama = document.getElementById('panelMitra');
  if (lama) lama.remove();
  document.body.insertAdjacentHTML('beforeend', formPanel(m));
  document.getElementById('panelMitra')?.classList.add('buka');
}

function tutupPanel() {
  document.getElementById('panelMitra')?.remove();
}

async function simpan() {
  const frm = document.getElementById('frmMitra');
  if (!frm || sedangSimpan) return;
  const err = document.getElementById('frmErr');
  const btn = document.getElementById('btnSimpanMitra');

  const f = new FormData();
  f.append('id', frm.querySelector('[name=id]').value || '0');
  for (const n of ['nama', 'jenis', 'lingkup', 'kota', 'no_dokumen', 'tgl_mulai', 'tgl_berakhir', 'alamat', 'catatan']) {
    f.append(n, frm.querySelector(`[name=${n}]`)?.value ?? '');
  }
  f.append('ada_laporan', frm.querySelector('[name=ada_laporan]')?.checked ? '1' : '');
  frm.querySelectorAll('[name=wahana]:checked').forEach((c) => f.append('wahana[]', c.value));

  // Validasi cepat di klien — server tetap memeriksa ulang.
  if (!f.get('nama').trim()) {
    err.textContent = 'Nama mitra wajib diisi.';
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
      body: JSON.stringify(Object.fromEntries(f)),
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

/* ── halaman ─────────────────────────────────────────────────────── */
function page() {
  if (!data) {
    if (galat) {
      return head('Kerja Sama', 'F-19 · Tabel 38')
        + callout('bad', 'Data tidak bisa dimuat', `<p>${esc(galat)}</p>`)
        + '<button class="btn btn-primary" data-muat-ulang="1">Coba lagi</button>';
    }
    return head('Kerja Sama', 'F-19 · Tabel 38') + '<p class="muted">Memuat…</p>';
  }

  const { mitra, ringkas, boleh_ubah } = data;
  const aksi = boleh_ubah
    ? '<button class="btn btn-primary" data-tambah-mitra="1">+ Tambah mitra</button>'
    : '';

  if (mitra.length === 0) {
    return head('Kerja Sama', 'F-19 · Tabel 38 · belum ada mitra', aksi)
      + callout('info', 'Belum ada mitra kerja sama',
        '<p>Tabel 38 meminta daftar kerja sama UPPS yang relevan dengan program studi: '
        + 'rumah sakit, puskesmas, BPM/PMB, dan institusi lain tempat mahasiswa praktik.</p>'
        + '<p>Tambahkan satu per satu. Tanggal berakhir wajib kalau dokumennya sudah ada — '
        + 'status MoU dihitung dari tanggal itu, bukan diketik.</p>')
      + empty('Daftar mitra', ['Nama mitra dan jenisnya', 'Nomor dokumen, tanggal mulai, tanggal berakhir',
        'Wahana praktik yang dilayani (opsional)']);
  }

  const perluPerhatian = mitra.filter((m) => m.status === 'kedaluwarsa' || m.status === 'belum ada' || !m.ada_laporan);
  const banner = perluPerhatian.slice(0, 1).map((m) => {
    if (m.status === 'kedaluwarsa') {
      return callout('bad', 'MoU kedaluwarsa',
        `<p><b>${esc(m.nama)}</b> — berakhir ${esc(m.tgl_berakhir_teks || '')}, lewat ${Math.abs(m.sisa_hari)} hari. `
        + 'Wahana ini tidak dihitung ke kapasitas Tabel 30 sampai dokumen diperbarui. '
        + 'Asesor membedakan "punya tapi kedaluwarsa" dari "belum ada" — yang pertama bisa diperpanjang.</p>');
    }
    if (m.status === 'belum ada') {
      return callout('warn', 'Dokumen belum ada',
        `<p><b>${esc(m.nama)}</b> tercatat sebagai mitra, tapi belum ada nomor maupun tanggal dokumen. `
        + 'Lengkapi di panel Ubah, atau hapus kalau memang belum bekerja sama.</p>');
    }
    return callout('warn', 'Laporan belum ada',
      `<p><b>${esc(m.nama)}</b> — dokumennya berlaku, tapi laporan pelaksanaannya belum ditandai ada. `
      + 'Tabel 38 menanyakan kegiatan yang sudah berjalan, bukan hanya MoU-nya.</p>');
  }).join('');

  const baris = mitra.map((m) => {
    const keg = (m.kegiatan || []).reduce((s, k) => s + Number(k.pendidikan) + Number(k.penelitian) + Number(k.pkm), 0);
    const cls = m.status === 'kedaluwarsa' ? 'is-bad' : (!m.ada_laporan || m.status === 'belum ada' ? 'is-warn' : '');
    const tombol = boleh_ubah
      ? `<button class="lnk" data-ubah-mitra="${m.id}">Ubah</button>
         <button class="lnk" data-hapus-mitra="${m.id}" data-nama="${esc(m.nama)}">Hapus</button>`
      : '';
    return `<tr class="${cls}">
      <td class="strong">${esc(m.nama)}${m.no_dokumen ? `<div class="muted" style="font-size:10px">${esc(m.no_dokumen)}</div>` : ''}</td>
      <td class="muted">${esc(m.jenis)}</td>
      <td class="muted">${esc(m.kota || '—')}</td>
      <td class="muted">${esc(m.tgl_mulai_teks || '—')}</td>
      <td class="${m.status === 'kedaluwarsa' ? '' : 'muted'}">${esc(m.tgl_berakhir_teks || '—')}</td>
      <td>${badge(m.status === 'berlaku' ? 'ok' : m.status === 'kedaluwarsa' ? 'bad' : 'warn', m.status)}</td>
      <td class="r num">${keg || '—'}</td>
      <td class="r">${tombol}</td>
    </tr>`;
  }).join('');

  return head('Kerja Sama',
    `F-19 · Tabel 38 · ${ringkas.total} mitra · ${ringkas.berlaku} MoU berlaku · ${ringkas.kedaluwarsa} kedaluwarsa`,
    aksi)
    + banner
    + strip([
      [`${ringkas.total}`, 'mitra kerja sama', ''],
      [`${ringkas.berlaku}`, 'MoU berlaku', 'ok'],
      [`${ringkas.kedaluwarsa}`, 'kedaluwarsa', ringkas.kedaluwarsa ? 'bad' : 'muted'],
      [`${ringkas.segera}`, 'perlu diperpanjang ≤6 bln', ringkas.segera ? 'warn' : 'muted'],
      [`${ringkas.tanpa_laporan}`, 'laporan belum ada', ringkas.tanpa_laporan ? 'warn' : 'muted'],
    ])
    + card(cardHead('Daftar mitra',
      'diurut tanggal berakhir terdekat · Tabel 38')
      + `<table class="tbl">
        <thead><tr>
          <th>Mitra</th><th>Jenis</th><th>Kota</th><th>Mulai</th><th>Berakhir</th>
          <th>Status</th><th class="r">Kegiatan</th><th></th>
        </tr></thead>
        <tbody>${baris}</tbody>
      </table>
      <p class="foot-note">Status dihitung dari tanggal berakhir. Hapus = menonaktifkan,
        bukan menghapus baris — riwayat pengisian harus tetap punya rujukan.</p>`);
}

/* ── pemasangan peristiwa (delegasi, sekali) ─────────────────────── */
if (typeof document !== 'undefined' && !window.__kerjasamaTerpasang) {
  window.__kerjasamaTerpasang = true;

  document.addEventListener('click', async (e) => {
    if (e.target.closest('[data-tambah-mitra]')) {
      bukaPanel(null);
      return;
    }
    const ubah = e.target.closest('[data-ubah-mitra]');
    if (ubah) {
      const id = Number(ubah.dataset.ubahMitra);
      const m = (data?.mitra || []).find((x) => Number(x.id) === id);
      if (m) bukaPanel(m);
      return;
    }
    const hapus = e.target.closest('[data-hapus-mitra]');
    if (hapus) {
      const id = Number(hapus.dataset.hapusMitra);
      if (!confirm(`Nonaktifkan mitra "${hapus.dataset.nama}"? Baris tidak dihapus, hanya tidak dipakai lagi.`)) return;
      hapus.disabled = true;
      try {
        const r = await fetch(API, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id }),
        });
        const j = await r.json();
        if (!r.ok || j.error) { alert(j.error || 'Gagal.'); hapus.disabled = false; return; }
        await muat();
      } catch (_) { alert('Server tidak merespons.'); hapus.disabled = false; }
      return;
    }
    if (e.target.closest('[data-tutup]')) {
      tutupPanel();
      return;
    }
    if (e.target.closest('#btnSimpanMitra')) {
      simpan();
    }
    if (e.target.closest('[data-muat-ulang]')) {
      data = null;
      galat = '';
      if (window.__paint) window.__paint();
      await muat();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') tutupPanel();
  });
}

export default page;
