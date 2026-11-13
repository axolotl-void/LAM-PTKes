/* kurikulum — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

function page(state) {
  const show = MK.slice(0, 11);
  return head('Kurikulum & MK',
    `F-07 · Tabel 1 · ${TOTAL_MK_INSTRUMEN} baris di instrumen · wajib impor, bukan ketik`,
    '<button class="btn btn-ghost">Template</button><button class="btn btn-primary">Impor Excel</button>')
  + `<div class="callout warn"><div class="c-body">
      <h3>Kurikulum terakhir diimpor 4 Nov 2026</h3>
      <p>${MK.length} baris contoh dari ${TOTAL_MK_INSTRUMEN} baris Tabel 1 ada di sistem. Sisa ${TOTAL_MK_INSTRUMEN - MK.length} baris belum masuk — selama belum, Tabel 1 tidak bisa diekspor.</p>
    </div><button class="btn btn-ghost" style="border-color:var(--warn);color:var(--warn)">Lanjutkan impor</button></div>`
  + strip([
    [`${D.mkAkademik}`, 'MK akademik', ''],
    [`${D.mkProfesi}`, 'MK profesi', 'muted'],
    [`${D.sksK}`, 'sks kuliah', ''],
    [`${D.sksP}`, 'sks praktik', ''],
    [`${D.cplKosong.length}`, 'CPL kosong', 'bad'],
    [`${D.rpsKosong.length}`, 'RPS kosong', 'bad'],
  ])
  + `<div class="card">
      <div class="card-head"><h2>Pratinjau MK</h2><span class="note">${MK.length} baris contoh · ${show.length} ditampilkan</span></div>
      <table class="tbl">
        <thead><tr><th>Kode</th><th>Mata kuliah</th><th class="r">sks k/p</th><th>Aspek penilaian</th>
          <th>Tahap</th><th>RPS</th><th>Lokasi</th></tr></thead>
        <tbody>
        ${show.map(([kode, nama, sk, sp, tahap, cpl, aspek, rps, lok]) => `<tr ${!cpl ? 'class="is-bad"' : ''}>
          <td class="num muted">${kode}</td>
          <td class="strong">${esc(nama)}${!cpl ? ' <span class="badge bad">CPL kosong</span>' : ''}</td>
          <td class="r num">${sk}/${sp}</td>
          <td><div class="letters">${aspek.map((v, i) => `<i class="${v ? 'on' : ''}">${ASPEK[i][0]}</i>`).join('')}</div></td>
          <td class="muted">${tahap}</td>
          <td>${rps ? '<span style="color:var(--ok);font-weight:700">✓</span>' : '<span class="badge bad">belum</span>'}</td>
          <td class="${sp > 0 && lok === '—' ? '' : 'muted'}">${esc(lok)}</td>
        </tr>`).join('')}
        </tbody>
      </table>
      <div class="legend mt12">
        ${ASPEK.map(([l, full], i) => `<span><i class="letters"><i class="on">${l}</i></i> ${esc(full)}</span>`).join('')}
      </div>
      <p class="foot-note">Menampilkan ${show.length} dari ${MK.length} baris contoh · ${TOTAL_MK_INSTRUMEN - MK.length} baris instrumen belum diimpor sama sekali.</p>
    </div>
    <div class="grid two">
      <div class="card">
        <div class="card-head"><h2>Pemeriksaan integritas</h2><span class="note">yang membuat Tabel 1 ditolak instrumen</span></div>
        <table class="tbl"><tbody>
          ${[['Setiap MK punya CPL', MK.length - D.cplKosong.length, MK.length, D.cplKosong.map((m) => m[0])],
             ['Setiap MK punya RPS', MK.length - D.rpsKosong.length, MK.length, D.rpsKosong.map((m) => m[0])],
             ['MK praktik punya lokasi', MK.filter((m) => m[3] > 0).length - D.lokasiKosong.length, MK.filter((m) => m[3] > 0).length, D.lokasiKosong.map((m) => m[0])],
             ['Setiap MK punya aspek', MK.length - D.aspekKosong.length, MK.length, []],
           ].map(([lab, done, tot, bad]) => `<tr>
            <td>${esc(lab)}</td>
            <td class="r num" style="color:var(--${done === tot ? 'ok' : 'bad'})">${done}/${tot}</td>
            <td><div class="mbar"><i class="${done === tot ? 'ok' : 'bad'}" style="width:${(done / tot) * 100}%"></i></div></td>
            <td class="num muted">${bad.join(', ') || 'lengkap'}</td>
          </tr>`).join('')}
        </tbody></table>
      </div>
      <div class="card">
        <div class="card-head"><h2>Kenapa impor, bukan form</h2></div>
        <div class="dlist">
          <div class="item"><span class="dot"></span><div><h4>${TOTAL_MK_INSTRUMEN} baris</h4><p>Mengetik satu per satu butuh ±4 jam dan rawan salah.</p></div></div>
          <div class="item"><span class="dot"></span><div><h4>13 kolom</h4><p>MK, CPL, sks kuliah, sks praktik, 4 aspek, RPS, lokasi, tahap.</p></div></div>
          <div class="item"><span class="dot"></span><div><h4>1 baris gagal = 1 baris ditolak</h4><p>Tidak ada impor sebagian yang diam-diam.</p></div></div>
        </div>
      </div>
    </div>`;
}

export default page;
