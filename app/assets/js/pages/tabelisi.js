/* tabelisi — halaman isian satu tabel data dukung (#/tabelisi/01).
 *
 * KENAPA HALAMAN SENDIRI, BUKAN PANEL: satu tabel bisa punya 100+ baris dan
 * 14 kolom (Tabel 22). Panel yang mengambang tidak mungkin dipakai untuk itu.
 * Halaman penuh memberi ruang, dan tombol "kembali" jelas.
 *
 * Bentuk isian ditentukan `spec` dari server (spec_field): tipe teks, angka,
 * pilihan, centang, relasi. Jadi menambah field di generator otomatis muncul
 * di sini tanpa menyentuh berkas ini.
 *
 * ATURAN KELOMPOK: field dengan `kelompok` sama dan `pilih`='satu' hanya boleh
 * satu yang terisi. Ditegakkan di klien saat mengetik DAN oleh server saat
 * simpan — kalau hanya di klien, data mustahil masih bisa masuk lewat API.
 */
import { esc, head, strip, badge, card, cardHead, callout } from '../pagekit.js';

let kode = null;
let data = null;      // {kode, status, spec, baris}
let galat = '';
let kotor = false;    // ada perubahan belum disimpan
let sedang = false;

const API = (k) => `/api/tabel/${encodeURIComponent(k)}`;

export async function muat() {
  const h = location.hash.replace('#/tabelisi/', '').replace('#/tabelisi', '');
  kode = h.replace(/[^0-9]/g, '').padStart(2, '0');
  if (!kode || kode === '00') { galat = 'Kode tabel tidak dikenal.'; return; }
  try {
    const r = await fetch(API(kode), { credentials: 'same-origin' });
    const j = await r.json();
    if (!r.ok) { galat = j.error || `HTTP ${r.status}`; data = null; }
    else { data = j; galat = ''; }
  } catch (e) {
    galat = 'Server tidak merespons.';
  }
  kotor = false;
  if (window.__paint) window.__paint();
}

/* ── satu sel isian ─────────────────────────────────────────────── */
function sel(kol, f, barisIdx, nilai, dis) {
  const id = `s_${barisIdx}_${kol}`;
  const nama = `data-baris="${barisIdx}" data-kunci="${esc(kol)}"`;

  if (f.tipe === 'centang' || f.tipe === 'ya/tidak') {
    const aktif = Number(nilai) === 1 || nilai === '1';
    return `<label class="sel-centang"><input type="checkbox" id="${id}" ${nama}
      ${aktif ? 'checked' : ''} ${dis}></label>`;
  }

  if (f.tipe === 'pilihan') {
    // Opsi diambil dari label field itu sendiri: "Kepemilikan: SD / SW"
    // -> SD, SW. Instrumen menulisnya begitu, tidak ada sumber lain.
    const opsi = opsiDari(f.label);
    return `<select class="input sel-input" id="${id}" ${nama} ${dis}>
      <option value=""></option>
      ${opsi.map((o) => `<option value="${esc(o)}"${String(nilai) === o ? ' selected' : ''}>${esc(o)}</option>`).join('')}
    </select>`;
  }

  if (f.tipe === 'tahun akademik') {
    const opsi = ['TS-2', 'TS-1', 'TS'];
    return `<select class="input sel-input" id="${id}" ${nama} ${dis}>
      <option value=""></option>
      ${opsi.map((o) => `<option value="${esc(o)}"${String(nilai) === o ? ' selected' : ''}>${esc(o)}</option>`).join('')}
    </select>`;
  }

  const tipe = (f.tipe === 'angka' || f.tipe === 'desimal' || f.tipe === 'persen') ? 'number' : 'text';
  const step = f.tipe === 'desimal' || f.tipe === 'persen' ? ' step="any"' : '';
  return `<input class="input sel-input" id="${id}" ${nama} type="${tipe}"${step}
    value="${esc(nilai ?? '')}" ${dis}>`;
}

/** "Kepemilikan: SD / SW" -> ['SD','SW']; "Tingkat: a / b / c" -> ['a','b','c'] */
function opsiDari(label) {
  const i = String(label).indexOf(':');
  if (i < 0) return [];
  return String(label).slice(i + 1).split('/').map((s) => s.trim()).filter(Boolean);
}

/* ── halaman ────────────────────────────────────────────────────── */
function page() {
  if (!data) {
    return head(`Data Dukung ${kode || ''}`, '', '')
      + (galat
        ? callout('bad', 'Tidak bisa dimuat', `<p>${esc(galat)}</p>`)
          + '<button class="btn btn-ghost" data-kembali="1">Kembali ke daftar</button>'
        : '<p class="muted">Memuat…</p>');
  }

  const sp = data.spec || { fields: [] };
  const baris = data.baris || [];
  const status = data.status || {};
  const terkunci = !!status.terkunci || baris.some((b) => b.dikunci);
  const dis = terkunci ? 'disabled' : '';

  // Field yang bisa diisi. Master/Hitung tidak ditampilkan sebagai input:
  // nilainya datang dari master atau dihitung, bukan diketik di sini.
  const bisa = sp.fields.filter((f) => f.asal === 'Input');
  const semua = sp.fields;

  const kepala = semua.map((f) => `<th title="${esc(f.tipe)}">${esc(f.label)}</th>`).join('');

  const isi = baris.length === 0
    ? `<tr><td colspan="${semua.length + 1}" class="muted" style="text-align:center;padding:24px">
         Belum ada baris. Klik <b>Tambah baris</b> untuk mulai mengisi.</td></tr>`
    : baris.map((b, i) => {
      const td = semua.map((f) => {
        // Nilai diambil untuk SEMUA kolom, bukan hanya yang Input. Dulu baris
        // ini menyaring lewat `bisa.find(...)`, sehingga kolom Master selalu
        // menerima string kosong — hasil tarikan master tidak pernah tampil
        // walau datanya sudah ada di DB.
        const v = b.nilai[f.kunci] ?? '';
        if (f.asal === 'Master') {
          // Nilai dari master DITAMPILKAN, tapi tidak bisa diketik. Dulu
          // semua kolom non-Input ditulis "—", sehingga hasil "Tarik dari
          // master" tidak kelihatan sama sekali padahal datanya sudah masuk.
          // Angka dari DB datang sebagai "1.0000" (DECIMAL). Menampilkannya
          // apa adanya bikin kolom centang terlihat seperti desimal.
          let tampil = (v === '' || v === null || v === undefined) ? '—' : String(v);
          if (/^-?\d+\.0+$/.test(tampil)) tampil = String(parseInt(tampil, 10));
          else if (/^-?\d+\.\d{1,}$/.test(tampil)) tampil = String(Number(tampil));
          return `<td class="sel-master" title="dari master: ${esc(f.label)}">${esc(tampil)}</td>`;
        }
        if (f.asal !== 'Input') {
          // Kolom hitungan: kalau nilainya sudah ada (ditarik dari master atau
          // dihitung), TAMPILKAN. Hanya kalau benar-benar kosong pakai tanda
          // pisah — bukan 0, karena 0 terbaca "memang nol".
          if (v === '' || v === null || v === undefined) {
            return `<td class="sel-hitung" title="dihitung sistem">—</td>`;
          }
          let tampil = String(v);
          if (/^-?\d+\.0+$/.test(tampil)) tampil = String(parseInt(tampil, 10));
          else if (/^-?\d+\.\d+$/.test(tampil)) tampil = String(Number(tampil));
          return `<td class="sel-hitung sel-ada" title="dihitung sistem">${esc(tampil)}</td>`;
        }
        return `<td>${sel(f.kunci, f, i, v, dis)}</td>`;
      }).join('');
      const hapus = terkunci ? '' :
        `<button class="lnk lnk-bad" data-hapus-baris="${i}" title="hapus baris">×</button>`;
      return `<tr data-baris="${i}">${td}<td class="r">${hapus}</td></tr>`;
    }).join('');

  // Tombol tarik hanya muncul kalau tabel ini memang punya peta tarikan.
  // Menampilkannya di semua tabel lalu menolak di server = tombol bohong.
  const tombolTarik = (!terkunci && data.bisa_tarik)
    ? `<button class="btn btn-ghost" data-tarik="1" title="ambil dari master dosen/lab/wahana/pustaka">Tarik dari master</button>`
    : '';

  const aksi = terkunci
    ? `<span class="badge lock">terkunci</span>`
    : `${tombolTarik}
       <button class="btn btn-ghost" data-tambah-baris="1">+ Tambah baris</button>
       <button class="btn btn-primary" data-simpan="1">Simpan</button>`;

  return head(`Tabel ${kode}`,
    `${esc(data.judul || '')} · ${baris.length} baris · ${bisa.length} kolom isian`,
    `<button class="btn btn-ghost" data-kembali="1">← Daftar tabel</button> ` + aksi)
    + (terkunci ? callout('lock', 'Tabel terkunci',
      `<p>Sudah dikunci verifikator. Isian tidak bisa diubah sampai dibuka.</p>`) : '')
    + (bisa.length === 0 ? callout('info', 'Tabel hitungan',
      `<p>Semua kolom tabel ini dihitung sistem dari master. Tidak ada yang perlu diketik.</p>`) : '')
    + (data.bisa_tarik ? callout('info', 'Sebagian kolom diambil dari master',
      `<p>Kolom bertanda — diisi otomatis dari master (dosen, laboratorium, wahana, pustaka). `
      + `Klik <b>Tarik dari master</b> untuk mengisinya. Isian yang Anda ketik sendiri tidak akan tertimpa.</p>`) : '')
    + card(cardHead(`Isian tabel ${kode}`, `${baris.length} baris`)
      + `<div style="overflow-x:auto">
        <table class="tbl tbl-isi">
          <thead><tr>${kepala}<th></th></tr></thead>
          <tbody id="isiTabel">${isi}</tbody>
        </table>
      </div>
      <p class="foot-note">Kolom bertanda — adalah kolom hitungan/master; tidak diketik di sini.
        Perubahan tersimpan setelah klik Simpan.</p>`)
    + `<div class="frm-err" id="errTabel" role="alert" style="margin-top:10px"></div>`;
}

/* ── kumpulkan isi form jadi payload ────────────────────────────── */
function kumpulkan() {
  const barisEl = document.querySelectorAll('#isiTabel tr[data-baris]');
  const out = [];
  barisEl.forEach((tr) => {
    const i = Number(tr.dataset.baris);
    const asli = (data.baris || [])[i] || {};
    const nilai = {};
    tr.querySelectorAll('[data-kunci]').forEach((el) => {
      const k = el.dataset.kunci;
      if (el.type === 'checkbox') { if (el.checked) nilai[k] = 1; }
      else if (String(el.value).trim() !== '') nilai[k] = el.value.trim();
    });
    out.push({ id: asli.id || 0, nilai });
  });
  return out;
}

/**
 * Tegakkan aturan kelompok pilih-satu di klien, supaya operator langsung tahu
 * sebelum menunggu server. Server tetap memeriksa ulang — klien bisa dilewati.
 */
function periksaKelompok(payload) {
  const grup = {};
  (data.spec.fields || []).forEach((f) => {
    if (!f.kelompok || f.pilih !== 'satu') return;
    grup[f.kelompok] = grup[f.kelompok] || [];
    grup[f.kelompok].push(f);
  });

  for (let i = 0; i < payload.length; i++) {
    for (const [nama, fields] of Object.entries(grup)) {
      const terisi = fields.filter((f) => {
        const v = payload[i].nilai[f.kunci];
        return v !== undefined && v !== '' && v !== 0;
      });
      if (terisi.length > 1) {
        return `Baris ${i + 1}: kolom ${nama.replace(/_/g, ' ')} hanya boleh satu `
          + `yang diisi, tapi ${terisi.length} terisi (${terisi.map((f) => f.label).join(', ')}).`;
      }
    }
  }
  return '';
}

async function simpan() {
  if (sedang) return;
  const err = document.getElementById('errTabel');
  const btn = document.querySelector('[data-simpan]');
  const payload = kumpulkan();

  err.className = 'frm-err';
  const salah = periksaKelompok(payload);
  if (salah) { err.textContent = salah; return; }

  sedang = true;
  btn.disabled = true;
  err.textContent = '';
  try {
    const r = await fetch(API(kode) + '/isi', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin', body: JSON.stringify({ baris: payload }),
    });
    const j = await r.json();
    if (!r.ok || j.error) { err.textContent = j.error || `Gagal (HTTP ${r.status}).`; return; }
    kotor = false;
    await muat();
  } catch (e) {
    err.textContent = 'Server tidak merespons. Data belum tersimpan.';
  } finally {
    sedang = false;
    btn.disabled = false;
  }
}

/* ── peristiwa ─────────────────────────────────────────────────── */
if (typeof document !== 'undefined' && !window.__tabelisiPasang) {
  window.__tabelisiPasang = true;

  document.addEventListener('click', async (e) => {
    if (e.target.closest('[data-kembali]')) {
      if (kotor && !confirm('Ada perubahan belum disimpan. Tinggalkan halaman?')) return;
      location.hash = '#/datadukung';
      return;
    }
    if (!data) return;

    if (e.target.closest('[data-tambah-baris]')) {
      const tbody = document.getElementById('isiTabel');
      const kosong = tbody.querySelector('td[colspan]');
      if (kosong) kosong.closest('tr').remove();
      const i = document.querySelectorAll('#isiTabel tr[data-baris]').length;
      const semua = data.spec.fields;
      const bisa = semua.filter((f) => f.asal === 'Input');
      const td = semua.map((f) => {
        if (f.asal !== 'Input') return '<td class="sel-hitung">—</td>';
        return `<td>${sel(f.kunci, f, i, '', '')}</td>`;
      }).join('');
      tbody.insertAdjacentHTML('beforeend',
        `<tr data-baris="${i}">${td}<td class="r"><button class="lnk lnk-bad" data-hapus-baris="${i}">×</button></td></tr>`);
      kotor = true;
      return;
    }

    const hapus = e.target.closest('[data-hapus-baris]');
    if (hapus) {
      hapus.closest('tr').remove();
      // Nomor baris digeser supaya payload tetap berurutan.
      document.querySelectorAll('#isiTabel tr[data-baris]').forEach((tr, n) => {
        tr.dataset.baris = n;
      });
      kotor = true;
      return;
    }

    if (e.target.closest('[data-tarik]')) {
      const btn = e.target.closest('[data-tarik]');
      if (kotor && !confirm('Ada perubahan belum disimpan. Tarik tetap akan menimpa kolom master. Lanjut?')) return;
      btn.disabled = true;
      btn.textContent = 'Menarik…';
      const err = document.getElementById('errTabel');
      try {
        const r = await fetch(API(kode) + '/tarik', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin', body: JSON.stringify({ timpa: 0 }),
        });
        const j = await r.json();
        if (!r.ok || j.error) { err.textContent = j.error || `Gagal (HTTP ${r.status}).`; return; }
        kotor = false;
        await muat();
        // Beri tahu hasilnya: tanpa angka, operator tidak tahu apakah
        // tarikan berhasil atau master-nya memang kosong.
        // Pesan sukses jangan memakai kelas galat: warna merah terbaca
        // seperti kegagalan, dan operator akan menekan tombolnya lagi.
        const kotak = document.getElementById('errTabel');
        kotak.className = 'frm-ok';
        kotak.textContent =
          `Ditarik dari master: ${j.dibuat} baris baru, ${j.diperbarui} diperbarui dari ${j.sumber_baris} data master.`;
      } catch (_) {
        err.textContent = 'Server tidak merespons.';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Tarik dari master';
      }
      return;
    }

    if (e.target.closest('[data-simpan]')) { simpan(); return; }
  });

  document.addEventListener('input', () => { kotor = true; });
  document.addEventListener('change', () => { kotor = true; });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && page() === 'tabelisi') {
      if (kotor && !confirm('Ada perubahan belum disimpan. Tinggalkan halaman?')) return;
      location.hash = '#/datadukung';
    }
  });
}

export default page;
