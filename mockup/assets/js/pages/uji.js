/* uji — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

// dipindah ke data.js saat backend ada
// peserta uji kompetensi = lulusan yang menempuh OSCE.
// angka lulus angkatan dari ANGKATAN[a[3]]; TS-1 & TS belum diuji.
const UJI_ANGKATAN = [
  // [angkatan, tahun, peserta, lulusUji]
  ['TS-6', '2019/2020', 55, 53],
  ['TS-5', '2020/2021', 54, 51],
  ['TS-4', '2021/2022', 58, 55],
  ['TS-3', '2022/2023', 48, 44],
  ['TS-2', '2023/2024', 41, 37],
];

const TOTAL_PESERTA = UJI_ANGKATAN.reduce((s, r) => s + r[2], 0);
const TOTAL_LULUS_UJI = UJI_ANGKATAN.reduce((s, r) => s + r[3], 0);
const TOTAL_BELUM = TOTAL_PESERTA - TOTAL_LULUS_UJI;
const PERSEN_LULUS = (TOTAL_LULUS_UJI / TOTAL_PESERTA) * 100;

// dipindah ke data.js saat backend ada
// stasiun OSCE: [nama, kompetensi, persenLulus, pesertaStasiun]
const STASIUN = [
  ['Pemeriksaan antenatal', 'Leopold & DJJ', 88, 256],
  ['Asuhan persalinan normal', 'Patrun kala II', 94, 256],
  ['Asuhan bayi baru lahir', 'Resusitasi neonatus', 91, 256],
  ['Gawat darurat obstetri', 'Manejemen perdarahan postpartum', 90, 256],
  ['Asuhan nifas dan KB', 'Konseling KB pascapersalinan', 96, 256],
  ['Komunitas & deteksi dini', 'SKD risiko tinggi', 93, 256],
  ['Etikolegal & komunikasi', 'Informed consent', 95, 256],
];

const STASIUN_MIN = STASIUN.reduce((a, b) => (b[2] < a[2] ? b : a));

export const css = `
/* uji — sorot stasiun terburuk, bedakan ukuran */
.stasiun-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.stasiun-card{background:#fff;border:1px solid var(--ink6);border-radius:8px;padding:14px 16px;display:flex;flex-direction:column;gap:6px}
.stasiun-card.is-worst{border-color:var(--accent);border-width:2px;box-shadow:0 2px 12px rgba(255,130,0,.18);grid-column:span 2;order:-1}
.stasiun-card.is-worst .s-nama{font-size:var(--fs-lg);font-weight:700}
.stasiun-card .s-nama{font-weight:600;color:var(--ink1)}
.stasiun-card .s-komp{color:var(--ink3);font-size:var(--fs-sm)}
.stasiun-card .s-pct{font-size:var(--fs-xl);font-weight:700;line-height:1;margin-top:4px}
.stasiun-card .s-pers{color:var(--ink4);font-size:var(--fs-xs)}
@media(max-width:640px){.stasiun-card.is-worst{grid-column:span 1}}
`;

function page(state) {
  const ujiRows = UJI_ANGKATAN.map(([ang, th, pes, lulus]) => {
    const belum = pes - lulus;
    const pct = (lulus / pes) * 100;
    const cls = pct < 92 ? 'style="color:var(--bad)"' : 'style="color:var(--ok)"';
    return {
      cells: [
        `<span class="strong">${ang}</span>`,
        `<span class="muted">${th}</span>`,
        `<span class="num">${pes}</span> orang`,
        `<span class="num" style="color:var(--ok)">${lulus}</span> orang`,
        `<span class="num" style="color:var(--bad)">${belum}</span> orang`,
        `<span class="num" ${cls}>${nf(pct)}%</span>`,
        mbar(pct, pct >= 92 ? 'ok' : 'bad', 5),
      ],
    };
  });

  const stasiunCards = STASIUN.map(([nama, komp, pct, n]) => {
    const worst = nama === STASIUN_MIN[0];
    const pctColor = pct >= 95 ? 'var(--ok)' : pct >= 90 ? 'var(--ind)' : 'var(--bad)';
    return `<div class="stasiun-card${worst ? ' is-worst' : ''}">
      <div class="s-nama">${esc(nama)}</div>
      <div class="s-komp">${esc(komp)}</div>
      <div class="s-pct" style="color:${pctColor}">${nf(pct)}%</div>
      <div class="s-pers">${mbar(pct, worst ? 'bad' : pct >= 95 ? 'ok' : 'ind', 4)}</div>
      <div class="s-pers">${n} peserta · ${Math.round(n * pct / 100)} lulus</div>
      ${worst ? '<span class="badge bad">Perlu perbaikan</span>' : ''}
    </div>`;
  }).join('');

  return head('Uji Kompetensi',
    `F-09 · Tabel 19 & 20 · OSCE ${STASIUN.length} stasiun · ${TOTAL_PESERTA} peserta, ${TOTAL_LULUS_UJI} lulus`,
    '<button class="btn btn-ghost">Ekspor Tabel 19</button><button class="btn btn-primary">+ Peserta</button>')
  + strip([
    [`${TOTAL_PESERTA}`, 'peserta OSCE', ''],
    [`${TOTAL_LULUS_UJI}`, 'lulus uji', 'ok'],
    [`${TOTAL_BELUM}`, 'belum lulus', 'bad'],
    [`${nf(PERSEN_LULUS)}%`, 'tingkat kelulusan', PERSEN_LULUS >= 92 ? 'ok' : 'warn'],
    [`${STASIUN.length}`, 'stasiun OSCE', ''],
  ])
  + `<div class="card">
      <div class="card-head"><h2>Peserta uji kompetensi per angkatan</h2>
        <span class="note">Tabel 19 · peserta = lulusan yang menempuh OSCE · TS-1 &amp; TS belum diuji</span></div>
      ${table(
        [['Angkatan', ''], ['Tahun akademik', ''], ['Peserta', 'r'], ['Lulus', 'r'], ['Belum lulus', 'r'], ['% kelulusan', 'r'], ['Bar', '']],
        ujiRows,
        { foot: ['Total', '', `${TOTAL_PESERTA} orang`, `${TOTAL_LULUS_UJI} orang`, `${TOTAL_BELUM} orang`,
          `<span style="color:var(--ind);font-weight:700">${nf(PERSEN_LULUS)}%</span>`, ''] },
      )}
      <div class="legend mt12">
        <span><span class="swatch ok"></span> ≥ 92%</span>
        <span><span class="swatch bad"></span> &lt; 92%</span>
        <span class="muted">angka lulus angkatan dari data.js: ${D.lulus} orang total lulus studi</span>
      </div>
    </div>`
  + callout('warn', `Stasiun terburuk: ${STASIUN_MIN[0]} — ${nf(STASIUN_MIN[2])}%`,
    `<p>Kompetensi yang diuji: <b>${esc(STASIUN_MIN[1])}</b>. Tingkat kelulusan terendah dari ${STASIUN.length} stasiun OSCE.
       Stasiun ini perlu evaluasi pembelajaran — cek RPS, media praktik, dan frekuensi latihan sebelum uji berikutnya.</p>`,
    '<button class="btn btn-ghost" style="border-color:var(--warn);color:var(--warn)">Buka Kurikulum →</button>')
  + `<div class="card">
      <div class="card-head"><h2>Stasiun OSCE</h2>
        <span class="note">${STASIUN.length} stasiun · kartu terbesar = kelulusan terendah</span></div>
      <div class="stasiun-grid">${stasiunCards}</div>
    </div>`
  + `<div class="card">
      <div class="card-head"><h2>Keterangan</h2><span class="note">F-09 · Tabel 19 &amp; 20</span></div>
      <div class="dlist">
        <div class="item"><span class="dot"></span><div>
          <h4>Wajib sebelum sumpah/janji</h4>
          <p>Lulusan kebidanan menempuh OSCE sebelum mengucap sumpah/janji bidan. Tanpa kelulusan, ijazah tidak diterbitkan.</p></div></div>
        <div class="item"><span class="dot"></span><div>
          <h4>Hasil masuk Tabel 19</h4>
          <p>Jumlah peserta, lulus, dan persentase kelulusan per angkatan dilaporkan di Tabel 19 instrumen LAM-PTKes.</p></div></div>
        <div class="item"><span class="dot"></span><div>
          <h4>Stasiun masuk Tabel 20</h4>
          <p>Daftar stasiun OSCE beserta kompetensi dan tingkat kelulusan per stasiun dilaporkan di Tabel 20.</p></div></div>
        <div class="item"><span class="dot"></span><div>
          <h4>Stasiun &lt; 92% ditindaklanjuti</h4>
          <p>Stasiun dengan kelulusan di bawah 92% wajib dievaluasi: RPS, media, dan frekuensi latihan diperbaiki sebelum uji periode berikutnya.</p></div></div>
      </div>
    </div>`;
}

export default page;
