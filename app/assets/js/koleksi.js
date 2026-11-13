/* Koleksi generik — daftar + form isian, dibentuk dari definisi field di server.
 *
 * Kenapa begini: Mahasiswa, Kurikulum, Sarana, Pustaka, Uji Kompetensi, Survei
 * punya bentuk sama. Kalau tiap halaman ditulis sendiri, satu perubahan aturan
 * harus disalin enam kali dan pasti ada yang tertinggal.
 *
 * Kontrak dengan server (GET /api/<koleksi>):
 *   { baris, field, judul, kode, kolom, keterangan, prodi, boleh_ubah }
 * field[kol] = { label, jenis, wajib, maks, min, opsi, opsi_teks, sumber, pilihan, bawaan }
 *
 * Halaman tinggal:  export default buatHalaman('mahasiswa');
 *
 * CATATAN PENTING: listener dipasang SATU KALI di tingkat modul, dan hanya
 * meneruskan ke koleksi yang sedang aktif. Kalau tiap koleksi memasang
 * listener sendiri, delapan halaman saling menangkap klik — klik "Ubah" di
 * Pustaka akan membuka panel Mahasiswa.
 */
import { esc, head, badge, card, cardHead, callout } from './pagekit.js';

const LEBAR_PENUH = new Set(['panjang']);
let aktif = null;          // instance yang sedang tampil
let pasang = false;        // listener sudah dipasang?

const esc2 = (v) => esc(String(v ?? ''));

function nilaiAwal(f, v) {
  return v === null || v === undefined ? (f.bawaan ?? '') : v;
}

function kontrol(kol, f, v, dis) {
  const id = 'f_' + kol;
  const wajib = f.wajib ? ' <span class="wajib">*</span>' : '';
  const nilai = nilaiAwal(f, v);

  if (f.jenis === 'centang') {
    return `<label class="frm-centang" style="grid-column:1/-1">
      <input type="checkbox" id="${id}" name="${kol}" ${nilai ? 'checked' : ''} ${dis}>
      <span>${esc2(f.label)}</span></label>`;
  }

  let isi;
  if (f.jenis === 'pilihan') {
    let opsi;
    if (f.sumber) {
      opsi = '<option value="">— pilih —</option>' + (f.pilihan || []).map((p) =>
        `<option value="${p.id}"${String(nilai) === String(p.id) ? ' selected' : ''}>${esc2(p.teks)}</option>`).join('');
    } else {
      const teks = f.opsi_teks || {};
      opsi = '<option value="">— pilih —</option>' + (f.opsi || []).map((o) =>
        `<option value="${esc2(o)}"${String(nilai) === String(o) ? ' selected' : ''}>${esc2(teks[o] || o)}</option>`).join('');
    }
    isi = `<select id="${id}" name="${kol}" ${dis}>${opsi}</select>`;
  } else if (f.jenis === 'panjang') {
    isi = `<textarea id="${id}" name="${kol}" rows="3" ${dis}>${esc2(nilai)}</textarea>`;
  } else {
    const angka = f.jenis === 'angka' || f.jenis === 'pecahan';
    const tipe = f.jenis === 'tanggal' ? 'date' : angka ? 'number' : 'text';
    const step = f.jenis === 'pecahan' ? ' step="0.01"' : '';
    const min = f.min !== null && f.min !== undefined ? ` min="${f.min}"` : '';
    const maks = angka && f.maks !== null && f.maks !== undefined ? ` max="${f.maks}"` : '';
    const maxlen = !angka && f.maks !== null && f.maks !== undefined ? ` maxlength="${f.maks}"` : '';
    isi = `<input class="input" id="${id}" name="${kol}" type="${tipe}"${step}${min}${maks}${maxlen}
            value="${esc2(nilai)}" ${dis}>`;
  }

  const lebar = LEBAR_PENUH.has(f.jenis) ? ' style="grid-column:1/-1"' : '';
  return `<div class="frm-fld"${lebar}>
    <label class="frm-lab" for="${id}">${esc2(f.label || kol)}${wajib}</label>
    ${isi}</div>`;
}

/* Nilai pilihan yang menyatakan keadaan lebih cepat dipindai sebagai badge
   daripada teks abu. Dipetakan ke kelas warna yang sudah ada di app.css. */
const BADGE_NILAI = {
  Aktif: 'ok', Lulus: 'ok', Baik: 'ok', ya: 'ok',
  Cuti: 'warn', 'Rusak Ringan': 'warn',
  DO: 'bad', 'Rusak Berat': 'bad',
};

function sel(kol, f, baris) {
  const v = baris[kol];
  if (f.jenis === 'centang') return v ? badge('ok', 'ya') : badge('muted', 'belum');
  if (f.sumber) {
    const teks = (f.pilihan || []).find((p) => String(p.id) === String(v));
    return esc2(teks ? teks.teks : '—');
  }
  if (kol === 'prodi_id') return esc2(baris.prodi_nama || '—');
  if (v === null || v === undefined || v === '') return '<span class="muted">—</span>';
  if (f.jenis === 'tanggal') {
    const t = new Date(v + 'T00:00:00');
    return isNaN(t) ? esc2(v)
      : t.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  }
  if (f.jenis === 'pecahan') {
    return `<span class="num">` + Number(v).toLocaleString('id-ID',
      { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>';
  }
  if (f.jenis === 'pilihan' && BADGE_NILAI[v]) return badge(BADGE_NILAI[v], v);
  if (f.jenis === 'pilihan') return esc2(v);
  // Tahun (1990-2100) jangan diberi pemisah ribuan: "2.024" terbaca dua ribu
  // dua puluh empat, bukan tahun 2024.
  if (f.jenis === 'angka') {
    const n = Number(v);
    const teks = (n >= 1900 && n <= 2100) ? String(n) : n.toLocaleString('id-ID');
    return `<span class="num">${teks}</span>`;
  }
  return esc2(v);
}

export function buatHalaman(nama) {
  const API = '/api/' + nama;
  const inst = { data: null, galat: '', saring: { cari: '', prodi: '' }, sedangSimpan: false };
  const tutupPanel = () => document.getElementById('panelKoleksi')?.remove();

  async function muat() {
    aktif = inst;
    try {
      const qs = new URLSearchParams(Object.entries(inst.saring).filter(([, v]) => v));
      const r = await fetch(API + (qs.toString() ? '?' + qs : ''), { credentials: 'same-origin' });
      if (!r.ok) {
        inst.galat = (await r.json().catch(() => ({}))).error || `HTTP ${r.status}`;
        if (window.__paint) window.__paint();
        return;
      }
      inst.data = await r.json();
      inst.galat = '';
    } catch (e) {
      inst.galat = 'Server tidak merespons.';
    }
    if (window.__paint) window.__paint();
  }

  function formPanel(row) {
    const x = row || {};
    const d = inst.data;
    const boleh = d.boleh_ubah;
    const dis = boleh ? '' : 'disabled';
    const isi = Object.entries(d.field).map(([kol, f]) => kontrol(kol, f, x[kol], dis)).join('');

    return `<div class="panel baca" id="panelKoleksi" role="dialog" aria-modal="true" aria-labelledby="panelJudulK">
      <div class="panel-tirai" data-tutup="1"></div>
      <div class="panel-isi">
        <div class="panel-kepala">
          <h2 id="panelJudulK">${row ? 'Ubah' : 'Tambah'} ${esc2(d.judul)}</h2>
          <span class="note">${esc2(d.kode)}</span>
          <button class="panel-x" type="button" data-tutup="1" aria-label="Tutup">×</button>
        </div>
        <div class="panel-badan">
          ${boleh ? '' : callout('warn', 'Hanya lihat', '<p>Peran Anda tidak boleh mengubah data.</p>')}
          <div class="frm" id="frmKoleksi" data-id="${row ? row.id : ''}">
            ${isi}
            <div class="frm-err" id="frmErrK" role="alert"></div>
          </div>
        </div>
        <div class="panel-kaki">
          <button class="btn btn-ghost" type="button" data-tutup="1">Batal</button>
          ${boleh ? '<button class="btn btn-primary" type="button" id="btnSimpanK">Simpan</button>' : ''}
        </div>
      </div>
    </div>`;
  }

  function bukaPanel(row) {
    tutupPanel();
    document.body.insertAdjacentHTML('beforeend', formPanel(row));
    document.getElementById('panelKoleksi')?.classList.add('buka');
  }

  async function simpan() {
    const frm = document.getElementById('frmKoleksi');
    if (!frm || inst.sedangSimpan) return;
    const err = document.getElementById('frmErrK');
    const btn = document.getElementById('btnSimpanK');
    const d = inst.data;

    const kirim = { id: frm.dataset.id || '0' };
    for (const [kol, f] of Object.entries(d.field)) {
      const el = frm.querySelector('[name="' + kol + '"]');
      if (!el) continue;
      kirim[kol] = f.jenis === 'centang' ? (el.checked ? 1 : 0) : el.value;
    }

    for (const [kol, f] of Object.entries(d.field)) {
      if (f.wajib && !String(kirim[kol] ?? '').trim()) {
        err.textContent = (f.label || kol) + ' wajib diisi.';
        frm.querySelector('[name="' + kol + '"]')?.focus();
        return;
      }
    }

    inst.sedangSimpan = true;
    btn.disabled = true;
    err.textContent = '';
    try {
      const r = await fetch(API, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(kirim),
      });
      const j = await r.json();
      if (!r.ok || j.error) { err.textContent = j.error || `Gagal (HTTP ${r.status}).`; return; }
      tutupPanel();
      await muat();
    } catch (e) {
      err.textContent = 'Server tidak merespons. Data belum tersimpan.';
    } finally {
      inst.sedangSimpan = false;
      btn.disabled = false;
    }
  }

  function page() {
    if (!inst.data) {
      if (inst.galat) {
        return head(nama, '') + callout('bad', 'Data tidak bisa dimuat', `<p>${esc2(inst.galat)}</p>`)
          + '<button class="btn btn-primary" data-muat-ulang="1">Coba lagi</button>';
      }
      return head(nama, '') + '<p class="muted">Memuat…</p>';
    }

    const { baris, field, judul, kode, keterangan, prodi, boleh_ubah } = inst.data;
    const aksi = boleh_ubah ? '<button class="btn btn-primary" data-tambah-k="1">+ Tambah</button>' : '';
    const adaProdi = Object.values(field).some((f) => f.sumber === 'prodi');
    const bisaCari = Object.values(field).some((f) => f.jenis === 'teks');

    const saringHtml = `<div class="frm-row" style="margin-bottom:10px">
      ${bisaCari ? `<div class="frm-fld"><label class="frm-lab">Cari</label>
        <input class="input" id="kCari" value="${esc2(inst.saring.cari)}" placeholder="kata kunci"></div>` : ''}
      ${adaProdi ? `<div class="frm-fld"><label class="frm-lab">Prodi</label>
        <select id="kProdi"><option value="">Semua</option>${(prodi || []).map((p) =>
          `<option value="${p.id}"${String(inst.saring.prodi) === String(p.id) ? ' selected' : ''}>${esc2(p.nama_ps)}</option>`).join('')}</select></div>` : ''}
    </div>`;

    if (baris.length === 0) {
      const adaSaring = inst.saring.cari || inst.saring.prodi;
      return head(judul, kode, aksi)
        + card(saringHtml)
        + (adaSaring
          ? callout('info', 'Tidak ada yang cocok', '<p>Kosongkan saringan atau ubah kata kunci.</p>')
          : callout('info', 'Belum ada data',
            `<p>${esc2(keterangan || 'Belum ada baris yang diisi.')}</p>`));
    }

    /* Kolom tabel diturunkan dari field yang sama dengan form, jadi tidak
       mungkin ada kolom yang tidak bisa diisi atau field yang hilang. */
    /* Kolom angka rata kanan supaya digit lurus vertikal; teks rata kiri.
       Tanpa ini mata harus menyapu kiri-kanan untuk membandingkan angka. */
    const angkaKol = new Set(Object.entries(field)
      .filter(([, f]) => f.jenis === 'angka' || f.jenis === 'pecahan')
      .map(([kol]) => kol));
    const tampil = Object.entries(field).slice(0, 8);
    const kepala = tampil.map(([kol, f]) =>
      `<th${angkaKol.has(kol) ? ' class="r"' : ''}>${esc2(f.label)}</th>`).join('');
    const isi = baris.map((b) => {
      const td = tampil.map(([kol, f]) =>
        `<td${angkaKol.has(kol) ? ' class="r"' : ''}>${sel(kol, f, b)}</td>`).join('');
      const tombol = boleh_ubah
        ? `<td class="r aksi-sel"><button class="lnk" data-ubah-k="${b.id}">Ubah</button>
           <button class="lnk" data-hapus-k="${b.id}">Hapus</button></td>`
        : '<td></td>';
      return `<tr>${td}${tombol}</tr>`;
    }).join('');

    return head(judul, `${kode} · ${baris.length} baris`, aksi)
      + (keterangan ? callout('info', 'Yang perlu diketahui', `<p>${esc2(keterangan)}</p>`) : '')
      + card(saringHtml
        + `<table class="tbl"><thead><tr>${kepala}<th></th></tr></thead>
           <tbody>${isi}</tbody></table>`);
  }

  if (typeof document !== 'undefined' && !pasang) {
    pasang = true;

    document.addEventListener('click', async (e) => {
      const d = aktif;
      if (!d || !d.data) return;

      if (e.target.closest('[data-tambah-k]')) { aktif.buka(null); return; }
      const ubah = e.target.closest('[data-ubah-k]');
      if (ubah) {
        const id = Number(ubah.dataset.ubahK);
        const b = (d.data.baris || []).find((x) => Number(x.id) === id);
        if (b) aktif.buka(b);
        return;
      }
      const hapus = e.target.closest('[data-hapus-k]');
      if (hapus) {
        const id = Number(hapus.dataset.hapusK);
        if (!confirm('Hapus baris ini? Tindakan ini tercatat di riwayat.')) return;
        hapus.disabled = true;
        try {
          const r = await fetch(aktif.API, {
            method: 'DELETE', headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin', body: JSON.stringify({ id }),
          });
          const j = await r.json();
          if (!r.ok || j.error) { alert(j.error || 'Gagal menghapus.'); hapus.disabled = false; return; }
          await aktif.muat();
        } catch (_) { alert('Server tidak merespons.'); hapus.disabled = false; }
        return;
      }
      if (e.target.closest('[data-tutup]')) { tutupPanel(); return; }
      if (e.target.closest('#btnSimpanK')) { aktif.simpanData(); return; }
      if (e.target.closest('[data-muat-ulang]')) {
        aktif.data = null; aktif.galat = '';
        if (window.__paint) window.__paint();
        await aktif.muat();
      }
    });

    document.addEventListener('change', async (e) => {
      if (!aktif || !aktif.data) return;
      if (e.target.id === 'kProdi') { aktif.saring.prodi = e.target.value; await aktif.muat(); }
    });
    document.addEventListener('input', (e) => {
      if (!aktif || !aktif.data || e.target.id !== 'kCari') return;
      clearTimeout(window.__cariK);
      window.__cariK = setTimeout(async () => {
        aktif.saring.cari = e.target.value;
        await aktif.muat();
      }, 350);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') tutupPanel();
    });
  }

  inst.API = API;
  inst.muat = muat;
  inst.buka = bukaPanel;
  inst.simpanData = simpan;
  inst.page = page;
  return { page, muat };
}
