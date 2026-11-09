/* survei — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

// dipindah ke data.js saat backend ada
// Skala 1-4: 1=kurang, 2=cukup, 3=baik, 4=sangat baik
const ASPEK_KEPUASAN = [
  ['Etika', 3.48],
  ['Keahlian kompetensi utama', 3.55],
  ['Bahasa komunikasi', 3.21],
  ['Kerja sama tim', 3.40],
  ['Penguasaan TI', 2.94],
];
const RESPONDEN = 52;       // pengguna lulusan yang mengisi
const TARGET_RESPONDEN = 140; // atasan/supervisor yang diundang
const RESPONSE_RATE = RESPONDEN / TARGET_RESPONDEN * 100;

// dipindah ke data.js saat backend ada
const TRACER = {
  terlacak: 178,           // punya info keberadaan (bekerja / studi / belum bekerja)
  belumTerlacak: 78,       // tidak tahu di mana — BUKAN berarti belum bekerja
  bekerja: 156,
  studi: 14,
  belumBekerja: 8,         // terlacak tapi belum bekerja (mencari / mengurus keluarga)
  tungguKurang3: 61,
  tunggu3sampai6: 54,
  tungguLebih6: 41,
  bidangSesuai: 108,
  bidangSebagian: 31,
  bidangTidak: 17,
  gaji: [['< 3 juta', 34], ['3–5 juta', 58], ['5–8 juta', 41], ['> 8 juta', 23]],
};

function page(state) {
  const rataKepuasan = ASPEK_KEPUASAN.reduce((s, a) => s + a[1], 0) / ASPEK_KEPUASAN.length;
  const pctKepuasan = rataKepuasan / 4 * 100;
  const aspekMin = ASPEK_KEPUASAN.reduce((m, a) => a[1] < m[1] ? a : m);
  const tungguKurang6 = TRACER.tungguKurang3 + TRACER.tunggu3sampai6;
  const pctTungguKurang6 = tungguKurang6 / TRACER.bekerja * 100;
  const pctTerlacak = TRACER.terlacak / D.lulus * 100;

  return head('Survei & Tracer',
    `F-10 · Tabel 21 · 22 · ${D.lulus} lulusan · ${RESPONDEN} responden pengguna · ${TRACER.terlacak} terlacak`,
    '<button class="btn btn-ghost">Ekspor CSV</button><button class="btn btn-primary">+ Survei</button>')

  // response rate rendah = temuan asesor
  + callout('warn', `Response rate ${RESPONSE_RATE.toFixed(1)}% — ${RESPONDEN} dari ${TARGET_RESPONDEN}`,
    `<p>Survei kepuasan pengguna lulusan hanya diisi <b>${RESPONDEN}</b> dari <b>${TARGET_RESPONDEN}</b> atasan/supervisor yang diundang. Instrument LAM-PTKes menyarankan minimal 60%. Angka di bawah ambang ini menjadi temuan asesor saat visitasi.</p>`)

  + strip([
    [`${D.lulus}`, 'lulusan total', ''],
    [`${TRACER.terlacak}`, 'terlacak', 'ok'],
    [`${TRACER.belumTerlacak}`, 'belum terlacak', 'warn'],
    [`${RESPONDEN}`, 'responden pengguna', ''],
    [nf(rataKepuasan), 'skor rata (1–4)', 'ok'],
  ])

  // ── Tabel 21: Survei kepuasan pengguna ──
  + `<div class="card">
      <div class="card-head">
        <h2>Kepuasan pengguna lulusan</h2>
        <span class="note">Tabel 21 · skala 1–4 · ${RESPONDEN} responden</span>
      </div>
      <table class="tbl">
        <thead><tr>
          <th>Aspek penilaian</th>
          <th class="r" style="width:90px">Skor</th>
          <th style="width:180px">Capaian</th>
          <th class="r" style="width:70px">%</th>
        </tr></thead>
        <tbody>
        ${ASPEK_KEPUASAN.map(([nama, skor]) => {
          const pct = skor / 4 * 100;
          const isMin = nama === aspekMin[0];
          return `<tr${isMin ? ' class="is-warn"' : ''}>
            <td>${esc(nama)}${isMin ? ' <span class="badge warn">terendah</span>' : ''}</td>
            <td class="r num strong" style="${isMin ? 'color:var(--warn)' : ''}">${nf(skor)}</td>
            <td>${mbar(pct, isMin ? 'warn' : 'ok', 6)}</td>
            <td class="r num">${nf(pct)}</td>
          </tr>`;
        }).join('')}
        </tbody>
        <tfoot><tr>
          <td>Rata-rata</td>
          <td class="r num strong" style="color:var(--ind)">${nf(rataKepuasan)}</td>
          <td></td>
          <td class="r num strong">${nf(pctKepuasan)}</td>
        </tr></tfoot>
      </table>
      <p class="foot-note">
        Kepuasan agregat <b>${nf(pctKepuasan)}%</b> (${nf(rataKepuasan)} dari 4).
        Aspek terendah: <b style="color:var(--warn)">${esc(aspekMin[0])}</b> (${nf(aspekMin[1])}) — perlu rencana perbaikan terdokumentasi.
      </p>
    </div>`

  // ── Tabel 22: Tracer study ──
  + `<div class="grid two">
      <div class="card">
        <div class="card-head">
          <h2>Keterlacakan lulusan</h2>
          <span class="note">Tabel 22 · dari ${D.lulus} lulusan</span>
        </div>
        <table class="tbl"><tbody>
          <tr>
            <td>Terlacak</td>
            <td class="r num strong" style="color:var(--ok)">${TRACER.terlacak} orang</td>
            <td class="r num">${nf(pctTerlacak)}%</td>
          </tr>
          <tr class="is-warn">
            <td>Belum terlacak</td>
            <td class="r num strong" style="color:var(--warn)">${TRACER.belumTerlacak} orang</td>
            <td class="r num">${nf(100 - pctTerlacak)}%</td>
          </tr>
        </tbody></table>
        ${mbar(pctTerlacak, 'ok', 8)}
        <p class="foot-note" style="margin-top:8px">
          <b>Belum terlacak</b> = tidak ada info keberadaan lulusan (pindah, tidak dijawab).
          <b>Belum bekerja</b> = lulusan dihubungi dan diketahui sedang mencari kerja atau mengurus keluarga.
          Keduanya berbeda dan tidak boleh ditukar di laporan akreditasi.
        </p>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Status ${TRACER.terlacak} lulusan terlacak</h2>
          <span class="note">bukan dari ${D.lulus} — dari yang terlacak saja</span>
        </div>
        <table class="tbl"><tbody>
          ${[
            ['Bekerja', TRACER.bekerja, 'ok'],
            ['Melanjutkan studi', TRACER.studi, ''],
            ['Belum bekerja', TRACER.belumBekerja, 'warn'],
          ].map(([lab, n, cls]) => `<tr>
            <td>${esc(lab)}</td>
            <td class="r num strong" style="${cls ? `color:var(--${cls})` : ''}">${n} orang</td>
            <td class="r num">${nf(n / TRACER.terlacak * 100)}%</td>
          </tr>`).join('')}
        </tbody></table>
        <p class="foot-note">${TRACER.belumBekerja} orang terlacak tapi belum bekerja — jumlah ini sudah masuk kolom "terlacak", bukan "belum terlacak".</p>
      </div>
    </div>`

  // ── Masa tunggu, kesesuaian bidang, gaji ──
  + `<div class="grid two">
      <div class="card">
        <div class="card-head">
          <h2>Masa tunggu kerja</h2>
          <span class="note">dari ${TRACER.bekerja} lulusan bekerja</span>
        </div>
        <table class="tbl">
          <thead><tr><th>Lama tunggu</th><th class="r" style="width:80px">Orang</th><th class="r" style="width:60px">%</th></tr></thead>
          <tbody>
          ${[
            ['< 3 bulan', TRACER.tungguKurang3, 'ok'],
            ['3–6 bulan', TRACER.tunggu3sampai6, ''],
            ['> 6 bulan', TRACER.tungguLebih6, 'warn'],
          ].map(([lab, n, cls]) => `<tr${cls === 'warn' ? ' class="is-warn"' : ''}>
            <td>${lab}</td>
            <td class="r num strong">${n}</td>
            <td class="r num">${nf(n / TRACER.bekerja * 100)}</td>
          </tr>`).join('')}
          </tbody>
          <tfoot><tr>
            <td>&lt; 6 bulan</td>
            <td class="r num strong" style="color:var(--ok)">${tungguKurang6}</td>
            <td class="r num strong">${nf(pctTungguKurang6)}</td>
          </tr></tfoot>
        </table>
        <p class="foot-note">${nf(pctTungguKurang6)}% lulusan bekerja dalam &lt; 6 bulan — indikator utama tracer study.</p>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Kesesuaian bidang kerja</h2>
          <span class="note">dari ${TRACER.bekerja} lulusan bekerja</span>
        </div>
        <table class="tbl">
          <thead><tr><th>Tingkat kesesuaian</th><th class="r" style="width:80px">Orang</th><th class="r" style="width:60px">%</th></tr></thead>
          <tbody>
          ${[
            ['Sesuai bidang keilmuan', TRACER.bidangSesuai, 'ok'],
            ['Sebagian sesuai', TRACER.bidangSebagian, ''],
            ['Tidak sesuai', TRACER.bidangTidak, 'bad'],
          ].map(([lab, n, cls]) => `<tr${cls === 'bad' ? ' class="is-bad"' : ''}>
            <td>${esc(lab)}</td>
            <td class="r num strong" style="${cls ? `color:var(--${cls})` : ''}">${n}</td>
            <td class="r num">${nf(n / TRACER.bekerja * 100)}</td>
          </tr>`).join('')}
          </tbody>
        </table>
        <p class="foot-note">${nf(TRACER.bidangSesuai / TRACER.bekerja * 100)}% bekerja di bidang keilmuan prodi.</p>
      </div>
    </div>`

  // ── Kisaran gaji ──
  + `<div class="card">
      <div class="card-head">
        <h2>Kisaran gaji pertama</h2>
        <span class="note">dari ${TRACER.bekerja} lulusan bekerja · Rupiah per bulan</span>
      </div>
      <div class="strip" style="margin:0">
        ${TRACER.gaji.map(([range, n]) =>
          `<div class="cell"><div class="val">${n}</div><div class="lab">${range}</div></div>`).join('')}
      </div>
      ${mbar(TRACER.gaji[1][1] / TRACER.bekerja * 100, '', 4)}
      <p class="foot-note">Mayoritas di rentang ${TRACER.gaji[1][0]} juta. ${TRACER.gaji[3][1]} orang di atas 8 juta — data ini opsional di instrumen tapi memperkuat narasi penyerapan.</p>
    </div>`;
}

export default page;
