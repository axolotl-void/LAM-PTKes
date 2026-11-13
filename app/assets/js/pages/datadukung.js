/* datadukung — daftar 38 tabel data dukung: 32 wajib + 6 tidak berlaku.
 * Dipisah dari views.js agar bisa dikerjakan paralel. */
import { LIVE, api, boleh } from '../api.js';
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table, TABEL, TABEL_NA,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

// dipindah ke data.js saat backend ada.
// [nomor, judul, kode kriteria, status, fieldTerisi, fieldTotal, aksi]
// status: lengkap | sebagian | kosong | terkunci | na
// Distribusi 32 wajib: 25 lengkap + 1 terkunci + 2 sebagian + 4 kosong = 26 selesai.
// 6 NA: nomor 4, 6, 7, 8, 11, 30 (dari INSTRUMEN.tabelNA).
// Daftar tabel tinggal di data.js — halaman ini tidak mendefinisikan ulang.
const AKSI = { lengkap: 'Buka', terkunci: 'Lihat', sebagian: 'Isi', kosong: 'Isi' };
export const TABEL_WAJIB = TABEL.map((t) => [
  t.no, t.judul, t.krit, t.status, t.terisi, t.total, AKSI[t.status] || 'Buka',
]);


/* assert: jumlah tabel per kriteria harus sama dengan KRITERIA[i].tabel[1].
   Kalau tidak cocok, halaman tidak dirender — angka bohong lebih burus
   daripada crash. */
{
  const hitung = {};
  TABEL_WAJIB.forEach(([, , k]) => { hitung[k] = (hitung[k] || 0) + 1; });
  KRITERIA.forEach((k) => {
    const lokal = hitung[k.kode] || 0;
    if (lokal !== k.tabel[1]) {
      throw new Error(`datadukung: ${k.kode} punya ${lokal} tabel lokal, KRITERIA minta ${k.tabel[1]}`);
    }
  });
  if (TABEL_WAJIB.length !== D.tabelWajib) {
    throw new Error(`datadukung: ${TABEL_WAJIB.length} tabel wajib, D.tabelWajib=${D.tabelWajib}`);
  }
  if (TABEL_WAJIB.length + TABEL_NA.length !== INSTRUMEN.totalTabel) {
    throw new Error(`datadukung: ${TABEL_WAJIB.length}+${TABEL_NA.length} ≠ ${INSTRUMEN.totalTabel}`);
  }
  // jumlah tabel "selesai" (lengkap + terkunci) harus sama dengan D.tabelLengkap,
  // dan per kriteria harus sama dengan KRITERIA[i].tabel[0]. Tanpa ini, halaman
  // bisa bilang 25 selesai sementara dashboard bilang 26.
  const SELESAI = new Set(['lengkap', 'terkunci']);
  const selesaiPer = {};
  TABEL_WAJIB.forEach(([, , k, st]) => {
    if (SELESAI.has(st)) selesaiPer[k] = (selesaiPer[k] || 0) + 1;
  });
  KRITERIA.forEach((k) => {
    const lokal = selesaiPer[k.kode] || 0;
    if (lokal !== k.tabel[0]) {
      throw new Error(`datadukung: ${k.kode} punya ${lokal} tabel selesai, KRITERIA minta ${k.tabel[0]}`);
    }
  });
  const totalSelesai = TABEL_WAJIB.filter((r) => SELESAI.has(r[3])).length;
  if (totalSelesai !== D.tabelLengkap) {
    throw new Error(`datadukung: ${totalSelesai} tabel selesai, D.tabelLengkap=${D.tabelLengkap}`);
  }
}

export const ST_KIND = {
  lengkap: 'ok', sebagian: 'warn', kosong: 'muted',
  terkunci: 'lock', na: 'na',
  // 'hitung' dulu 'lock' -> kartu "Otomatis" bertepi ungu, sama dengan
  // "Terkunci". Dua arti berbeda tidak boleh satu warna.
  hitung: 'hitung',
};
const ST_TXT = {
  lengkap: 'Lengkap', sebagian: 'Sebagian', kosong: 'Kosong',
  terkunci: 'Terkunci', na: 'Tak berlaku',
  hitung: 'Otomatis',
};

function chip(label, kind, n) {
  return `<span class="chip ${kind}">${esc(label)} <b>${n}</b></span>`;
}

/* Chip yang bisa diklik. `aktif` menandai pilihan sekarang supaya orang tahu
   daftar sedang tersaring — tanpa penanda, kartu yang hilang terasa seperti
   data yang hilang. */
function chipAktif(label, kind, n, aktif = false) {
  return `<button type="button" class="chip ${kind}${aktif ? ' on' : ''}"
    data-saring="${esc(label.toLowerCase())}" aria-pressed="${aktif}">
    ${esc(label)} <b>${n}</b></button>`;
}

/* Angka DB menang atas konstanta. Kalau LIVE null (server mati), tetap
   memakai data dari TABEL_WAJIB supaya halaman tidak kosong. */
function liveFor(no) {
  if (!LIVE.siap) return null;
  // Normalisasi WAJIB: kode dari server ber-padding ("01"), nomor dari
  // TABEL_WAJIB tidak ("1"). Tanpa ini perbandingan selalu gagal dan setiap
  // baris diam-diam memakai data statis — kartu bilang 0 lengkap, baris
  // bilang Lengkap 6/6, di layar yang sama.
  const kunci = String(no).padStart(2, '0');
  return LIVE.tabel.find((t) => String(t.kode).padStart(2, '0') === kunci) || null;
}

function rowWajib(t) {
  const [no, judul, k, stStatis, ftStatis, fttStatis] = t;
  const lv = liveFor(no);
  const st = lv ? lv.status : stStatis;
  const ft = lv ? lv.field_terisi : ftStatis;
  const ftt = lv ? lv.field_total : fttStatis;

  const fieldCls = ft < ftt && ft > 0 ? ' style="color:var(--warn)"' : '';
  const fieldTxt = ft === 0 ? `<span class="muted">0/${ftt}</span>` : `${ft}/${ftt}`;

  // Aksi: tombol hanya dirender kalau peran ini memang boleh. Tombol yang
  // selalu ditolak server lebih buruk daripada tombol yang tidak ada.
  const bisaIsi = st !== 'terkunci' && ft < ftt && boleh('Isi');
  const kunciBtn = (boleh('Verifikasi') && ft >= ftt && st !== 'terkunci')
    ? `<button class="lnk dd-kunci" data-kunci="${no}">Kunci →</button>` : '';
  const bukaBtn = (boleh('Buka kunci') && st === 'terkunci')
    ? `<button class="lnk dd-buka" data-buka="${no}">Buka kunci →</button>` : '';
  // Tabel berstatus hitung tidak punya kolom isian sama sekali; tombol "Isi"
  // di situ hanya mengecewakan. Arahkan ke halaman tabel supaya bisa dilihat.
  // Tabel matriks punya halamannya sendiri: bentuknya bukan daftar baris
  // yang bisa ditambah, jadi "Isi" di sini akan menyesatkan.
  const matriks = lv && lv.matriks;
  const adaHitung = lv && lv.ada_hitung;
  const hanyaHitung = lv && lv.hanya_hitung;

  // Tabel berkolom Hitung punya halaman hitungan sendiri. Kalau punya kolom
  // isian juga (mis. Tabel 10: daya tampung manual + sisanya hitung), yang
  // ditawarkan "Isi" karena itu pekerjaan operator; angkanya tetap tampil
  // di halaman isian.
  const aksi = matriks
    ? `<a class="lnk" href="#/tabelmatriks/${no}">Lihat →</a>`
    : st === 'terkunci'
      ? `<span class="lnk muted">Terkunci</span>${bukaBtn}`
      : (bisaIsi && !hanyaHitung)
        ? `<a class="lnk dd-isi" href="#/tabelisi/${no}">Isi →</a>`
        : adaHitung
          ? `<a class="lnk" href="#/tabelhitung/${no}">Lihat →</a>`
          : `<a class="lnk" href="#/tabelisi/${no}">Lihat →</a>`;
  const aksiFinal = `${aksi}${kunciBtn}`;


  return `<tr>
    <td class="num strong">T-${no.padStart(2, '0')}</td>
    <td>${esc(judul)}</td>
    <td class="num"><span class="kc-kode">${k}</span></td>
    <td>${badge(ST_KIND[st], ST_TXT[st])}</td>
    <td class="r num"${fieldCls}>${fieldTxt}</td>
    <td class="r">${aksiFinal}</td>
  </tr>`;
}

/* Satu tabel = satu kartu. Alasan bentuk kartu, bukan baris tabel:
 *  - judul tabel panjang (sampai 90 karakter) terpotong di kolom tabel,
 *  - 38 baris = 1845px gulir tanpa titik pandang,
 *  - tiap tabel punya satu aksi utama + satu metrik; di kartu keduanya
 *    bisa diberi bobot visual yang benar, di baris tabel semuanya rata.
 */
function kartuTabel(t) {
  const [no, judul, kStatis, stStatis, ftStatis, fttStatis] = t;
  const lv = liveFor(no);
  const st = lv ? lv.status : stStatis;
  const ft = lv ? lv.field_terisi : ftStatis;
  const ftt = lv ? lv.field_total : fttStatis;

  const bisaIsi = st !== 'terkunci' && ft < ftt && boleh('Isi');
  const hanyaHitung = lv && lv.hanya_hitung;
  const matriks = lv && lv.matriks;
  const adaHitung = lv && lv.ada_hitung;

  // Aksi utama ditentukan oleh bentuk tabelnya, bukan oleh status saja:
  // tabel matriks/hitungan tidak punya baris yang bisa ditambah.
  let tautan = `#/tabelisi/${no}`;
  let label = ft > 0 ? 'Lanjutkan' : 'Isi';
  if (matriks) { tautan = `#/tabelmatriks/${no}`; label = 'Lihat'; }
  else if (hanyaHitung || (!bisaIsi && adaHitung)) { tautan = `#/tabelhitung/${no}`; label = 'Lihat'; }
  else if (!bisaIsi) { label = 'Lihat'; }

  // Bilah kemajuan hanya kalau ada penyebut. 0/0 = tabel otomatis; bilah
  // kosong di situ terbaca "belum dikerjakan" padahal tidak ada yang dikerjakan.
  const persen = ftt > 0 ? Math.round((ft / ftt) * 100) : null;
  const bilah = persen === null
    ? '<div class="kt-bilah kt-bilah-auto" title="tidak ada kolom isian manual"></div>'
    : `<div class="kt-bilah" title="${ft} dari ${ftt} kolom terisi">
         <i style="width:${persen}%"></i></div>`;

  const metrik = ftt > 0
    ? `<span class="kt-metrik">${ft}<span>/${ftt} kolom</span></span>`
    : '<span class="kt-metrik kt-metrik-auto">otomatis</span>';

  // data-st dipakai filter chip; tanpa ini chip jadi hiasan dan 38 kartu
  // harus dipindai satu per satu untuk mencari yang masih kosong.
  return `<article class="kt kt-${ST_KIND[st] || 'muted'}" data-st="${esc(st)}">
    <div class="kt-kepala">
      <span class="kt-kode">T-${String(no).padStart(2, '0')}</span>
      ${badge(ST_KIND[st], ST_TXT[st])}
    </div>
    <h3 class="kt-judul">${esc(judul)}</h3>
    ${bilah}
    <div class="kt-kaki">
      ${metrik}
      <a class="kt-aksi${label === 'Lihat' ? ' kt-aksi-sekunder' : ''}"
         href="${tautan}">${label} →</a>
    </div>
  </article>`;
}

function rowNA(t) {
  const [no, judul, k] = t;
  return `<tr class="na-row">
    <td class="num muted">T-${no.padStart(2, '0')}</td>
    <td class="muted">${esc(judul)}</td>
    <td class="num"><span class="kc-kode">${k}</span></td>
    <td>${badge('na', 'Tidak berlaku untuk jenjang ini')}</td>
    <td class="r muted">—</td>
    <td class="r muted">—</td>
  </tr>`;
}

export const css = `
.dd-filter { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 16px; }
.dd-filter .chip { display:inline-flex; align-items:center; gap:6px;
  padding:6px 12px; border:1px solid var(--bd,#E4E7EC); border-radius:999px;
  font-size:var(--fs-2); cursor:pointer; background:#fff; color:var(--ink3);
  transition:border-color .15s, color .15s; }
.dd-filter .chip:hover { border-color:var(--ind,#2F2A95); color:var(--ind,#2F2A95); }
.dd-filter .chip b { font-weight:700; color:var(--ink,#1A1A2E); }
.dd-filter .chip.ok b { color:var(--ok,#2E9E5B); }
.dd-filter .chip.warn b { color:var(--warn,#FF8200); }
.dd-filter .chip.muted b { color:var(--ink4,#8A8FA3); }
.dd-filter .chip.lock b { color:var(--ink3,#5A5F73); }
.dd-filter .chip.na b { color:var(--ink4,#8A8FA3); }
.tbl .na-row td { background:#F8F9FB; }
.tbl .na-row td:first-child { border-left:3px solid var(--bd,#E4E7EC); }
.dd-na-note { margin-top:12px; }
.dd-section-head { margin:24px 0 12px; display:flex; align-items:baseline; gap:10px; }
.dd-section-head h3 { margin:0; font-size:var(--fs-3); }
.dd-section-head .n { color:var(--ink3); font-size:var(--fs-2); }

/* tombol Isi: terlihat seperti link, tapi bisa diklik dan mengubah data */
.dd-isi { background:none; border:0; padding:0; cursor:pointer; font:inherit;
  color:var(--ind); font-weight:600; }
.dd-isi:hover { text-decoration:underline; }
.dd-isi[disabled] { color:var(--ink3); cursor:default; text-decoration:none; }
`;

function page(state) {
  const hitung = { lengkap: 0, sebagian: 0, kosong: 0, terkunci: 0 };
  if (LIVE.siap) {
    // 38 baris di DB sudah memuat status turunan; saring hanya yang berlaku.
    LIVE.tabel.filter((t) => t.berlaku).forEach((t) => {
      hitung[t.status] = (hitung[t.status] || 0) + 1;
    });
  } else {
    TABEL_WAJIB.forEach((t) => { hitung[t[3]] = (hitung[t[3]] || 0) + 1; });
  }
  const belum = TABEL_WAJIB.length - hitung.lengkap - hitung.terkunci;

  const rowsNA = TABEL_NA.map(rowNA).join('');

  // Kelompokkan per kriteria. 38 kartu datar masih melelahkan; 8 bagian
  // memberi tempat berhenti mata, dan tiap bagian punya angka sisa sendiri
  // sehingga orang tahu kriteria mana yang paling tertinggal.
  const KRIT_NAMA = Object.fromEntries(KRITERIA.map((k) => [k.kode, k.nama || k.kode]));
  const perKrit = {};
  TABEL_WAJIB.forEach((t) => {
    const lv = liveFor(t[0]);
    const kode = t[2];
    (perKrit[kode] = perKrit[kode] || []).push({ t, st: lv ? lv.status : t[3] });
  });

  const bagianKrit = Object.keys(perKrit).sort().map((kode) => {
    const isi = perKrit[kode];
    const selesai = isi.filter((x) => x.st === 'lengkap' || x.st === 'terkunci').length;
    const otomatis = isi.filter((x) => x.st === 'hitung').length;
    const sisa = isi.length - selesai - otomatis;
    return `<section class="kt-bagian">
      <div class="kt-bagian-kepala">
        <h2><span class="kt-krit">${esc(kode)}</span> ${esc(KRIT_NAMA[kode] || '')}</h2>
        <span class="note">${selesai} dari ${isi.length} selesai${otomatis ? ` · ${otomatis} otomatis` : ''}${sisa ? ` · ${sisa} perlu diisi` : ''}</span>
      </div>
      <div class="kt-grid">${isi.map((x) => kartuTabel(x.t)).join('')}</div>
    </section>`;
  }).join('');

  const tblHead = `<tr>
    <th>Tabel</th><th>Judul</th><th>Kriteria</th><th>Status</th>
    <th class="r">Field terisi</th><th class="r">Aksi</th>
  </tr>`;

  return head('Data Dukung 38 Tabel',
    `${INSTRUMEN.totalTabel} total · ${D.tabelWajib} wajib · ${TABEL_NA.length} tidak berlaku · data per ${DATA_PER}`)
  // ringkasan strip
  + strip([
    [`${INSTRUMEN.totalTabel}`, 'tabel total', ''],
    [`${D.tabelWajib}`, 'wajib diisi', 'ok'],
    [`${TABEL_NA.length}`, 'tidak berlaku', 'muted'],
    [`${hitung.lengkap}`, 'lengkap', 'ok'],
    [`${hitung.sebagian}`, 'sebagian', 'warn'],
    [`${hitung.kosong}`, 'kosong', 'bad'],
    [`${hitung.terkunci}`, 'terkunci', 'lock'],
  ])
  // filter chip — BERFUNGSI. Sebelumnya hanya hiasan: 38 kartu tanpa cara
  // menyaring, padahal pekerjaan operator justru "mana yang masih kosong".
  + `<div class="dd-filter" role="group" aria-label="Saring tabel menurut status">
      ${chipAktif('Semua', 'semua', TABEL_WAJIB.length, true)}
      ${chipAktif('Lengkap', 'ok', hitung.lengkap)}
      ${chipAktif('Sebagian', 'warn', hitung.sebagian)}
      ${chipAktif('Kosong', 'muted', hitung.kosong)}
      ${chipAktif('Otomatis', 'lock', hitung.hitung || (LIVE.siap
        ? LIVE.tabel.filter((t) => t.status === 'hitung').length : 0))}
      ${chipAktif('Terkunci', 'lock', hitung.terkunci)}
    </div>`
  // tabel wajib — bentuk kartu per kriteria
  + `<div class="dd-section-head"><h3>Tabel wajib</h3>
      <span class="n">${TABEL_WAJIB.length} tabel · ${belum} belum lengkap</span></div>`
  + bagianKrit
  // tabel tidak berlaku
  + `<div class="dd-section-head"><h3>Tidak berlaku untuk jenjang ini</h3>
      <span class="n">${TABEL_NA.length} tabel — khusus jenjang dengan tahap profesi</span></div>`
  + `<div class="card">
      <div style="overflow-x:auto">
      <table class="tbl">
        <thead>${tblHead}</thead>
        <tbody>${rowsNA}</tbody>
      </table>
      </div>
    </div>`
  + `<p class="foot-note dd-na-note">Enam tabel ini hanya wajib untuk jenjang
      dengan tahap profesi (Sarjana dan Profesi, Profesi, Spesialis).
      Tabel ditampilkan untuk transparansi, tidak diisi untuk jenjang ini.</p>`;
}

export default page;

/* ── filter status (delegasi, dipasang sekali) ───────────────────────
 * Kartu disaring di DOM, bukan render ulang: render ulang akan memanggil
 * API lagi dan kehilangan posisi gulir saat orang sedang membandingkan.
 */
if (typeof document !== 'undefined' && !window.__ddSaring) {
  window.__ddSaring = true;

  document.addEventListener('click', (e) => {
    const tombol = e.target.closest('[data-saring]');
    if (!tombol) return;

    const saring = tombol.dataset.saring;
    const semua = document.querySelectorAll('.kt-bagian');
    if (!semua.length) return;

    // Penanda pilihan
    document.querySelectorAll('[data-saring]').forEach((b) => {
      const on = b === tombol;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', String(on));
    });

    // Label chip berbahasa Indonesia, data-st memakai kunci internal.
    // Tanpa peta ini "Otomatis" tidak pernah cocok dengan data-st="hitung".
    const PETA = { otomatis: 'hitung', 'tak berlaku': 'na' };
    const kunci = PETA[saring] || saring;

    let tampil = 0;
    document.querySelectorAll('.kt').forEach((k) => {
      const cocok = saring === 'semua' || k.dataset.st === kunci;
      k.hidden = !cocok;
      if (cocok) tampil++;
    });

    // Bagian kriteria yang seluruh kartunya tersaring disembunyikan —
    // judul kosong dengan "0 dari 7 selesai" di atasnya hanya membingungkan.
    semua.forEach((s) => {
      const ada = [...s.querySelectorAll('.kt')].some((k) => !k.hidden);
      s.hidden = !ada;
    });

    // Katakan terus terang kalau tidak ada yang cocok.
    let kosong = document.getElementById('ddKosong');
    if (tampil === 0) {
      if (!kosong) {
        const wadah = document.querySelector('.dd-filter')?.parentElement;
        wadah?.insertAdjacentHTML('beforeend',
          '<div class="callout info" id="ddKosong"><p>Tidak ada tabel dengan status ini.</p></div>');
      }
    } else {
      kosong?.remove();
    }
  });
}
