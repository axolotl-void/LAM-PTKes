/* periode — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

/* dipindah ke data.js saat backend ada */
const TABEL_FORMULA = ['1', '2', '3', '5', '9', '10', '13', '14', '18', '20', '21', '24', '25', '26', '31', '33', '38'];       // 17 — label TS dipakai di rumus, bergeser otomatis
const TABEL_LABEL_TH = ['7', '11', '16', '17', '19', '22', '27', '28', '34', '35', '36'];                                       // 11 — kolom header pakai label tahun, geser manual
const RIWAYAT_PERIODE = [
  ['4 Nov 2026 · 11:05', 'Dr. Maimunah, M.Keb', 'kunci periode TS 2025/2026', 'dikunci'],
  ['4 Nov 2026 · 09:30', 'Siti Rahmawati', 'atur TS 2025/2026 saat setup awal', 'dibuat'],
  ['2 Nov 2026 · 14:20', 'Siti Rahmawati', 'minta geser TS ke 2025/2026', 'menunggu'],
];

export const css = `
.pd-hero { display:grid; grid-template-columns: 1.4fr 1fr; gap:var(--gap); margin-bottom:var(--gap); }
.pd-hero .card { margin-top:0; }
.pd-ts { display:flex; align-items:baseline; gap:10px; }
.pd-ts .yr { font-family:var(--mono); font-size:var(--fs-big); font-weight:700; color:var(--ind); letter-spacing:-0.01em; }
.pd-ts .tag-ss { font-size:var(--fs-sm); }
.pd-meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px 20px; margin-top:14px; }
.pd-meta .m-cell { border-left:2px solid var(--bd); padding-left:12px; }
.pd-meta .m-cell:first-child { border-left:0; padding-left:0; }
.pd-meta .m-lab { font-size:var(--fs-xs); color:var(--ink3); text-transform:uppercase; letter-spacing:0.05em; }
.pd-meta .m-val { font-family:var(--mono); font-size:var(--fs-h2); font-weight:600; color:var(--ink1); margin-top:3px; }
.pd-meta .m-sub { font-size:var(--fs-xs); color:var(--ink3); margin-top:2px; }
.pd-map .tbl td.lab { font-weight:600; color:var(--ind); font-family:var(--mono); white-space:nowrap; }
.pd-map .tbl td.now { background:var(--ind-t); }
.pd-impact { display:grid; grid-template-columns: 1fr 1.3fr; gap:var(--gap); }
.pd-impact .card { margin-top:0; }
.pd-chips { display:flex; flex-wrap:wrap; gap:4px; margin-top:10px; }
.pd-chips .chip { display:inline-block; white-space:nowrap; font-family:var(--mono); font-size:var(--fs-xs); font-weight:600; padding:2px 8px; margin:2px 0; border-radius:4px; border:1px solid var(--bd); background:#F9FAFB; color:var(--ink2); }
.pd-chips .chip.auto { border-color:var(--ok); background:var(--ok-bg); color:var(--ok); }
.pd-chips .chip.manual { border-color:var(--warn); background:var(--warn-bg); color:var(--warn); }
.pd-bar { display:flex; align-items:center; gap:8px; margin-top:12px; }
.pd-bar .lbl { font-size:var(--fs-xs); color:var(--ink3); white-space:nowrap; }
@media (max-width: 900px) { .pd-hero, .pd-impact { grid-template-columns:1fr; } }
`;

function page(state) {
  const tsTahun = PERIODE.ts;
  const ssKosong = !PERIODE.ss;
  const total = INSTRUMEN.totalTabel;
  const otomatis = TABEL_FORMULA.length;
  const manual = TABEL_LABEL_TH.length;
  const takTerkait = total - otomatis - manual;

  // Berapa tabel yang benar-benar memakai tiap label — ini yang informatif,
  // bukan mengulang tahun absolut di dua kolom.
  const dipakai = {};
  ANGKATAN.forEach(([lab]) => { dipakai[lab] = 0; });
  // tabel yang menyebut label relatif: 17 otomatis memakai TS..TS-6
  TABEL_FORMULA.forEach((_, i) => {
    const lab = ANGKATAN[ANGKATAN.length - 1 - (i % ANGKATAN.length)][0];
    dipakai[lab] += 1;
  });

  const mapRows = ANGKATAN.map(([lab, th]) => {
    const isNow = lab === 'TS';
    const n = dipakai[lab] || 0;
    return {
      cls: isNow ? 'is-now' : '',
      cells: [
        `<span class="lab">${lab}</span>${isNow ? ' ' + badge('ok', 'aktif') : ''}`,
        th,
        `<span class="muted">${n}</span>`,
      ],
    };
  });

  return head('Periode Akreditasi',
    `F-02 · TS ${tsTahun} · ${otomatis} tabel ikut bergeser otomatis, ${manual} harus dicek manual`)
  + `<div class="pd-hero">
      <section class="card">
        <div class="card-head"><h2>Periode aktif</h2><span class="note">sumber seluruh label TS di app</span></div>
        <div class="pd-ts">
          <span class="yr">TS ${tsTahun}</span>
          <span class="tag-ss">${ssKosong
            ? badge('na', 'SS belum diatur')
            : badge('ok', 'SS ' + PERIODE.ss)}</span>
        </div>
        <p class="foot-note">TS = tahun sekarang (tahun akademik berjalan). SS = tahun survei.
        Setiap label relatif (TS, TS-1, …, TS-6) dipetakan ke tahun absolut saat periode ini disimpan.
        Mengubah TS menggeser seluruh deretan.</p>
        <div class="pd-meta">
          <div class="m-cell">
            <div class="m-lab">Tanggal pengajuan</div>
            <div class="m-val">12 Agu 2027</div>
            <div class="m-sub">batas akhir dokumen</div>
          </div>
          <div class="m-cell">
            <div class="m-lab">Sisa hari berlaku</div>
            <div class="m-val">${D.hari} hari</div>
            <div class="m-sub">dari snapshot ${DATA_PER}</div>
          </div>
          <div class="m-cell">
            <div class="m-lab">Rentang data</div>
            <div class="m-val">${ANGKATAN[0][1]}–${tsTahun}</div>
            <div class="m-sub">${ANGKATAN.length} angkatan tertangkap</div>
          </div>
        </div>
      </section>
      <section class="card">
        <div class="card-head"><h2>Inti desain</h2><span class="note">kenapa label relatif</span></div>
        ${dlist([
          ['Simpan absolut, tampilkan relatif',
            'Database menyimpan 2025/2026. Layar menampilkan TS. Pengguna tidak perlu tahu tahun kalender.'],
          ['Geser sekali, deretan ikut',
            `Ubah TS dari 2025/2026 ke 2026/2027 — TS-1 jadi 2025/2026, TS-2 jadi 2024/2025, sampai TS-6.`],
          ['SS terpisah',
            ssKosong
            ? 'Survei belum dijadwalkan. SS dan SS-1..SS-6 kosong sampai tanggal survei ditetapkan.'
            : 'SS ' + PERIODE.ss + ' menentukan tahun survei — deretan SS-1..SS-6 mengikuti.'],
        ])}
      </section>
    </div>`
  + `<div class="card pd-map">
      <div class="card-head"><h2>Pemetaan label → tahun absolut</h2>
        <span class="note">${ANGKATAN.length} label · TS-6 paling tua · TS = periode aktif</span></div>
      ${table(
        [['Label', 'l'], ['Tahun absolut', 'l'], ['Dipakai tabel', 'r']],
        mapRows,
      )}
      <p class="foot-note">Kolom ketiga = jumlah tabel yang memakai label itu. Data di app
      disimpan sebagai label (kolom 1); tahun absolut hanya untuk dibaca manusia.</p>
    </div>`
  + `<div class="pd-impact">
      <section class="card">
        <div class="card-head"><h2>Bergeser otomatis</h2>
          <span class="note">${otomatis} dari ${total} tabel</span></div>
        <p class="foot-note" style="margin-top:0">Tabel berformula: kolom memakai label TS, TS-1, dst.
        Tahun absolut dihitung ulang saat periode diubah — tidak ada input manual.</p>
        <div class="pd-chips">
          ${TABEL_FORMULA.map((t) => `<span class="chip auto">Tabel ${t}</span>`).join('')}
        </div>
        <div class="pd-bar">
          <span class="lbl">${otomatis} otomatis</span>
          ${mbar((otomatis / total) * 100, 'ok', 6)}
        </div>
      </section>
      <section class="card">
        <div class="card-head"><h2>Perlu dicek manual</h2>
          <span class="note">${manual} dari ${total} tabel</span></div>
        <p class="foot-note" style="margin-top:0">Tabel ini menulis label tahun langsung di header kolom
        (mis. "2023/2024"), bukan label relatif. Geser periode tidak mengubah teks header — harus diedit per tabel.</p>
        <div class="pd-chips">
          ${TABEL_LABEL_TH.map((t) => `<span class="chip manual">Tabel ${t}</span>`).join('')}
        </div>
        <div class="pd-bar">
          <span class="lbl">${manual} manual</span>
          ${mbar((manual / total) * 100, 'bad', 6)}
          <span class="lbl" style="margin-left:auto">${takTerkait} tak terkait</span>
        </div>
      </section>
    </div>`
  + callout('warn',
      `Ubah periode setelah ada data = 11 tabel tidak ikut`,
      `<p>17 tabel berformula bergeser otomatis, tapi <b>${manual} tabel</b> di kanan atas masih
      menulis "2023/2024" sebagai teks kolom. Kalau TS digeser setelah data terisi, label di
      ${manual} tabel itu <b>tidak ikut bergeser</b> — tahun di header akan kontradiksi dengan
      label relatif di 17 tabel lain. Periksa dan edit manual sebelum ekspor.</p>
      <p class="foot-note" style="margin-top:8px">Sisa <b>${takTerkait}</b> tabel tidak memakai label periode
      (${INSTRUMEN.tabelNA.length} tak berlaku untuk jenjang ini, sisanya tanpa dimensi tahun).</p>`)
  + `<section class="card">
      <div class="card-head"><h2>Riwayat perubahan periode</h2>
        <span class="note">${RIWAYAT_PERIODE.length} entri</span></div>
      <div class="rwt">
        ${RIWAYAT_PERIODE.map(([waktu, siapa, apa, jenis]) => `<div class="rwt-row">
          <span class="rwt-t">${esc(waktu)}</span>
          <span class="rwt-who">${esc(siapa)}</span>
          <span class="rwt-what">${esc(apa)}</span>
          <span class="badge ${jenis === 'menunggu' ? 'warn' : jenis === 'dikunci' ? 'lock' : 'na'}">${esc(jenis)}</span>
        </div>`).join('')}
      </div>
    </section>`;
}

export default page;
