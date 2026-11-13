/* dokumen — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

/* ── data butir dokumen — dipindah ke data.js saat backend ada ──────
   7 dokumen terunggah yang kedaluwarsa (SUBSET dari 41 terunggah).
   Tiap butir: [nama, kriteria, tanggal unggah, tanggal kedaluwarsa]. */
export const KEDALUWARSA = [
  ['SK Dekan Kurikulum 2020', 'K2', '03 Sep 2020', '03 Sep 2025'],
  ['Laporan Audit Internal 2021', 'K7', '15 Nov 2021', '15 Nov 2025'],
  ['Sertifikat Akreditasi Prodi', 'K1', '20 Jan 2021', '20 Jan 2026'],
  ['SK Struktur Organisasi 2021', 'K8', '01 Feb 2021', '01 Feb 2026'],
  ['MoU Dinas Kesehatan 2020', 'K6', '10 Mar 2020', '10 Mar 2025'],
  ['Laporan Penelitian Dosen 2021', 'K5', '28 Des 2021', '28 Des 2025'],
  ['Sertifikat Lab Kompetensi', 'K6', '05 Apr 2021', '05 Apr 2025'],
];

/* Dokumen belum diunggah di K5 (Dosen & Tendik) — kriteria dengan sisa
   terbanyak (13 belum). Butir contoh, bukan daftar lengkap. */
export const K5_BELUM = [
  ['CV dosen tetap (3 orang)', 'K5', 'butir baru'],
  ['Sertifikat pendidik 4 dosen', 'K5', 'butir baru'],
  ['Daftar publikasi 3 tahun terakhir', 'K5', 'per tahun TS-2 s.d. TS'],
  ['Laporan PkM', 'K5', 'siklus terakhir'],
  ['SK pembimbing akademik', 'K5', 'butir baru'],
  ['Bukti pelaksanaan evaluasi dosen', 'K5', 'siklus terakhir'],
  ['STR aktif dosen praktisi', 'K5', 'per individu'],
  ['Daftar tendik + sertifikat', 'K5', 'butir baru'],
  ['Laporan beban kerja dosen TS-1', 'K5', 'persemester'],
  ['Bukti pengembangan SDM', 'K5', 'siklus terakhir'],
  ['SK panitia penelitian', 'K5', 'siklus terakhir'],
  ['Daftar anggota penelitian', 'K5', 'per tim'],
  ['Laporan kepatuhan etik', 'K5', 'siklus terakhir'],
];

/* ── assert: angka harus konsisten dengan data.js ────────────────── */
const _sumDone = KRITERIA.reduce((s, k) => s + k.dok[0], 0);
const _sumTot  = KRITERIA.reduce((s, k) => s + k.dok[1], 0);
if (_sumDone !== DOKUMEN.terunggah)
  throw new Error(`Σ dok[done] ${_sumDone} ≠ DOKUMEN.terunggah ${DOKUMEN.terunggah}`);
if (_sumTot !== DOKUMEN.total)
  throw new Error(`Σ dok[total] ${_sumTot} ≠ DOKUMEN.total ${DOKUMEN.total}`);
if (_sumTot - _sumDone !== DOKUMEN.total - DOKUMEN.terunggah)
  throw new Error('Σ belum tidak tutup');
if (DOKUMEN.kedaluwarsa > DOKUMEN.terunggah)
  throw new Error('kedaluwarsa bukan subset terunggah');

export const css = `
  .dok-kedal { background: var(--bad-bg); border-color: var(--bad); }
  .dok-kedal .dok-nama { color: var(--bad); }
  .dok-belum .dok-nama { color: var(--ink2); }
  .dok-tbl td.dok-st { white-space: nowrap; }
  .dok-tbl tfoot td { font-weight: 600; }
  .dok-perhatian .card-head { border-bottom-color: var(--orn); }
`;

function page(state) {
  const belum = DOKUMEN.total - DOKUMEN.terunggah;
  const pctTerunggah = (DOKUMEN.terunggah / DOKUMEN.total * 100);
  const kSisa = (k) => k.dok[1] - k.dok[0];

  /* breakdown per kriteria */
  const rowsK = KRITERIA.map((k) => {
    const s = kSisa(k);
    const warn = s === 0 ? 'ok' : s === k.dok[1] ? 'bad' : 'warn';
    return {
      cls: s === 0 ? '' : 'dok-belum',
      cells: [
        `<b>${k.kode}</b> ${esc(k.nama)}`,
        `<span class="num">${k.dok[1]}</span>`,
        `<span class="num">${k.dok[0]}</span>`,
        `<span class="num ${warn}"><b>${s}</b></span>`,
      ],
    };
  });

  const footK = [
    '<b>Total</b>',
    `<span class="num"><b>${DOKUMEN.total}</b></span>`,
    `<span class="num"><b>${DOKUMEN.terunggah}</b></span>`,
    `<span class="num bad"><b>${belum}</b></span>`,
  ];

  return head('Dokumen Pendukung',
    `${DOKUMEN.total} butir bukti · ${DOKUMEN.terunggah} terunggah · ${belum} belum · data per ${DATA_PER}`)
  + strip([
      [`${DOKUMEN.total}`, 'total butir', ''],
      [`${DOKUMEN.terunggah}`, 'terunggah', 'ok'],
      [`${belum}`, 'belum diunggah', 'warn'],
      [`${DOKUMEN.kedaluwarsa}`, 'kedaluwarsa', 'bad'],
    ])
  + callout('warn', 'Kedaluwarsa adalah subset terunggah',
      `<p>${DOKUMEN.kedaluwarsa} dari ${DOKUMEN.terunggah} dokumen yang sudah diunggah
        sudah melewati masa berlaku. Mereka <b>tidak dihitung ulang</b> ke 38 belum —
        kedaluwarsa tetap menempati slot terunggah, hanya berstatus kedaluwarsa.
        Total tetap ${DOKUMEN.total}: ${DOKUMEN.terunggah} terunggah (di dalamnya
        ${DOKUMEN.kedaluwarsa} kedaluwarsa) + ${belum} belum.</p>`)
  + mbar(pctTerunggah, 'ok', 8)
  + `<p class="foot-note" style="margin-top:4px">
      ${DOKUMEN.terunggah} dari ${DOKUMEN.total} butir terunggah
      (${pctTerunggah.toFixed(1)}%). Bar menunjukkan proporsi terunggah terhadap total,
      bukan target — instrumen tidak menetapkan batas minimum berbeda dari total.</p>`
  + `<div class="card" style="margin-top:14px">
      <div class="card-head"><h2>Breakdown per kriteria</h2>
        <span class="note">K1–K8 · angka dari KRITERIA[i].dok</span></div>
      ${table(
        [['Kriteria', ''], ['Total', 'r'], ['Terunggah', 'r'], ['Belum', 'r']],
        rowsK,
        { foot: footK, cls: 'dok-tbl' })}
      <p class="foot-note">K1 dan K2 penuh — 0 belum. K5 paling banyak sisa
        (${kSisa(KRITERIA[4])} dari ${KRITERIA[4].dok[1]} butir), diikuti K6
        (${kSisa(KRITERIA[5])} dari ${KRITERIA[5].dok[1]}).</p>
    </div>`
  + `<div class="grid two dok-perhatian">
      <div class="card">
        <div class="card-head"><h2>Kedaluwarsa</h2>
          <span class="note">${DOKUMEN.kedaluwarsa} butir · harus diperbarui</span></div>
        <table class="tbl dok-tbl">
          <thead><tr><th>Dokumen</th><th>Kriteria</th><th class="r">Berlaku s.d.</th></tr></thead>
          <tbody>
          ${KEDALUWARSA.map(([nm, kr, , sd]) => `<tr class="dok-kedal">
            <td class="dok-nama">${esc(nm)}</td>
            <td><b>${kr}</b></td>
            <td class="r num">${esc(sd)}</td>
          </tr>`).join('')}
          </tbody>
        </table>
        <p class="foot-note">Semua 7 butir sudah diunggah tapi melewati masa berlaku.
          Perbarui dokumen lalu unggah ulang — slot sudah ada, tidak menambah jumlah belum.</p>
      </div>
      <div class="card">
        <div class="card-head"><h2>Belum diunggah — K5</h2>
          <span class="note">${kSisa(KRITERIA[4])} dari ${belum} belum · sisa terbanyak</span></div>
        <table class="tbl dok-tbl">
          <thead><tr><th>Dokumen</th><th>Kriteria</th><th>Catatan</th></tr></thead>
          <tbody>
          ${K5_BELUM.map(([nm, kr, ct]) => `<tr class="dok-belum">
            <td class="dok-nama">${esc(nm)}</td>
            <td><b>${kr}</b></td>
            <td class="muted" style="font-size:9.5px">${esc(ct)}</td>
          </tr>`).join('')}
          </tbody>
        </table>
        <p class="foot-note">K5 Dosen &amp; Tendik menyumbang ${kSisa(KRITERIA[4])} dari
          ${belum} belum (${(kSisa(KRITERIA[4]) / belum * 100).toFixed(0)}%).
          Setelah K5, K6 (${kSisa(KRITERIA[5])}) dan K3/K4
          (masing-masing ${kSisa(KRITERIA[2])}) menjadi prioritas berikutnya.</p>
      </div>
    </div>`
  + `<div class="card" style="margin-top:14px">
      <div class="card-head"><h2>Format &amp; syarat unggah</h2>
        <span class="note">berlaku semua butir</span></div>
      ${dlist([
        ['Format PDF', 'File wajib PDF. Scan atau ekspor dari dokumen asli — tidak menerima Word, JPG, atau ZIP.'],
        ['Maksimum 5 MB', 'Ukuran per file tidak boleh melebihi 5 MB. Kompresi PDF jika perlu, tetapi teks harus tetap terbaca.'],
        ['Bertanggal', 'Setiap dokumen harus mencantumkan tanggal terbit atau tanggal persetujuan. Dokumen tanpa tanggal akan ditolak saat verifikasi.'],
        ['Bertanda tangan', 'Harus ada tanda tangan pejabat berwenang dan stempel lembaga. Tanda tangan elektronik tersertifikasi (TTE) diterima bila memenuhi UU ITE.'],
        ['Nama file', 'Pola: <code>K[r]-[nomor]-[singkat].pdf</code>, contoh <code>K5-03-cv.pdf</code>. Hindari spasi dan karakter khusus.'],
      ])}
      <p class="foot-note">Dokumen yang kedaluwarsa tetap mengikuti syarat yang sama saat diperbarui.
        Verifikasi ulang menolak dokumen tanpa tanggal atau tanda tangan, bahkan jika format PDF dan ukuran sudah benar.</p>
    </div>`;
}

export default page;
