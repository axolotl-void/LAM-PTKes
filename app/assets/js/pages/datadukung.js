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

/* ═══ Kartu tabel ════════════════════════════════════════════════════
 *
 * Percobaan sebelumnya gagal karena dua hal, dan keduanya struktural:
 *   1. Semua kartu bobotnya sama. 30 kartu "kosong" dan 8 kartu "otomatis"
 *      tampil identik, jadi 38 kotak abu-abu tanpa titik masuk.
 *   2. Aksi ("Isi"/"Lihat") diperlakukan sebagai teks di pojok, padahal itu
 *      satu-satunya hal yang bisa diklik orang. Di layar lebar ia jadi pulau
 *      sendiri jauh dari judulnya.
 *
 * Struktur yang dipakai sekarang:
 *   - Kartu hanya untuk tabel yang MEMANG perlu dikerjakan (belum lengkap).
 *     Tabel yang dihitung otomatis dipisah ke daftar ringkas — bukan
 *     pekerjaan operator, jadi tidak boleh memakan bobot visual yang sama.
 *   - Aksi jadi tombol penuh selebar kartu di kakinya: tujuan klik jelas,
 *     dan tidak lagi terpisah dari judulnya.
 *   - Kemajuan jadi deret titik per kolom isian, bukan angka "0/3" yang
 *     harus diterjemahkan. Titik terbaca sebagai "berapa yang sudah".
 */

/* Deret titik: satu titik per kolom isian. 0/3 -> ○○○ */
function titikKemajuan(ft, ftt) {
  if (ftt <= 0) return '';
  if (ftt > 12) {
    // Tabel berkolom banyak: titik jadi tak terbaca, kembali ke angka.
    return `<span class="kr-angka">${ft}<span>/${ftt}</span></span>`;
  }
  let out = '';
  for (let i = 0; i < ftt; i++) {
    out += `<i class="${i < ft ? 'on' : ''}"></i>`;
  }
  return `<span class="kr-titik" aria-hidden="true"
            title="${ft} dari ${ftt} kolom sudah terisi">${out}</span>
          <span class="kr-angka-sr">${ft} dari ${ftt} kolom terisi</span>`;
}

/* Kartu untuk tabel yang perlu diisi. */
function kartuTabel(t) {
  const [no, judul, kStatis, stStatis, ftStatis, fttStatis] = t;
  const lv = liveFor(no);
  const st = lv ? lv.status : stStatis;
  const ft = lv ? lv.field_terisi : ftStatis;
  const ftt = lv ? lv.field_total : fttStatis;
  const matriks = lv && lv.matriks;

  const tautan = matriks ? `#/tabelmatriks/${no}` : `#/tabelisi/${no}`;
  const mulai = ft === 0;
  let label = mulai ? 'Mulai isi' : 'Lanjutkan';
  const persen = ftt > 0 ? Math.round((ft / ftt) * 100) : 0;

  // Kunci hanya untuk tabel yang sudah lengkap dan peran ini berhak
  // memverifikasi. Tombol yang selalu ditolak server lebih buruk daripada
  // tombol yang tidak dirender.
  // "Lengkap" = semua kolom isian terisi. Untuk tabel ber-.no saja (1 kolom
  // "No" yang otomatis) ft/ftt bisa 0/1 padahal isinya sudah ada, jadi
  // kelengkapan harus diambil dari status server, bukan hanya rasio.
  const penuh = ftt > 0 && ft >= ftt;
  if (st === 'terkunci') {
    label = 'Buka kunci';
  } else if (penuh && boleh('Verifikasi')) {
    label = 'Kunci';
  }

  // Setelah terkunci, mengisi tidak mungkin — arahkan ke buka kunci.
  const aksiKunci = st === 'terkunci' ? ' data-buka-kunci="1"' : '';
  const tautanAksi = st === 'terkunci' && boleh('Buka kunci')
    ? '#'
    : tautan;

  return `<article class="kr kr-${esc(st)}" data-st="${esc(st)}">
    <header class="kr-kepala">
      <span class="kr-kode">T-${String(no).padStart(2, '0')}</span>
      ${st === 'sebagian' ? '<span class="kr-tanda">Sedang diisi</span>' : ''}
    </header>
    <h3 class="kr-judul">${esc(judul)}</h3>
    <div class="kr-kemajuan">
      ${titikKemajuan(ft, ftt)}
      ${persen > 0 ? `<span class="kr-persen">${persen}%</span>` : ''}
    </div>
    <div class="kr-bilah" aria-hidden="true"><i style="width:${persen}%"></i></div>
    <a class="kr-aksi" href="${tautanAksi}"${st === 'terkunci' && boleh('Buka kunci')
      ? ` data-kunci-buka="${no}"` : ''}${st === 'lengkap' && penuh && boleh('Verifikasi')
      ? ` data-kunci-tabel="${no}"` : ''}>${label}</a>
  </article>`;
}

/* Baris ringkas untuk tabel yang dihitung sistem — bukan pekerjaan operator,
   jadi tidak layak dapat kartu selebar tabel manual. */
function barisOtomatis(t) {
  const [no, judul, , stStatis] = t;
  const lv = liveFor(no);
  const st = lv ? lv.status : stStatis;
  const hanyaHitung = lv && lv.hanya_hitung;
  const matriks = lv && lv.matriks;
  const tautan = matriks ? `#/tabelmatriks/${no}`
    : hanyaHitung ? `#/tabelhitung/${no}` : `#/tabelisi/${no}`;
  const ket = st === 'terkunci' ? 'terkunci'
    : st === 'lengkap' ? 'sudah terisi'
      : 'dihitung sistem';

  return `<a class="or" href="${tautan}">
    <span class="or-kode">T-${String(no).padStart(2, '0')}</span>
    <span class="or-judul">${esc(judul)}</span>
    <span class="or-ket">${ket} →</span>
  </a>`;
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

  const ringkas = perlu === 0
    ? (hitung.hitung === total
      ? 'Seluruh tabel dihitung otomatis dari master.'
      : 'Semua tabel yang perlu diisi sudah lengkap.')
    : `${perlu} dari ${total} tabel perlu diisi`
      + (hitung.hitung ? ` · ${hitung.hitung} dihitung otomatis` : '');

  const KRIT_NAMA = Object.fromEntries(KRITERIA.map((k) => [k.kode, k.nama || k.kode]));
  const perKrit = {};
  TABEL_WAJIB.forEach((t) => {
    const lv = liveFor(t[0]);
    const st = lv ? lv.status : t[3];
    (perKrit[t[2]] = perKrit[t[2]] || []).push({ t, st });
  });

  const kriteria = Object.keys(perKrit).sort().map((kode) => {
    const isi = perKrit[kode];
    const selesai = isi.filter((x) => x.st === 'lengkap' || x.st === 'terkunci').length;
    const otomatis = isi.filter((x) => x.st === 'hitung').length;
    const sisa = isi.length - selesai - otomatis;

    // Pekerjaan operator dipisah dari yang dihitung sistem. Kartu untuk yang
    // perlu dikerjakan; daftar ringkas untuk yang tidak.
    const manual = isi.filter((x) => x.st !== 'hitung');
    const auto = isi.filter((x) => x.st === 'hitung');

    return `<section class="kr-bagian" data-krit="${esc(kode)}">
      <header class="kr-bagian-kepala">
        <span class="kr-krit-kode">${esc(kode)}</span>
        <h2>${esc(KRIT_NAMA[kode] || '')}</h2>
        <span class="kr-bagian-meta">${selesai}/${isi.length} selesai${
          sisa ? ` · <b>${sisa} perlu diisi</b>` : ' · lengkap'}</span>
      </header>
      ${manual.length ? `<div class="kr-grid">${manual.map((x) => kartuTabel(x.t)).join('')}</div>` : ''}
      ${auto.length ? `<div class="kr-auto">
        <div class="kr-auto-lab">Dihitung sistem — tidak perlu diisi</div>
        ${auto.map((x) => barisOtomatis(x.t)).join('')}
      </div>` : ''}
    </section>`;
  }).join('');

  return head('Data Dukung',
    `${total} tabel wajib · ${TABEL_NA.length} tidak berlaku untuk jenjang ini`)
    + `<div class="kr-kendali">
        <p class="kr-ringkas">${esc(ringkas)}</p>
        <div class="kr-saring" role="group" aria-label="Saring menurut status">
          ${chipSaring('Semua', total, true)}
          ${hitung.kosong ? chipSaring('Belum diisi', hitung.kosong) : ''}
          ${hitung.sebagian ? chipSaring('Sedang diisi', hitung.sebagian) : ''}
          ${hitung.lengkap ? chipSaring('Lengkap', hitung.lengkap) : ''}
          ${hitung.terkunci ? chipSaring('Terkunci', hitung.terkunci) : ''}
          ${hitung.hitung ? chipSaring('Otomatis', hitung.hitung) : ''}
          ${TABEL_NA.length ? chipSaring('Tak berlaku', TABEL_NA.length) : ''}
        </div>
      </div>`
    + kriteria
    + (TABEL_NA.length ? `<details class="kr-na">
        <summary>${TABEL_NA.length} tabel tidak berlaku untuk jenjang ini</summary>
        <div class="kr-auto">${TABEL_NA.map(([no, judul]) => `<span class="or or-na">
          <span class="or-kode">T-${String(no).padStart(2, '0')}</span>
          <span class="or-judul">${esc(judul)}</span>
          <span class="or-ket">tidak berlaku</span>
        </span>`).join('')}</div>
      </details>` : '');
}

function chipSaring(label, n, aktif = false) {
  return `<button type="button" class="kr-chip${aktif ? ' on' : ''}"
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
    const semua = document.querySelectorAll('.kr-bagian');
    if (!semua.length) return;

    // Penanda pilihan
    document.querySelectorAll('[data-saring]').forEach((b) => {
      const on = b === tombol;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', String(on));
    });

    // Label chip berbahasa Indonesia, data-st memakai kunci internal.
    // Tanpa peta ini "Otomatis" tidak pernah cocok dengan data-st="hitung".
    const PETA = { otomatis: 'hitung', 'belum diisi': 'kosong', 'sedang diisi': 'sebagian' };
    const kunci = PETA[saring] || saring;

    let tampil = 0;
    document.querySelectorAll('.kr[data-st]').forEach((k) => {
      const cocok = saring === 'semua' || k.dataset.st === kunci;
      k.hidden = !cocok;
      if (cocok) tampil++;
    });

    // Baris otomatis juga disaring. Tanpa ini, memilih "Otomatis" menyembunyikan
    // SEMUA kartu tapi 8 baris otomatis tetap tampil — hasilnya 0 kartu di
    // layar kosong padahal chip bilang 8.
    const autoTampil = ['semua', 'otomatis'].includes(saring);
    document.querySelectorAll('.or').forEach((o) => { o.hidden = !autoTampil; });
    document.querySelectorAll('.kr-auto').forEach((w) => {
      w.hidden = !autoTampil || ![...w.querySelectorAll('.or')].some((o) => !o.hidden);
    });
    // Label "DIHITUNG SISTEM" ikut disembunyikan bersama isinya.
    document.querySelectorAll('.kr-auto-lab').forEach((l) => {
      l.hidden = !autoTampil;
    });
    if (autoTampil) tampil += document.querySelectorAll('.or').length;

    // Bagian kriteria yang seluruh kartunya tersaring disembunyikan —
    // judul kosong dengan "0 dari 7 selesai" di atasnya hanya membingungkan.
    semua.forEach((s) => {
      const ada = [...s.querySelectorAll('.kr[data-st], .or')].some((k) => !k.hidden);
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
