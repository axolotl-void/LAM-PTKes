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


/* ═══ Tampilan Data Dukung ═══════════════════════════════════════════
 *
 * Tiga percobaan pertama gagal dengan cara yang sama: saya menambal kartu
 * seragam, bukan membangun hierarki. Hasilnya 38 kotak abu-abu setara bobot,
 * strip KPI 7 angka (12 dari 13 nol), lalu baris filter 6 angka — data yang
 * sama muncul dua kali dalam 120px, dan tidak ada satu pun titik masuk mata.
 *
 * Struktur yang dipakai sekarang:
 *   1. JUDUL + satu kalimat keadaan ("30 dari 38 tabel belum diisi").
 *   2. SATU baris status — bukan strip 7 sel: hanya angka yang tidak nol.
 *   3. Baris tabel per kriteria: satu baris per tabel, bukan kotak.
 *
 * Kenapa baris, bukan kartu: isi tiap tabel cuma 4 potongan informasi
 * (kode, judul, status, kemajuan). Empat potongan tidak butuh kotak — kotak
 * memaksa padding mengelilingi isi yang tipis, lalu 38 kotak terbaca sebagai
 * dinding. Baris dengan satu aksen warna status memberi pemindaian vertikal
 * yang jauh lebih cepat, dan judul panjang mendapat lebar penuh.
 */

const ST_TXT2 = {
  lengkap: 'Lengkap', sebagian: 'Sebagian', kosong: 'Kosong',
  terkunci: 'Terkunci', na: 'Tak berlaku', hitung: 'Otomatis',
};

/* Satu baris tabel. Struktur grid, bukan <table>: lebar kolom ditentukan
   sekali di kepala, dan judul boleh 2 baris tanpa merusak kolom lain. */
function barisTabel(t) {
  const [no, judul, kStatis, stStatis, ftStatis, fttStatis] = t;
  const lv = liveFor(no);
  const st = lv ? lv.status : stStatis;
  const ft = lv ? lv.field_terisi : ftStatis;
  const ftt = lv ? lv.field_total : fttStatis;
  const hanyaHitung = lv && lv.hanya_hitung;
  const matriks = lv && lv.matriks;
  const adaHitung = lv && lv.ada_hitung;

  const bisaIsi = st !== 'terkunci' && ft < ftt && boleh('Isi');

  // Aksi ditentukan bentuk tabel, bukan status saja.
  let tautan = `#/tabelisi/${no}`;
  let label = ft > 0 ? 'Lanjutkan' : 'Isi';
  let utama = true;
  if (matriks) { tautan = `#/tabelmatriks/${no}`; label = 'Lihat'; utama = false; }
  else if (hanyaHitung || (!bisaIsi && adaHitung)) {
    tautan = `#/tabelhitung/${no}`; label = 'Lihat'; utama = false;
  } else if (!bisaIsi) { label = 'Lihat'; utama = false; }

  // Kemajuan: angka + bilah tipis. Tanpa penyebut (0/0) -> "otomatis",
  // bukan bilah kosong yang terbaca "belum dikerjakan".
  const persen = ftt > 0 ? Math.round((ft / ftt) * 100) : null;
  // Satu pembungkus untuk angka+bilah: dua elemen terpisah membuat baris
  // punya 6 anak grid di grid 5 kolom, dan kolom aksi terdorong ke baris baru.
  // Kolom metrik mengosongkan diri untuk tabel otomatis: status di sebelah
  // kiri sudah menulis "Otomatis". Mengulanginya dalam huruf kecil membuat
  // satu baris berbunyi "Otomatis … otomatis" — dua kata, satu arti.
  const metrik = persen === null
    ? '<div class="br-metrik-wrap"><span class="br-metrik br-metrik-auto">—</span></div>'
    : `<div class="br-metrik-wrap">
         <span class="br-metrik"><b>${ft}</b>/${ftt}</span>
         <span class="br-bilah" aria-hidden="true"><i style="width:${persen}%"></i></span>
       </div>`;

  return `<div class="br" data-st="${esc(st)}">
    <span class="br-kode">T-${String(no).padStart(2, '0')}</span>
    <div class="br-utama">
      <a class="br-judul" href="${tautan}">${esc(judul)}</a>
    </div>
    <span class="br-status">${st === 'kosong' ? '' : esc(ST_TXT2[st] || st)}</span>
    ${metrik}
    <a class="br-aksi${utama ? '' : ' br-aksi-sekunder'}" href="${tautan}">${label}</a>
  </div>`;
}

function page(state) {
  const hitung = { lengkap: 0, sebagian: 0, kosong: 0, terkunci: 0, hitung: 0 };
  if (LIVE.siap) {
    LIVE.tabel.filter((t) => t.berlaku).forEach((t) => {
      hitung[t.status] = (hitung[t.status] || 0) + 1;
    });
  } else {
    TABEL_WAJIB.forEach((t) => { hitung[t[3]] = (hitung[t[3]] || 0) + 1; });
  }

  const total = TABEL_WAJIB.length;
  const beres = hitung.lengkap + hitung.terkunci;
  const perlu = total - beres - hitung.hitung;

  // Kalimat keadaan, bukan tabel angka. Satu baris ini menggantikan tujuh
  // sel strip: pembaca langsung tahu harus berbuat apa.
  const ringkas = perlu === 0
    ? (hitung.hitung === total
      ? 'Seluruh tabel dihitung otomatis dari master.'
      : 'Semua tabel yang perlu diisi sudah lengkap.')
    : `${perlu} dari ${total} tabel perlu diisi`
      + (hitung.hitung ? ` · ${hitung.hitung} dihitung otomatis` : '')
      + (beres ? ` · ${beres} selesai` : '');

  // Kelompokkan per kriteria.
  const KRIT_NAMA = Object.fromEntries(KRITERIA.map((k) => [k.kode, k.nama || k.kode]));
  const perKrit = {};
  TABEL_WAJIB.forEach((t) => {
    const lv = liveFor(t[0]);
    (perKrit[t[2]] = perKrit[t[2]] || []).push({ t, st: lv ? lv.status : t[3] });
  });

  const kriteria = Object.keys(perKrit).sort().map((kode) => {
    const isi = perKrit[kode];
    const selesai = isi.filter((x) => x.st === 'lengkap' || x.st === 'terkunci').length;
    const otomatis = isi.filter((x) => x.st === 'hitung').length;
    const sisa = isi.length - selesai - otomatis;
    return `<section class="br-krit-bagian" data-krit="${esc(kode)}">
      <header class="br-krit-kepala">
        <span class="br-krit-kode">${esc(kode)}</span>
        <h2>${esc(KRIT_NAMA[kode] || '')}</h2>
        <span class="br-krit-meta">${selesai}/${isi.length} selesai${sisa ? ` · ${sisa} perlu diisi` : ''}${otomatis ? ` · ${otomatis} otomatis` : ''}</span>
      </header>
      <div class="br-daftar">${isi.map((x) => barisTabel(x.t)).join('')}</div>
    </section>`;
  }).join('');

  return head('Data Dukung',
    `${total} tabel wajib · ${TABEL_NA.length} tidak berlaku untuk jenjang ini`)
    // Satu baris keadaan + filter dalam satu baris kendali.
    + `<div class="br-kendali">
        <p class="br-ringkas">${esc(ringkas)}</p>
        <div class="br-saring" role="group" aria-label="Saring menurut status">
          ${chipSaring('Semua', '', total, true)}
          ${hitung.lengkap ? chipSaring('Lengkap', 'ok', hitung.lengkap) : ''}
          ${hitung.sebagian ? chipSaring('Sebagian', 'warn', hitung.sebagian) : ''}
          ${hitung.kosong ? chipSaring('Kosong', '', hitung.kosong) : ''}
          ${hitung.hitung ? chipSaring('Otomatis', '', hitung.hitung) : ''}
          ${hitung.terkunci ? chipSaring('Terkunci', '', hitung.terkunci) : ''}
        </div>
      </div>`
    + kriteria
    // Tabel tidak berlaku: dilipat, bukan dipamerkan. Bukan pekerjaan operator.
    + (TABEL_NA.length ? `<details class="br-na">
        <summary>${TABEL_NA.length} tabel tidak berlaku untuk jenjang ini</summary>
        <div class="br-daftar">${TABEL_NA.map(([no, judul, k]) => `<div class="br br-na-baris">
          <span class="br-kode">T-${String(no).padStart(2, '0')}</span>
          <div class="br-utama"><span class="br-judul-static">${esc(judul)}</span>
            <span class="br-krit">${esc(k)}</span></div>
          <span class="br-status">Tak berlaku</span>
        </div>`).join('')}</div>
      </details>` : '');
}

/* Chip saring. Hanya status yang punya isi yang ditampilkan — chip "Lengkap 0"
   adalah tombol untuk menyaring ke nol hasil. */
function chipSaring(label, kind, n, aktif = false) {
  return `<button type="button" class="br-chip${kind ? ' ' + kind : ''}${aktif ? ' on' : ''}"
    data-saring="${esc(label.toLowerCase())}" aria-pressed="${aktif}">
    ${esc(label)}<b>${n}</b></button>`;
}


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
    const semua = document.querySelectorAll('.br-krit-bagian');
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
    document.querySelectorAll('.br[data-st]').forEach((k) => {
      const cocok = saring === 'semua' || k.dataset.st === kunci;
      k.hidden = !cocok;
      if (cocok) tampil++;
    });

    // Bagian kriteria yang seluruh kartunya tersaring disembunyikan —
    // judul kosong dengan "0 dari 7 selesai" di atasnya hanya membingungkan.
    semua.forEach((s) => {
      const ada = [...s.querySelectorAll('.br[data-st]')].some((k) => !k.hidden);
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

export default page;
