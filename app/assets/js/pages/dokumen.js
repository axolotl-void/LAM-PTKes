/* dokumen — Dokumen Pendukung (#/dokumen). 78 butir, 8 kriteria.
 *
 * Yang dikerjakan halaman ini hanya tiga: melihat status per butir, mengunggah
 * berkas, dan mengunduh/hapus versi. Semua aturan berat ada di server
 * (web/modul/dokumen/logika.php, diuji 52 uji): validasi tipe & ukuran,
 * penamaan aman, dan penjagaan path.
 *
 * KENAPA MENAMPILKAN RIWAYAT VERSI, BUKAN HANYA "SUDAH/BELUM":
 * Dokumen akreditasi sering diperbarui (MoU diperpanjang, SK baru). Kalau
 * sistem menyimpan satu berkas dan menimpanya, tidak ada bukti versi mana yang
 * dipakai saat asesmen. Karena itu setiap unggahan jadi baris sendiri, dan
 * yang dipakai = yang terbaru.
 */
import { esc, head, strip, badge, card, callout } from '../pagekit.js';

let data = null;
let galat = '';
let buka = {};        // kriteria yang sedang terbuka
let sedang = false;

const API = '/api/dokumen';

const STATUS_TXT = {
  belum: ['muted', 'belum ada'],
  ada: ['ok', 'ada'],
  kedaluwarsa: ['bad', 'kedaluwarsa'],
};

export async function muat() {
  try {
    const r = await fetch(API, { credentials: 'same-origin' });
    const j = await r.json();
    if (!r.ok) { galat = j.error || `HTTP ${r.status}`; data = null; }
    else { data = j; galat = ''; }
  } catch (e) {
    galat = 'Server tidak merespons.';
  }
  if (window.__paint) window.__paint();
}

const ukuran = (b) => b < 1024 ? b + ' B'
  : b < 1024 * 1024 ? (b / 1024).toFixed(0) + ' KB'
    : (b / 1024 / 1024).toFixed(1) + ' MB';

function tgl(s) {
  if (!s) return '—';
  const d = new Date(String(s).replace(' ', 'T'));
  return isNaN(d) ? esc(String(s))
    : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

/* ── satu butir ─────────────────────────────────────────────────── */
function butir(b, bolehUbah) {
  const [kind, txt] = STATUS_TXT[b.status] || ['muted', b.status];
  const ada = b.pakai;

  const aksi = !bolehUbah ? ''
    : `<label class="btn btn-ghost btn-sm dok-pilih">
         ${ada ? 'Ganti' : 'Unggah'}
         <input type="file" data-unggah="${b.id}" hidden
                accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png">
       </label>`;

  // Riwayat versi: yang terpakai ditandai. Versi lama tetap terlihat supaya
  // jelas dokumen apa yang dulu diserahkan.
  const versi = (b.versi || []).map((v, i) => `
    <div class="dok-versi">
      <span class="badge ${i === 0 ? 'ok' : 'muted'}">${i === 0 ? 'dipakai' : 'lama'}</span>
      <a class="lnk" href="${API}/unduh?id=${v.id}">${esc(v.nama_asli || 'berkas')}</a>
      <span class="muted">${ukuran(Number(v.ukuran || 0))} · ${tgl(v.diunggah_pada)}</span>
      ${v.berlaku_sampai ? `<span class="muted">berlaku s/d ${tgl(v.berlaku_sampai)}</span>` : ''}
      ${bolehUbah ? `<button class="lnk lnk-bad" data-hapus-dok="${v.id}"
        data-nama="${esc(v.nama_asli || 'berkas')}">hapus</button>` : ''}
    </div>`).join('');

  /* Nama butir panjangnya sampai 266 karakter dan isinya sering sama dengan
     rincian. Menampilkan seluruhnya membuat daftar tidak bisa dipindai: mata
     harus membaca kalimat panjang untuk tahu butir itu tentang apa. Kalimat
     pertama dijadikan judul, sisanya rincian kecil. */
  const penuh = String(b.nama || '').trim();
  const titik = penuh.search(/\.\s/);
  const judul = titik > 40 ? penuh.slice(0, titik + 1) : penuh;
  const sisa = judul.length < penuh.length ? penuh.slice(judul.length).trim() : '';
  const rincian = sisa && sisa !== String(b.rincian || '').trim() ? sisa : '';

  return `<div class="dok-butir" data-butir="${b.id}">
    <div class="dok-kepala">
      <div>
        <span class="dok-nomor">${esc(b.kriteria)}-${b.nomor}</span>
        ${badge(kind, txt)}
        ${b.wajib ? '' : '<span class="badge muted">tidak wajib</span>'}
      </div>
      <div class="dok-aksi">${aksi}</div>
    </div>
    <div class="dok-nama">${esc(judul)}</div>
    ${rincian ? `<div class="dok-rincian">${esc(rincian)}</div>` : ''}
    ${versi ? `<div class="dok-daftar-versi">${versi}</div>` : ''}
    <div class="dok-err" data-err="${b.id}" role="alert"></div>
  </div>`;
}

function page() {
  if (!data) {
    return head('Dokumen Pendukung', '', '')
      + (galat
        ? callout('bad', 'Tidak bisa dimuat', `<p>${esc(galat)}</p>`)
        : '<p class="muted">Memuat…</p>');
  }

  const { rekap, grup, boleh_ubah, batas } = data;

  const isiKriteria = (g) => (g.butir || []).map((b) => butir(b, boleh_ubah)).join('');

  /* Kunci grup adalah `kode` (dari dok_daftar), BUKAN `kriteria` — butir yang
     memakai `kriteria`. Memakai nama yang salah membuat semua grup punya kunci
     `undefined`, sehingga membuka satu grup membuka semuanya. */
  const kartu = (g) => {
    const kunci = g.kode || g.kriteria || '';
    const n = (g.butir || []).length;
    const terunggah = (g.butir || []).filter((b) => b.status === 'ada').length;
    const lewat = (g.butir || []).filter((b) => b.status === 'kedaluwarsa').length;
    const terbuka = !!buka[kunci];
    return `<div class="card dok-grup">
      <button class="dok-grup-kepala" data-buka="${esc(kunci)}">
        <span class="dok-grup-nama">${esc(kunci)} · ${esc(g.nama || '')}</span>
        <span class="dok-grup-angka">
          ${terunggah}/${n} ada
          ${lewat ? `<span class="badge bad">${lewat} kedaluwarsa</span>` : ''}
          <span class="dok-panah">${terbuka ? '▾' : '▸'}</span>
        </span>
      </button>
      ${terbuka ? `<div class="dok-isi">${isiKriteria(g)}</div>` : ''}
    </div>`;
  };

  return head('Dokumen Pendukung',
    `${rekap.total} butir · ${rekap.unggah} terunggah · ${rekap.belum} belum`
    + (rekap.kedaluwarsa ? ` · ${rekap.kedaluwarsa} kedaluwarsa` : ''))
    + strip([
      [`${rekap.total}`, 'butir dokumen', ''],
      [`${rekap.unggah}`, 'terunggah', rekap.unggah ? 'ok' : 'muted'],
      [`${rekap.belum}`, 'belum', rekap.belum ? 'warn' : 'muted'],
      [`${rekap.kedaluwarsa}`, 'kedaluwarsa', rekap.kedaluwarsa ? 'bad' : 'muted'],
    ])
    + (boleh_ubah ? '' : callout('warn', 'Hanya lihat',
      '<p>Peran Anda tidak boleh mengunggah dokumen.</p>'))
    + callout('info', 'Cara mengunggah',
      `<p>Klik <b>Unggah</b> pada butir yang dituju. Berkas maksimal `
      + `${ukuran(batas.byte)}, jenis: ${esc((batas.ekstensi || []).join(', '))}. `
      + `Menambah berkas baru tidak menghapus yang lama — versi lama tetap tersimpan `
      + `sebagai bukti, dan yang dipakai selalu yang terbaru.</p>`)
    + (grup || []).map(kartu).join('');
}

/* ── peristiwa ─────────────────────────────────────────────────── */
if (typeof document !== 'undefined' && !window.__dokPasang) {
  window.__dokPasang = true;

  document.addEventListener('click', async (e) => {
    const bukaBtn = e.target.closest('[data-buka]');
    if (bukaBtn) {
      const k = bukaBtn.dataset.buka;
      buka[k] = !buka[k];
      if (window.__paint) window.__paint();
      return;
    }

    const hapus = e.target.closest('[data-hapus-dok]');
    if (hapus && data) {
      const id = Number(hapus.dataset.hapusDok);
      if (!confirm(`Hapus berkas "${hapus.dataset.nama}"? Versi ini hilang dari riwayat.`)) return;
      hapus.disabled = true;
      try {
        const r = await fetch(API + '/hapus', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin', body: JSON.stringify({ id }),
        });
        const j = await r.json();
        if (!r.ok || j.error) { alert(j.error || 'Gagal menghapus.'); hapus.disabled = false; return; }
        await muat();
      } catch (_) { alert('Server tidak merespons.'); hapus.disabled = false; }
    }
  });

  // Unggah: satu perjalanan multipart. Berkas tidak dikirim base64 —
  // itu membengkakkan 33% dan memakan memori untuk berkas 20 MB.
  document.addEventListener('change', async (e) => {
    const inp = e.target.closest('[data-unggah]');
    if (!inp || !inp.files || !inp.files[0] || sedang) return;

    const idButir = inp.dataset.unggah;
    const err = document.querySelector(`[data-err="${idButir}"]`);
    const berkas = inp.files[0];

    if (data && berkas.size > data.batas.byte) {
      err.textContent = `Berkas ${ukuran(berkas.size)} melebihi batas `
        + `${ukuran(data.batas.byte)}.`;
      inp.value = '';
      return;
    }

    sedang = true;
    err.textContent = 'Mengunggah…';
    err.className = 'dok-err muted';

    const fd = new FormData();
    fd.append('dok_spec_id', idButir);
    fd.append('berkas', berkas);

    try {
      const r = await fetch(API + '/unggah', {
        method: 'POST', credentials: 'same-origin', body: fd,
      });
      const j = await r.json().catch(() => ({}));
      if (!r.ok || !j.ok) {
        err.className = 'dok-err';
        err.textContent = j.pesan || j.error || `Gagal mengunggah (HTTP ${r.status}).`;
        return;
      }
      // Muat ulang dengan butir yang sama tetap terbuka.
      const krit = (data.grup || []).find((g) => (g.butir || [])
        .some((b) => Number(b.id) === Number(idButir)));
      const kk = krit ? (krit.kode || krit.kriteria) : '';
      if (kk) buka[kk] = true;
      await muat();
    } catch (_) {
      err.className = 'dok-err';
      err.textContent = 'Server tidak merespons. Berkas belum terkirim.';
    } finally {
      sedang = false;
      inp.value = '';
    }
  });
}

export default page;
