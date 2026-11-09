/* dosen — F-06. Registry, bukan form. Satu orang satu baris walau lintas prodi.
 * 24 orang mengisi 8 tabel K5. Dosen yang mengajar di dua prodi tetap satu baris,
 * ditandai lintas prodi — bukan diduplikasi. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PRODI,
} from '../pagekit.js';

// dipindah ke data.js saat backend ada
const REKAP = { tetap: 18, tidakTetap: 4, tendik: 2, sertifikat: 15, belum: 9, lintas: 3 };
const TOTAL = REKAP.tetap + REKAP.tidakTetap + REKAP.tendik;   // 24

/* 8 tabel K5 (instrumen LAM-PTKes Dosen & Tendik):
 * 12 Dosen Tetap · 13 Dosen Tidak Tetap · 14 Tendik · 15 Sertifikat Pendidik
 * 16 Penelitian · 17 PkM · 18 Rekognisi/Jabatan · 19 Beban Mengajar */
const TABEL_K5 = [
  ['12', 'Dosen Tetap'], ['13', 'Dosen Tidak Tetap'], ['14', 'Tendik'],
  ['15', 'Sertifikat Pendidik'], ['16', 'Penelitian'], ['17', 'PkM'],
  ['18', 'Rekognisi & Jabatan'], ['19', 'Beban Mengajar'],
];

// dipindah ke data.js saat backend ada
// nama+gelar, nidn, jabatan, prodi, lintas, strAktif, tabel[]
const ROSTER = [
  ['Dr. Maimunah, M.Keb', '0012076802', 'Lektor Kepala', 'Kebidanan', false, true, ['12','15','16','19']],
  ['Siti Rahmawati, S.ST., M.Keb', '0112058701', 'Lektor', 'Kebidanan', false, true, ['12','15','17','19']],
  ['Nurhayati, S.Kep., M.Kep', '0023076503', 'Lektor', 'Keperawatan', false, true, ['12','15','16','19']],
  ['Yusrizal, S.Kep., Ns., M.Kep', '0118058804', 'Lektor', 'Kebidanan + Keperawatan', true, true, ['12','15','16','17','19']],
  ['Dr. Ir. Zulfikar, M.Pd', '0019077205', 'Lektor Kepala', 'Kebidanan + Keperawatan', true, true, ['12','15','18','19']],
  ['Prof. Dr. Cut Zahriah, M.Pd', '0020057306', 'Guru Besar', 'Kebidanan + Keperawatan', true, true, ['12','15','18','19']],
  ['Marlina, S.ST., M.Keb', '0112065407', 'Asisten Ahli', 'Kebidanan', false, false, ['12','15','19']],
  ['Hasnawati, A.Md.Keb', '0123110808', 'Dosen Tidak Tetap', 'Kebidanan', false, true, ['13','15','19']],
  ['Rina Marlina, S.Kep', '0118075709', 'Dosen Tidak Tetap', 'Keperawatan', false, false, ['13','19']],
  ['Safrida, S.ST', '0112055810', 'Dosen Tidak Tetap', 'Kebidanan', false, true, ['13','15','19']],
  ['Sumiati, A.Md', '—', 'Tendik · Laboran', 'Kebidanan', false, null, ['14']],
  ['Rahmat Hidayat, S.Kom', '—', 'Tendik · Admin', 'UPPS', false, null, ['14']],
];

/* orang contoh untuk detail pemetaan tabel — lintas prodi, masuk 5 tabel */
const CONTOH = ROSTER.find((r) => r[0] === 'Yusrizal, S.Kep., Ns., M.Kep');
const CONTOH_MAP = [
  ['12', 'Dosen Tetap', 'Terdaftar sebagai dosen tetap prodi Kebidanan; mengajar MK Keperawatan juga.'],
  ['15', 'Sertifikat Pendidik', 'Sertifikat pendidik aktif, masa berlaku 2028.'],
  ['16', 'Penelitian', '2 penelitian TS (1 Kebidanan, 1 Keperawatan).'],
  ['17', 'PkM', '3 PkM TS lintas prodi.'],
  ['19', 'Beban Mengajar', '14 SKS TS — 8 SKS Kebidanan, 6 SKS Keperawatan.'],
];

export const css = `
/* dosen — highlight baris lintas prodi + grid pemetaan tabel */
.tbl tr.lintas td { background: var(--ind-t); }
.tbl tr.lintas:hover td { background: #E4E1F4; }
.orn-chip {
  font-family: var(--mono); font-size: var(--fs-xs); font-weight: 700;
  color: var(--on-orn); background: var(--orn); border-radius: 4px;
  padding: 2px 6px; display: inline-block;
}
.dmap { display: grid; gap: 0; }
.dmap-row {
  display: grid; grid-template-columns: 44px minmax(110px, auto) minmax(0, 1fr);
  gap: clamp(8px, 1vw, 14px); align-items: baseline;
  padding: 10px 0; border-bottom: 1px solid var(--dv);
}
.dmap-row:last-child { border-bottom: 0; }
.dmap-num {
  font-family: var(--mono); font-size: var(--fs-sm); font-weight: 700;
  color: var(--ind); background: var(--ind-t); border-radius: 4px;
  padding: 3px 7px; text-align: center;
}
.dmap-lab { font-size: var(--fs-sm); font-weight: 600; color: var(--ink1); }
.dmap-dsc { font-size: var(--fs-xs); color: var(--ink2); line-height: 1.5; }
`;

function page(state) {
  const k5 = KRITERIA.find((k) => k.kode === 'K5');
  const rasio = Math.round(D.masuk / REKAP.tetap);   // 425 / 18 = 24
  const tanpaStr = ROSTER.filter((r) => r[5] === false).length;

  const chips = (tabels) =>
    `<td class="tagcell">${tabels.map((t) => `<span class="tag">${t}</span>`).join('')}</td>`;

  const prodiCell = (prodi, lintas) =>
    `<td>${esc(prodi)}${lintas ? ` ${badge('na', 'lintas')}` : ''}</td>`;

  const strCell = (str) =>
    `<td>${str === null ? '<span class="muted">—</span>'
      : str ? badge('ok', 'STR aktif') : badge('bad', 'STR kosong')}</td>`;

  return head('Dosen & Tendik',
    `F-06 · ${TOTAL} orang · ${k5.tabel[0]}/${k5.tabel[1]} tabel terisi · satu orang satu baris walau lintas prodi`,
    '<button class="btn btn-ghost">Impor Excel</button><button class="btn btn-primary">+ Dosen</button>')
  + callout('bad', `Rasio dosen:mahasiswa 1:${rasio} melebihi batas instrumen 1:20`,
      `<p>${esc(k5.temuan)}</p>
       <div class="row mt12" style="gap:26px">
         <div><span style="color:var(--bad);font-weight:700;font-size:var(--fs-xs)">RASIO</span>
           <div class="foot-note" style="margin-top:2px">1:${rasio} — batas 1:20 · ${REKAP.tetap} dosen tetap : ${nf(D.masuk)} mahasiswa</div></div>
         <div><span style="color:var(--bad);font-weight:700;font-size:var(--fs-xs)">STR</span>
           <div class="foot-note" style="margin-top:2px">${tanpaStr} dosen tanpa STR aktif — blokir Tabel 15</div></div>
       </div>`,
      '<button class="btn btn-danger">Perbaiki STR</button>')
  + strip([
    [`${REKAP.tetap}`, 'dosen tetap', 'ok'],
    [`${REKAP.tidakTetap}`, 'tidak tetap', 'warn'],
    [`${REKAP.tendik}`, 'tendik', 'muted'],
    [`${REKAP.sertifikat}`, 'bersertifikat', 'ok'],
    [`${REKAP.belum}`, 'belum bersertifikat', 'bad'],
    [`${REKAP.lintas}`, 'lintas prodi', ''],
  ])
  + `<div class="card">
      <div class="card-head"><h2>Roster dosen & tendik</h2>
        <span class="note">${ROSTER.length} dari ${TOTAL} orang · satu baris per orang · chip = nomor tabel K5 yang memakai orang ini</span></div>
      <table class="tbl">
        <thead><tr>
          <th>Nama</th><th>NIDN</th><th>Jabatan</th><th>Prodi</th>
          <th>STR</th><th>Tabel</th>
        </tr></thead>
        <tbody>
        ${ROSTER.map(([nama, nidn, jab, prodi, lintas, str, tabels]) =>
          `<tr class="${lintas ? 'lintas' : ''}">
            <td class="strong">${esc(nama)}</td>
            <td class="num muted">${esc(nidn)}</td>
            <td>${esc(jab)}</td>
            ${prodiCell(prodi, lintas)}
            ${strCell(str)}
            ${chips(tabels)}
          </tr>`).join('')}
        </tbody>
      </table>
      <div class="legend mt12">
        <span><span class="swatch ind"></span> baris lintas prodi — mengajar di dua prodi, tetap satu baris</span>
        <span class="muted">chip = nomor tabel yang memakai orang ini · 8 tabel K5: ${TABEL_K5.map(([n]) => n).join(', ')}</span>
      </div>
      <p class="foot-note">Dosen lintas prodi tidak diduplikasi. ${REKAP.lintas} orang mengajar di Kebidanan dan Keperawatan — tetap ${REKAP.lintas} baris, bukan ${REKAP.lintas * 2}. NIDN kosong pada tendik karena tendik tidak punya NIDN.</p>
    </div>`
  + `<div class="card">
      <div class="card-head"><h2>Detail: ${esc(CONTOH[0])}</h2>
        <span class="note">data ini masuk tabel mana saja · ${CONTOH[6].length} dari 8 tabel K5</span></div>
      <div class="idhead" style="margin-bottom:0;padding-bottom:10px">
        <span class="foot-note" style="margin:0">${esc(CONTOH[3])} · ${esc(CONTOH[2])} · NIDN ${esc(CONTOH[1])}</span>
        <span class="orn-chip">${CONTOH[6].length} tabel</span>
      </div>
      <div class="dmap">
        ${CONTOH_MAP.map(([num, lab, dsc]) =>
          `<div class="dmap-row">
            <span class="dmap-num">${num}</span>
            <span class="dmap-lab">${esc(lab)}</span>
            <span class="dmap-dsc">${esc(dsc)}</span>
          </div>`).join('')}
      </div>
      <p class="foot-note mt8">Orang ini muncul sekali di Tabel 12 (dosen tetap), bukan dua kali walau mengajar di dua prodi. Tabel 16, 17, 19 memakai data yang sama — beban mengajar dijumlahkan lintas prodi di Tabel 19. Kalau duplikasi terjadi, ${REKAP.lintas} orang jadi ${REKAP.lintas * 2} baris dan total dosen meleset dari ${TOTAL}.</p>
    </div>`
  + `<div class="card">
      <div class="card-head"><h2>Pemetaan 8 tabel K5</h2>
        <span class="note">${k5.tabel[0]} terisi dari ${k5.tabel[1]} · ${k5.dok[0]} dokumen dari ${k5.dok[1]}</span></div>
      <table class="tbl">
        <thead><tr><th>No.</th><th>Tabel</th><th>Memuat</th><th class="r">Orang</th></tr></thead>
        <tbody>
        ${TABEL_K5.map(([num, nama]) => {
          const orang = ROSTER.filter((r) => r[6].includes(num)).length;
          return `<tr>
            <td class="num strong">${num}</td>
            <td>${esc(nama)}</td>
            <td class="muted">${num === '14' ? 'tendik' : 'dosen'}</td>
            <td class="r num">${orang}</td>
          </tr>`;
        }).join('')}
        </tbody>
        <tfoot><tr>
          <td colspan="3" class="muted">total baris tabel (bukan orang unik — dosen lintas prodi dihitung per tabel)</td>
          <td class="r num">${TABEL_K5.reduce((s, [n]) => s + ROSTER.filter((r) => r[6].includes(n)).length, 0)}</td>
        </tr></tfoot>
      </table>
      <p class="foot-note">Tabel 12 + 13 + 14 = ${REKAP.tetap + REKAP.tidakTetap + REKAP.tendik} baris (satu per orang). Tabel 15–19 memakai sub-himpunan dosen yang punya data terkait. Orang lintas prodi tetap satu baris di tiap tabel — tidak diduplikasi per prodi.</p>
    </div>`;
}

export default page;
