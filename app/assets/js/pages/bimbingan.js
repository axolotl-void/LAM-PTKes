/* bimbingan — sumber angka Tabel 21 (#/bimbingan).
 *
 * Sebelumnya Tabel 21 SELALU kosong: `bimbingan` tidak punya formulir di mana
 * pun, jadi angka "dihitung dari master" tidak pernah punya master. Halaman
 * ini mengisi lubang itu.
 *
 * Satu baris = satu mahasiswa dibimbing satu dosen pada satu tahun akademik.
 * Lama bimbingan DIHITUNG dari tanggal (hari / 30.44 → bulan), tidak diketik:
 * angka turunan yang disimpan akan basi saat tanggal diperbaiki.
 *
 * Berbeda dari Kerja Sama: hapus di sini benar-benar DELETE. `bimbingan`
 * adalah catatan proses, bukan dokumen yang dirujuk tabel lain.
 */
import { esc, head, strip, badge, card, cardHead, callout, empty } from '../pagekit.js';

const API = '/api/bimbingan';

let data = null;     // { bimbingan, pilihan, status, kurang, boleh_ubah }
let galat = '';
let sedangSimpan = false;

export const css = '';

export async function muat() {
  try {
    const r = await fetch(API, { credentials: 'same-origin' });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || j.error) {
      galat = j.error || `HTTP ${r.status}`;
      data = null;
    } else {
      data = j;
      galat = '';
    }
  } catch (e) {
    galat = 'Server tidak merespons.';
  }
  if (typeof window !== 'undefined' && window.__paint) window.__paint();
}

/* ── panel isian ─────────────────────────────────────────────────── */
function formPanel(b = null) {
  const d = b || {};
  const boleh = data?.boleh_ubah;
  const dosen = data?.pilihan?.dosen || [];
  const mhs = data?.pilihan?.mahasiswa || [];
  const status = data?.status || ['Berjalan', 'Selesai', 'Gagal'];
  const judul = b ? 'Ubah bimbingan' : 'Tambah bimbingan';

  const optDosen = dosen.map((x) =>
    `<option value="${x.id}"${Number(d.dosen_id) === Number(x.id) ? ' selected' : ''}>${esc(x.nama)}</option>`).join('');
  const optMhs = ['<option value="">— belum ditentukan —</option>'].concat(
    mhs.map((x) =>
      `<option value="${x.id}"${Number(d.mahasiswa_id) === Number(x.id) ? ' selected' : ''}>${esc(x.nim)} · ${esc(x.nama)}</option>`)).join('');
  const optStatus = status.map((s) =>
    `<option value="${esc(s)}"${(d.status || 'Berjalan') === s ? ' selected' : ''}>${esc(s)}</option>`).join('');

  return `<div class="panel baca" id="panelBmb" role="dialog" aria-modal="true" aria-labelledby="panelBmbJudul">
    <div class="panel-tirai" data-tutup="1"></div>
    <div class="panel-isi">
      <div class="panel-kepala">
        <h2 id="panelBmbJudul">${esc(judul)}</h2>
        <span class="note">Sumber Tabel 21</span>
        <button class="panel-x" type="button" data-tutup="1" aria-label="Tutup">×</button>
      </div>
      <div class="panel-badan">
        ${boleh ? '' : callout('warn', 'Tidak boleh mengubah data',
          '<p>Peran Anda hanya bisa melihat.</p>')}
        <div class="frm" id="frmBmb">
          <input type="hidden" name="id" value="${b ? b.id : ''}">
          <div class="frm-fld">
            <label class="frm-lab" for="fDosen">Dosen pembimbing <span class="wajib">*</span></label>
            <select id="fDosen" name="dosen_id" ${boleh ? '' : 'disabled'}>
              <option value="">— pilih dosen —</option>${optDosen}
            </select>
          </div>
          <div class="frm-fld">
            <label class="frm-lab" for="fMhs">Mahasiswa yang dibimbing</label>
            <select id="fMhs" name="mahasiswa_id" ${boleh ? '' : 'disabled'}>${optMhs}</select>
            <span class="frm-hint">Kosongkan kalau bimbingan belum sampai tahap per orang.</span>
          </div>
          <div class="frm-row">
            <div class="frm-fld">
              <label class="frm-lab" for="fTahun">Tahun akademik <span class="wajib">*</span></label>
              <input class="input" id="fTahun" name="tahun_akademik" type="number" min="2000" max="2100"
                     value="${esc(d.tahun_akademik || '')}" placeholder="2025" ${boleh ? '' : 'disabled'}>
            </div>
            <div class="frm-fld">
              <label class="frm-lab" for="fPertemuan">Jumlah pertemuan</label>
              <input class="input" id="fPertemuan" name="jumlah_pertemuan" type="number" min="0" max="500"
                     value="${d.jumlah_pertemuan ?? ''}" ${boleh ? '' : 'disabled'}>
            </div>
          </div>
          <div class="frm-row">
            <div class="frm-fld">
              <label class="frm-lab" for="fMulai">Tanggal mulai</label>
              <input class="input" id="fMulai" type="date" name="mulai" value="${d.mulai || ''}" ${boleh ? '' : 'disabled'}>
            </div>
            <div class="frm-fld">
              <label class="frm-lab" for="fSelesai">Tanggal selesai</label>
              <input class="input" id="fSelesai" type="date" name="selesai" value="${d.selesai || ''}" ${boleh ? '' : 'disabled'}>
            </div>
          </div>
          <p class="frm-hint">Lama bimbingan dihitung dari dua tanggal ini, tidak diketik.
            Status "Selesai" menuntut tanggal selesai.</p>
          <div class="frm-fld">
            <label class="frm-lab" for="fStatus">Status</label>
            <select id="fStatus" name="status" ${boleh ? '' : 'disabled'}>${optStatus}</select>
          </div>
          <div class="frm-fld">
            <label class="frm-lab" for="fTopik">Topik / judul</label>
            <input class="input" id="fTopik" name="topik" maxlength="255" value="${esc(d.topik || '')}"
                   placeholder="Asuhan kebidanan pada ibu hamil" ${boleh ? '' : 'disabled'}>
          </div>
          <div class="frm-err" id="frmBmbErr" role="alert"></div>
        </div>
      </div>
      <div class="panel-kaki">
        <button class="btn btn-ghost" type="button" data-tutup="1">Batal</button>
        ${boleh ? '<button class="btn btn-primary" type="button" id="btnSimpanBmb">Simpan</button>' : ''}
      </div>
    </div>
  </div>`;
}

function bukaPanel(b = null) {
  document.getElementById('panelBmb')?.remove();
  document.body.insertAdjacentHTML('beforeend', formPanel(b));
  document.getElementById('panelBmb')?.classList.add('buka');
}

const tutupPanel = () => document.getElementById('panelBmb')?.remove();

async function simpan() {
  const frm = document.getElementById('frmBmb');
  if (!frm || sedangSimpan) return;
  const err = document.getElementById('frmBmbErr');
  const btn = document.getElementById('btnSimpanBmb');

  const isi = {
    id: frm.querySelector('[name=id]').value || '0',
    dosen_id: frm.querySelector('[name=dosen_id]').value,
    mahasiswa_id: frm.querySelector('[name=mahasiswa_id]').value,
    tahun_akademik: frm.querySelector('[name=tahun_akademik]').value,
    jumlah_pertemuan: frm.querySelector('[name=jumlah_pertemuan]').value,
    mulai: frm.querySelector('[name=mulai]').value,
    selesai: frm.querySelector('[name=selesai]').value,
    status: frm.querySelector('[name=status]').value,
    topik: frm.querySelector('[name=topik]').value,
  };

  // Pemeriksaan cepat; server memeriksa ulang dan bisa menolak dengan
  // beberapa alasan sekaligus.
  if (!isi.dosen_id) { err.textContent = 'Dosen pembimbing wajib dipilih.'; return; }
  if (!isi.tahun_akademik) { err.textContent = 'Tahun akademik wajib diisi.'; return; }

  sedangSimpan = true;
  btn.disabled = true;
  err.textContent = '';
  try {
    const r = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(isi),
    });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.ok) {
      // Tampilkan SEMUA alasan, bukan yang pertama saja.
      const alasan = Array.isArray(j.galat) ? j.galat
        : [j.error || `Gagal menyimpan (HTTP ${r.status}).`];
      err.innerHTML = alasan.map((a) => `<div>${esc(a)}</div>`).join('');
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
    return head('Bimbingan Tugas Akhir', 'Sumber Tabel 21')
      + (galat
        ? callout('bad', 'Data tidak bisa dimuat', `<p>${esc(galat)}</p>`)
        : '<p class="muted">Memuat…</p>');
  }

  const { bimbingan, pilihan, boleh_ubah, kurang } = data;
  const aksi = boleh_ubah && !kurang?.length
    ? '<button class="btn btn-primary" data-tambah-bmb="1">+ Tambah bimbingan</button>'
    : '';

  // Kalau master dosen/mahasiswa kosong, tambah bimbingan tidak mungkin.
  // Katakan apa yang harus diisi dulu — dropdown kosong membingungkan.
  const blokir = (kurang || []).length
    ? callout('warn', 'Belum bisa mengisi',
        '<p>' + kurang.map(esc).join(' ') + '</p>'
        + '<p>Isi dulu <a class="lnk" href="#/dosen">Dosen &amp; Tendik</a>'
        + (kurang.some((k) => k.includes('mahasiswa'))
          ? ' dan <a class="lnk" href="#/mahasiswa">Mahasiswa</a>' : '')
        + '. Tabel 21 dihitung dari dua daftar itu.</p>')
    : '';

  if (bimbingan.length === 0) {
    return head('Bimbingan Tugas Akhir', 'Sumber Tabel 21 · belum ada data', aksi)
      + blokir
      + callout('info', 'Belum ada catatan bimbingan',
        '<p>Tabel 21 menanyakan berapa mahasiswa dibimbing tiap dosen, berapa kali '
        + 'pertemuan, dan berapa lama bimbingannya — tiga tahun terakhir.</p>'
        + '<p>Catat satu baris per mahasiswa per tahun akademik. Angka Tabel 21 '
        + 'dihitung dari sini, tidak diketik di tabelnya.</p>')
      + empty('Bimbingan', ['Dosen pembimbing dan mahasiswa',
        'Tahun akademik, jumlah pertemuan',
        'Tanggal mulai dan selesai (lama dihitung dari sini)']);
  }

  const baris = bimbingan.map((b) => {
    const lama = b.lama_bulan === null ? '—' : `${b.lama_bulan} bln`;
    const tombol = boleh_ubah
      ? `<button class="lnk" data-ubah-bmb="${b.id}">Ubah</button>
         <button class="lnk" data-hapus-bmb="${b.id}" data-nama="${esc(b.mahasiswa || b.topik || '')}">Hapus</button>`
      : '';
    return `<tr>
      <td class="strong">${esc(b.dosen)}</td>
      <td>${b.mahasiswa ? `${esc(b.nim || '')} · ${esc(b.mahasiswa)}` : '<span class="muted">—</span>'}</td>
      <td class="num">${b.tahun_akademik}</td>
      <td class="r num">${b.jumlah_pertemuan ?? '—'}</td>
      <td class="r num">${esc(lama)}</td>
      <td>${badge(b.status === 'Selesai' ? 'ok' : b.status === 'Gagal' ? 'bad' : 'warn', b.status)}</td>
      <td class="r">${tombol}</td>
    </tr>`;
  }).join('');

  const selesai = bimbingan.filter((b) => b.status === 'Selesai').length;
  const totalMhs = new Set(bimbingan.map((b) => b.mahasiswa_id).filter(Boolean)).size;
  const dosenAktif = new Set(bimbingan.map((b) => b.dosen_id)).size;

  return head('Bimbingan Tugas Akhir',
    `Sumber Tabel 21 · ${bimbingan.length} catatan bimbingan`, aksi)
    + blokir
    + strip([
      [`${bimbingan.length}`, 'catatan bimbingan', ''],
      [`${dosenAktif}`, 'dosen membimbing', ''],
      [`${totalMhs}`, 'mahasiswa', ''],
      [`${selesai}`, 'selesai', selesai ? 'ok' : 'muted'],
    ])
    + card(cardHead('Catatan bimbingan', 'terbaru di atas · angka Tabel 21 dihitung dari sini')
      + `<table class="tbl">
        <thead><tr>
          <th>Dosen</th><th>Mahasiswa</th><th>Tahun</th>
          <th class="r">Pertemuan</th><th class="r">Lama</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>${baris}</tbody>
      </table>
      <p class="foot-note">Lama bimbingan dihitung dari tanggal mulai dan selesai.
        Hapus di sini benar-benar menghapus baris — catatan proses, bukan dokumen
        yang dirujuk tabel lain. <a class="lnk" href="#/tabelhitung/21">Lihat Tabel 21 →</a></p>`);
}

/* ── peristiwa ───────────────────────────────────────────────────── */
if (typeof document !== 'undefined' && !window.__bmbTerpasang) {
  window.__bmbTerpasang = true;

  document.addEventListener('click', async (e) => {
    if (e.target.closest('[data-tambah-bmb]')) { bukaPanel(null); return; }

    const ubah = e.target.closest('[data-ubah-bmb]');
    if (ubah) {
      const id = Number(ubah.dataset.ubahBmb);
      const b = (data?.bimbingan || []).find((x) => Number(x.id) === id);
      if (b) bukaPanel(b);
      return;
    }

    const hapus = e.target.closest('[data-hapus-bmb]');
    if (hapus) {
      const id = Number(hapus.dataset.hapusBmb);
      if (!confirm('Hapus catatan bimbingan ini? Tabel 21 akan dihitung ulang.')) return;
      hapus.disabled = true;
      try {
        const r = await fetch(API, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id }),
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || !j.ok) { alert(j.error || 'Gagal menghapus.'); hapus.disabled = false; return; }
        await muat();
      } catch (_) { alert('Server tidak merespons.'); hapus.disabled = false; }
      return;
    }

    if (e.target.closest('[data-tutup]')) { tutupPanel(); return; }
    if (e.target.closest('#btnSimpanBmb')) simpan();
  });
}

export default page;
