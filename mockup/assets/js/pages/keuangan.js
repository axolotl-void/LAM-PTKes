/* keuangan — F-17 · Tabel 33 (penerimaan) & Tabel 34 (penggunaan dana).
 *
 * Aturan mutlak instrumen: persen penggunaan per tahun harus tepat 100%.
 * TS-1 asli 99,9% karena spreadsheet pakai truncation, bukan largest
 * remainder. Halaman ini menampilkan koreksi — semua tahun = 100,0%. */
import { esc, nf, head, strip, callout, table } from '../pagekit.js';

export const css = `
.fin-bars { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: var(--gap); margin-top:4px; }
.fin-bar .yr { font-size: var(--fs-xs); font-weight:700; color: var(--ink2); margin-bottom:6px; }
.fin-bar .yr b { font-family: var(--mono); color: var(--ink1); }
.fin-seg { display:flex; height:24px; border-radius:4px; overflow:hidden; background: var(--dv); }
.fin-seg i { display:block; height:100%; }
.fin-s0 { background: var(--ind); }
.fin-s1 { background: var(--ind-d); }
.fin-s2 { background: var(--orn); }
.fin-s3 { background: var(--ok); }
.fin-s4 { background: var(--warn); }
.fin-s5 { background: var(--ink2); }
.fin-total { font-family: var(--mono); font-size: var(--fs-sm); font-weight:700; text-align:right; margin-top:4px; color: var(--ok); }
.fin-legend { display:flex; flex-wrap:wrap; gap:8px 14px; margin-top:14px; font-size: var(--fs-xs); color: var(--ink2); }
.fin-legend span { display:inline-flex; align-items:center; gap:5px; }
.fin-legend i { width:10px; height:10px; border-radius:2px; display:inline-block; }
`;

/* dipindah ke data.js saat backend ada */
const TAHUN = ['TS-2', 'TS-1', 'TS'];
const TAHUN_LABEL = ['2023/2024', '2024/2025', '2025/2026'];

// [jenis, TS-2, TS-1, TS] dalam Rp juta — Tabel 34
const JENIS_GUNA = [
  ['Gaji & honor dosen/tendik',  900,  917,  920],
  ['Operasional pendidikan',     280,  276,  280],
  ['Sarana & prasarana',         200,  207,  200],
  ['Penelitian',                 100,  100,  100],
  ['PkM',                         80,   80,   80],
  ['Lain-lain',                  440,  420,  420],
];

// [sumber, TS-2, TS-1, TS] dalam Rp juta — Tabel 33
// TS-2 surplus (hibah 100), TS-1 defisit (hibah turun ke 70 + SPP turun)
const SUMBER_TERIMA = [
  ['SPP & uang kuliah', 1880, 1850, 1900],
  ['Hibah & bantuan',    120,   70,   60],
  ['Pendapatan lain',     50,   60,   50],
];

/** Largest remainder: persen 1 desimal, dijamin jumlah 100,0.
 *  Scale ke 1000 (10 × 100), floor, lalu bagi sisa ke frac terbesar. */
function pctLR(vals) {
  const total = vals.reduce((s, v) => s + v, 0);
  if (total === 0) return vals.map(() => 0);
  const raw = vals.map((v) => (v / total) * 100);
  const base = raw.map((r) => Math.floor(r * 10) / 10);
  let need = Math.round((100 - base.reduce((s, v) => s + v, 0)) * 10);
  const order = raw
    .map((r, i) => [i, r * 10 - base[i] * 10])
    .sort((a, b) => b[1] - a[1]);
  let k = 0;
  while (need > 0) { base[order[k % order.length][0]] += 0.1; need--; k++; }
  return base;
}

/** Truncation (floor 1 desimal) — metode lama yang menyebabkan 99,9%. */
function pctTrunc(vals) {
  const total = vals.reduce((s, v) => s + v, 0);
  if (total === 0) return vals.map(() => 0);
  return vals.map((v) => Math.floor((v / total) * 1000) / 10);
}

const fmt1 = (n) => n.toFixed(1).replace('.', ',');

function page(state) {
  const gunaTotal = TAHUN.map((_, ti) => JENIS_GUNA.reduce((s, j) => s + j[1 + ti], 0));
  const terimaTotal = TAHUN.map((_, ti) => SUMBER_TERIMA.reduce((s, j) => s + j[1 + ti], 0));
  const selisih = TAHUN.map((_, ti) => terimaTotal[ti] - gunaTotal[ti]);

  const pct = TAHUN.map((_, ti) => pctLR(JENIS_GUNA.map((j) => j[1 + ti])));
  const pctTr = TAHUN.map((_, ti) => pctTrunc(JENIS_GUNA.map((j) => j[1 + ti])));
  const pctTrSum = pctTr.map((p) => p.reduce((s, v) => s + v, 0));

  // self-check: largest remainder harus 100,0 untuk semua tahun
  TAHUN.forEach((t, ti) => {
    const s = Math.round(pct[ti].reduce((x, v) => x + v, 0) * 10) / 10;
    if (s !== 100) throw new Error(`pctLR ${t} = ${s}, bukan 100`);
  });

  return head('Keuangan', 'F-17 · Tabel 33 & 34 · persen penggunaan per tahun harus 100%')
  // ── strip ringkas ──
  + strip([
    [`Rp ${nf(gunaTotal[2])} jt`, 'penggunaan TS', ''],
    [`Rp ${nf(terimaTotal[2])} jt`, 'penerimaan TS', ''],
    [`${selisih[2] < 0 ? '−' : '+'}Rp ${nf(Math.abs(selisih[2]))} jt`, 'selisih TS', selisih[2] < 0 ? 'bad' : 'ok'],
  ])
  // ── temuan: TS-1 99,9% ──
  + callout('bad', 'Penggunaan dana TS-1 = 99,9% — harus tepat 100%',
    `<p>Tabel 34 TS-1 dihitung pakai <b>truncation</b> (pembulatan ke bawah) di spreadsheet instrumen, bukan largest remainder. Dua jenis — gaji honor (45,85% dipotong jadi 45,8%) dan sarana (10,35% dipotong jadi 10,3%) — masing-masing dibulatkan ke bawah, sehingga total turun 0,1% menjadi 99,9%. Ini memblokir ekspor.</p>
     <div class="row mt8" style="gap:18px;font-size:var(--fs-xs);color:var(--ink3)">
       <span><b style="color:var(--bad)">truncation</b> → ${fmt1(pctTrSum[1])}%</span>
       <span><b style="color:var(--ok)">largest remainder</b> → 100,0%</span>
     </div>
     <p class="foot-note" style="margin-top:8px"><b>Yang harus diperbaiki:</b> ganti metode pembulatan di sel Tabel 34 dari <code>ROUNDDOWN</code> ke largest remainder, atau perbaiki dua nilai sumber (gaji honor dan sarana) supaya pembagian terhadap total Rp 2.000 jt menghasilkan angka bulat.</p>`)
  // ── bar segmentasi 3 tahun ──
  + `<div class="card">
      <div class="card-head"><h2>Proporsi penggunaan dana</h2>
        <span class="note">Tabel 34 · 6 jenis · koreksi largest remainder · semua 100,0%</span></div>
      <div class="fin-bars">
        ${TAHUN.map((t, ti) => `
          <div class="fin-bar">
            <div class="yr">${t} <b>${TAHUN_LABEL[ti]}</b></div>
            <div class="fin-seg">
              ${pct[ti].map((p, ji) => p > 0
                ? `<i class="fin-s${ji}" style="width:${p.toFixed(1)}%" title="${esc(JENIS_GUNA[ji][0])}: ${fmt1(p)}%"></i>`
                : '').join('')}
            </div>
            <div class="fin-total">100,0%</div>
          </div>`).join('')}
      </div>
      <div class="fin-legend">
        ${JENIS_GUNA.map((j, ji) => `<span><i class="fin-s${ji}"></i>${esc(j[0])}</span>`).join('')}
      </div>
    </div>`
  // ── Tabel 34: penggunaan per jenis x 3 tahun ──
  + `<div class="card">
      <div class="card-head"><h2>Penggunaan dana per jenis</h2>
        <span class="note">Tabel 34 · Rp juta</span></div>
      ${table(
        [['Jenis penggunaan', ''], ['TS-2', 'r'], ['TS-1', 'r'], ['TS', 'r'], ['Total', 'r']],
        JENIS_GUNA.map((j) => ({
          cells: [
            esc(j[0]),
            nf(j[1]),
            nf(j[2]),
            nf(j[3]),
            nf(j[1] + j[2] + j[3]),
          ],
        })),
        { foot: [
          '<b>Total</b>',
          `<b>${nf(gunaTotal[0])}</b>`,
          `<b>${nf(gunaTotal[1])}</b>`,
          `<b>${nf(gunaTotal[2])}</b>`,
          `<b>${nf(gunaTotal[0] + gunaTotal[1] + gunaTotal[2])}</b>`,
        ] },
      )}
      <p class="foot-note">Persen per tahun (largest remainder): TS-2 100,0% · TS-1 100,0% · TS 100,0%. Sebelum koreksi, TS-1 = ${fmt1(pctTrSum[1])}% akibat truncation.</p>
    </div>`
  // ── Tabel 33 + selisih ──
  + `<div class="grid two">
      <div class="card">
        <div class="card-head"><h2>Penerimaan dana per sumber</h2>
          <span class="note">Tabel 33 · Rp juta</span></div>
        ${table(
          [['Sumber penerimaan', ''], ['TS-2', 'r'], ['TS-1', 'r'], ['TS', 'r']],
          SUMBER_TERIMA.map((j) => ({
            cells: [esc(j[0]), nf(j[1]), nf(j[2]), nf(j[3])],
          })),
          { foot: [
            '<b>Total</b>',
            `<b>${nf(terimaTotal[0])}</b>`,
            `<b>${nf(terimaTotal[1])}</b>`,
            `<b>${nf(terimaTotal[2])}</b>`,
          ] },
        )}
        <p class="foot-note">SPP menanggung ${fmt1((SUMBER_TERIMA[0][3] / terimaTotal[2]) * 100)}% penerimaan TS. Ketergantungan tinggi — hilang satu sumber berdampak langsung.</p>
      </div>
      <div class="card">
        <div class="card-head"><h2>Selisih penerimaan − penggunaan</h2>
          <span class="note">Rp juta · positif = surplus</span></div>
        ${table(
          [['Tahun', ''], ['Penerimaan', 'r'], ['Penggunaan', 'r'], ['Selisih', 'r']],
          TAHUN.map((t, ti) => ({
            cls: selisih[ti] < 0 ? 'is-warn' : '',
            cells: [
              `${t} <span class="muted" style="font-size:var(--fs-xs)">${TAHUN_LABEL[ti]}</span>`,
              nf(terimaTotal[ti]),
              nf(gunaTotal[ti]),
              `<b style="color:var(--${selisih[ti] < 0 ? 'bad' : 'ok'})">${selisih[ti] < 0 ? '−' : '+'}${nf(Math.abs(selisih[ti]))}</b>`,
            ],
          })),
        )}
        <p class="foot-note">TS-1 defisit Rp ${nf(Math.abs(selisih[1]))} jt — penerimaan SPP turun Rp ${nf(SUMBER_TERIMA[0][1] - SUMBER_TERIMA[0][2])} jt dari TS-2, biaya operasional tetap. TS-2 surplus Rp ${nf(selisih[0])} jt dari hibah.</p>
      </div>
    </div>`;
}

export default page;
