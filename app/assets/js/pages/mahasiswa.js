/* mahasiswa — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

function page(state) {
  const feeds = (ang) => (FEEDS[ang] || []).map((t) => `<span class="tag">${t}</span>`).join(' ');
  return head('Mahasiswa',
    `F-05 · ${D.masuk} baris terdaftar · ${D.unik} orang unik · daftar per angkatan, bukan per orang`,
    '<button class="btn btn-ghost">Impor Excel</button><button class="btn btn-primary">+ Mahasiswa</button>')
  + `<div class="callout bad"><div class="c-body">
      <h3>Impor terakhir 6 Nov 2026 · 12 baris perlu diperbaiki</h3>
      <p>Data ini sumber Tabel 2, 3, 5, 9, 10, dan 11. Satu NIM ganda membuat dua tabel menghitung orang yang sama dua kali — <b>${D.masuk} baris terdaftar, ${D.unik} orang unik</b>.</p>
      <div class="row mt12" style="gap:26px">
        ${MASALAH_MHS.map(([lab, why, kind]) =>
          `<div><span style="color:var(--${kind});font-weight:700;font-size:10px">${esc(lab)}</span>
           <div class="foot-note" style="margin-top:2px">${esc(why)}</div></div>`).join('')}
      </div>
    </div><button class="btn btn-danger">Perbaiki 12 baris</button></div>`
  + strip([
    [`${D.lulus}`, 'lulus', 'ok'],
    [`${D.aktif}`, 'aktif', ''],
    [`${D.drop}`, 'DO', 'bad'],
    [`${D.cuti}`, 'cuti', 'muted'],
    [`${D.unik}`, 'orang unik', ''],
  ])
  + `<div class="card">
      <div class="card-head"><h2>Per angkatan</h2><span class="note">satu baris per angkatan — angka ini yang dibagi ke Tabel 2, 3, 5, 9, 10</span></div>
      <table class="tbl">
        <thead><tr><th>Angkatan</th><th>Tahun akademik</th><th class="r">Masuk</th><th class="r">Lulus</th>
          <th class="r">DO</th><th class="r">Aktif</th><th class="r">IPK rata</th>
          <th style="width:120px">Komposisi</th><th>Tabel</th></tr></thead>
        <tbody>
        ${ANGKATAN.map(([ang, th, m, l, d, c, ipk]) => {
          const a = m - l - d - c;
          return `<tr>
            <td class="num strong" ${ang === 'TS' ? 'style="color:var(--ind)"' : ''}>${ang}</td>
            <td class="num muted">${th}</td>
            <td class="r num">${m}</td><td class="r num" style="color:var(--ok)">${l}</td>
            <td class="r num" ${d ? 'style="color:var(--bad)"' : 'style="color:var(--ink4)"'}>${d}</td>
            <td class="r num">${a}</td>
            <td class="r num">${nf(ipk)}</td>
            <td><div class="mbar">
              <i class="ok" style="width:${(l / m) * 100}%"></i>
              ${d ? `<i class="bad" style="width:${(d / m) * 100}%"></i>` : ''}
              <i class="ind" style="width:${(a / m) * 100}%"></i>
            </div></td>
            <td class="tagcell">${feeds(ang)}</td>
          </tr>`;
        }).join('')}
        </tbody>
        <tfoot><tr>
          <td>Total</td><td></td>
          <td class="r num">${D.masuk}</td><td class="r num">${D.lulus}</td>
          <td class="r num" style="color:var(--bad)">${D.drop}</td><td class="r num">${D.aktif}</td>
          <td class="r num" style="color:var(--ind)">${nf(ANGKATAN.reduce((s, a) => s + a[6], 0) / ANGKATAN.length)}</td>
          <td colspan="2" class="muted" style="font-size:9px">rata-rata sederhana antar angkatan</td>
        </tr></tfoot>
      </table>
      <div class="legend mt12">
        <span><span class="swatch ok"></span> lulus</span>
        <span><span class="swatch bad"></span> DO</span>
        <span><span class="swatch ind"></span> aktif</span>
        <span class="muted">chip = nomor tabel yang memakai angkatan ini</span>
      </div>
      <p class="foot-note">Satu angkatan = satu baris. Mahasiswa yang pindah tahap akademik → profesi tetap di angkatannya, riwayat tahap disimpan terpisah.</p>
    </div>`;
}

export default page;
