/* kerjasama — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

// dipindah ke data.js saat backend ada
const MITRA = [
  { nama: 'RSUD Zainoel Abidin', jenis: 'Rumah Sakit', lok: 'Banda Aceh',
    mulai: 'Jun 2021', berakhir: 'Jun 2026', mou: 'berlaku',
    keg: { pen: 3, lit: 2, pkm: 4 }, tabel: ['30', '28'], laporan: 'ada' },
  { nama: 'RSIA Banda Aceh', jenis: 'Rumah Sakit', lok: 'Banda Aceh',
    mulai: 'Sep 2022', berakhir: 'Sep 2027', mou: 'berlaku',
    keg: { pen: 2, lit: 1, pkm: 2 }, tabel: ['30', '28'], laporan: 'ada' },
  { nama: 'RSJ Banda Aceh', jenis: 'Rumah Sakit', lok: 'Banda Aceh',
    mulai: 'Jun 2021', berakhir: 'Jun 2024', mou: 'kedaluwarsa',
    keg: { pen: 1, lit: 0, pkm: 1 }, tabel: ['30'], laporan: 'belum' },
  { nama: 'Puskesmas Kuta Alam', jenis: 'Puskesmas', lok: 'Banda Aceh',
    mulai: 'Jan 2023', berakhir: 'Jan 2028', mou: 'berlaku',
    keg: { pen: 4, lit: 0, pkm: 2 }, tabel: ['30'], laporan: 'ada' },
  { nama: 'Puskesmas Ulee Kareng', jenis: 'Puskesmas', lok: 'Banda Aceh',
    mulai: 'Mar 2023', berakhir: 'Mar 2028', mou: 'berlaku',
    keg: { pen: 3, lit: 1, pkm: 1 }, tabel: ['30'], laporan: 'ada' },
  { nama: 'BPM Cut Nyak Dhien', jenis: 'BPM', lok: 'Banda Aceh',
    mulai: 'Feb 2022', berakhir: 'Feb 2027', mou: 'berlaku',
    keg: { pen: 2, lit: 0, pkm: 0 }, tabel: ['30'], laporan: 'belum' },
  { nama: 'PMB Nurhasanah', jenis: 'BPM', lok: 'Banda Aceh',
    mulai: 'Jul 2024', berakhir: 'Jul 2029', mou: 'berlaku',
    keg: { pen: 1, lit: 0, pkm: 1 }, tabel: ['30'], laporan: 'ada' },
];

const KEG_TOTAL = { pen: 16, lit: 4, pkm: 11 };
const KEG_MAX = Math.max(KEG_TOTAL.pen, KEG_TOTAL.lit, KEG_TOTAL.pkm);

function page(state) {
  const exp = MITRA.filter((m) => m.mou === 'kedaluwarsa');
  const laporBelum = MITRA.filter((m) => m.laporan === 'belum');

  return head('Kerja Sama',
    `F-19 · Tabel 38 · ${D.wahana} mitra · ${D.mouValid} MoU berlaku · ${D.mouExpired} kedaluwarsa`,
    '<button class="btn btn-primary">+ MoU</button>')

  /* ── Banner gerbang ── */
  + callout('bad', 'Satu gerbang tertutup',
    `<p><b>${esc(exp[0].nama)}</b> — MoU berakhir ${esc(exp[0].berakhir)}, lewat ${new Date().getFullYear() - 2024} tahun. Kapasitas ${esc(exp[0].nama)} tidak dihitung ke Tabel 30 sampai dokumen diperbarui. Beda dengan "belum ada" (mitra tidak punya MoU sama sekali), yang ini <b>punya tapi kedaluwarsa</b> — asesor melihat riwayat, bukan kekosongan.</p>`,
    '<button class="btn btn-ghost btn-sm" style="border-color:var(--bad);color:var(--bad)">Perbaiki RSJ →</button>')

  /* ── Ringkasan strip ── */
  + strip([
    [`${MITRA.length}`, 'mitra kerja sama', ''],
    [`${D.mouValid}`, 'MoU berlaku', 'ok'],
    [`${D.mouExpired}`, 'kedaluwarsa', 'bad'],
    ['0', 'mitra luar negeri', 'muted'],
    [`${laporBelum.length}`, 'laporan belum ada', 'warn'],
  ])

  /* ── Tabel mitra urut berakhir terdekat ── */
  + `<div class="card">
    <div class="card-head"><h2>Daftar mitra</h2><span class="note">diurut berdasarkan tanggal berakhir terdekat · Tabel 38</span></div>
    <table class="tbl">
      <thead><tr>
        <th>Mitra</th><th>Jenis</th><th>Lokasi</th>
        <th>Mulai</th><th>Berakhir</th><th>MoU</th>
        <th class="r">Kegiatan</th><th>Tabel</th>
      </tr></thead>
      <tbody>
      ${MITRA
        .slice()
        .sort((a, b) => {
          const pa = Date.parse('01 ' + a.berakhir);
          const pb = Date.parse('01 ' + b.berakhir);
          return pa - pb;
        })
        .map((m) => {
          const tot = m.keg.pen + m.keg.lit + m.keg.pkm;
          const cls = m.mou === 'kedaluwarsa' ? 'is-bad' : (m.laporan === 'belum' ? 'is-warn' : '');
          return `<tr class="${cls}">
            <td class="strong">${esc(m.nama)}</td>
            <td class="muted">${esc(m.jenis)}</td>
            <td class="muted">${esc(m.lok)}</td>
            <td class="muted">${esc(m.mulai)}</td>
            <td class="${m.mou === 'kedaluwarsa' ? '' : 'muted'}">${esc(m.berakhir)}</td>
            <td>${badge(m.mou === 'berlaku' ? 'ok' : 'bad', m.mou)}</td>
            <td class="r num">${tot}</td>
            <td>${m.tabel.map((t) => `<span class="badge lock">T${esc(t)}</span>`).join(' ')}</td>
          </tr>`;
        }).join('')}
      </tbody>
    </table>
    <p class="foot-note">Urutan tanggal berakhir menempatkan ${esc(exp[0].nama)} paling atas — problem paling mendesak terlihat pertama. Baris berwarna: merah = kedaluwarsa, kuning = laporan pelaksanaan belum diunggah.</p>
  </div>`

  /* ── Rekap kategori kegiatan ── */
  + `<div class="card">
    <div class="card-head"><h2>Rekap kegiatan per kategori</h2><span class="note">satu skala untuk perbandingan proporsional</span></div>
    <div class="dlist" style="gap:14px">
      ${[
        ['Pendidikan', KEG_TOTAL.pen, 'var(--indigo)'],
        ['Penelitian', KEG_TOTAL.lit, 'var(--accent)'],
        ['PkM', KEG_TOTAL.pkm, 'var(--ok)'],
      ].map(([lab, val, col]) => {
        const pct = (val / KEG_MAX) * 100;
        return `<div class="item" style="align-items:flex-start">
          <span class="dot" style="background:${col}"></span>
          <div style="flex:1">
            <div class="spread"><h4>${esc(lab)}</h4>
              <span class="strong">${val} <span class="muted" style="font-size:9.5px">kegiatan</span></span></div>
            <div class="mbar" style="height:6px;margin-top:6px"><i style="width:${pct.toFixed(1)}%;background:${col}"></i></div>
          </div>
        </div>`;
      }).join('')}
    </div>
    <p class="foot-note">Total ${KEG_TOTAL.pen + KEG_TOTAL.lit + KEG_TOTAL.pkm} kegiatan dari ${MITRA.length} mitra. Penelitian paling sedikit — konsentrasi kegiatan ${KEG_TOTAL.pen > KEG_TOTAL.lit * 3 ? 'didominasi pendidikan' : 'relatif seimbang'}.</p>
  </div>`

  /* ── Panel perbaikan RSJ ── */
  + `<div class="card">
    <div class="card-head"><h2>Yang harus dilakukan untuk ${esc(exp[0].nama)}</h2><span class="note">urutan kerja</span></div>
    ${dlist([
      ['Perpanjang MoU',
       `Dokumen lama berakhir ${esc(exp[0].berakhir)}. Tanda tangan perpanjangan sebelum asesor datang — kalau tidak, kapasitasnya tetap 0 di Tabel 30.`,
       ],
      ['Unggah laporan pelaksanaan',
       `${exp[0].laporan === 'belum' ? 'Laporan belum ada di berkas. ' : ''}Asesor memeriksa bukti pelaksanaan, bukan hanya dokumen MoU.`,
       ],
      ['Verifikasi kapasitas',
       `Setelah MoU berlaku, kapasitas ${exp[0].nama} (${WAHANA.find((w) => w[0] === exp[0].nama)[3]} tempat) masuk kembali ke D.kapValid.`,
       ],
    ])}
    <p class="foot-note">Beda status: <b>kedaluwarsa</b> = punya MoU tapi lewat masa (RSJ). <b>Belum ada</b> = tidak punya dokumen sama sekali — tidak ada di daftar ini. Asesor memperlakukan keduanya berbeda: yang kedaluwarsa punya riwayat, yang belum ada tidak ada apa-apa.</p>
  </div>`;
}

export default page;
