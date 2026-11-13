/* sarana — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

function page(state) {
  return head('Sarana & Wahana',
  `F-15 · Tabel 15 · 28 · 30 · 36 · ${D.wahana} wahana praktik · ${SARANA.length} jenis sarana umum`,
  '<button class="btn btn-primary">+ Sarana</button>')
+ `<div class="callout warn"><div class="c-body">
    <h3>Dua aturan yang menyilang halaman ini</h3>
    <p><b>1.</b> ${D.mouExpired} wahana MoU-nya kedaluwarsa (RSJ Banda Aceh) — tidak dihitung ke
      kapasitas Tabel 30. Perpanjang sebelum asesor datang — MoU wajib untuk jenjang ini.</p>
    <p><b>2.</b> Satu ruangan tidak boleh dihitung dua kali oleh Tabel 15 dan Tabel 30.</p>
  </div><button class="btn btn-ghost btn-sm" style="border-color:var(--warn);color:var(--warn)">Periksa MoU →</button></div>`
+ strip([
  [`${D.wahana}`, 'wahana praktik', ''],
  [`${D.mouValid}`, 'MoU berlaku', 'ok'],
  [`${D.mouExpired}`, 'MoU kedaluwarsa', 'bad'],
  [`${D.kapValid}`, 'kapasitas dihitung', ''],
  [`${D.saranaBaik}/${D.saranaUnit}`, 'unit kondisi baik', ''],
])
+ `<div class="card">
    <div class="card-head"><h2>Wahana praktik</h2><span class="note">Tabel 30 · khusus tahap profesi · MoU berlaku adalah syarat</span></div>
    <table class="tbl">
      <thead><tr><th>Wahana</th><th>Jenis</th><th>Lokasi</th><th class="r">Kap.</th><th>MoU</th></tr></thead>
      <tbody>
      ${WAHANA.map(([nama, jenis, lok, kap, mou]) => `<tr class="${mou ? '' : 'is-bad'}">
        <td class="strong">${esc(nama)}</td>
        <td class="muted">${esc(jenis)}</td>
        <td class="muted">${esc(lok)}</td>
        <td class="r num ${mou ? '' : 'muted'}">${mou ? kap : `<s>${kap}</s>`}</td>
        <td>${mou ? '<span style="color:var(--ok);font-size:9.5px;font-weight:600">✓ berlaku</span>'
                  : '<span style="color:var(--bad);font-size:9.5px;font-weight:600">kedaluwarsa · tidak dihitung</span>'}</td>
      </tr>`).join('')}
      </tbody>
    </table>
    <p class="foot-note">Kapasitas dihitung ${D.kapValid} dari ${D.kapValid + D.kapDitolak} — ${D.kapDitolak} milik RSJ tidak masuk sampai MoU diperbarui.</p>
  </div>
  <div class="grid two">
    <div class="card">
      <div class="card-head"><h2>Sarana umum</h2><span class="note">Tabel 15 · ${D.saranaSebagian} unit kondisi sebagian</span></div>
      <table class="tbl">
        <thead><tr><th>Jenis sarana</th><th class="r">Jumlah</th><th>Kondisi</th><th>Lokasi</th></tr></thead>
        <tbody>
        ${SARANA.map(([nama, jml, kon, lok]) => `<tr class="${kon === 'sebagian' ? 'is-warn' : ''}">
          <td class="strong">${esc(nama)}</td><td class="r num">${jml}</td>
          <td>${badge(kon === 'baik' ? 'ok' : 'warn', kon)}</td>
          <td class="muted">${esc(lok)}</td>
        </tr>`).join('')}
        </tbody>
      </table>
    </div>
    <div class="card">
      <div class="card-head"><h2>Keselamatan & kesehatan kerja</h2><span class="note">Tabel 36</span></div>
      <table class="tbl"><tbody>
        ${K3.map(([nama, jml, st, ok]) => `<tr>
          <td>${esc(nama)}</td><td class="num muted">${esc(jml)}</td>
          <td class="${ok ? '' : ''}" style="color:var(--${ok ? 'ok' : 'bad'});font-weight:600;font-size:9.5px">${esc(st)}</td>
        </tr>`).join('')}
      </tbody></table>
      <p class="foot-note">Wahana dan sarana dipisah karena aturannya beda: wahana butuh MoU, sarana butuh lokasi unik.</p>
    </div>
  </div>`;
}

export default page;
