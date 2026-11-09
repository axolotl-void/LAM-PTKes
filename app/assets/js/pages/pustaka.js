/* pustaka — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

function page(state) {
  return head('Pustaka & Jurnal',
  `F-16 · Tabel 16 · 17 · 18 · ${D.judulCetak} judul cetak · ${D.judulDigital} judul digital · ${D.langgananTotal} langganan`,
  '<button class="btn btn-primary">+ Koleksi</button>')
+ `<div class="callout lock"><div class="c-body">
    <h3>Tiga hal yang tidak boleh dijumlahkan</h3>
    <p>Satu paket langganan berisi ribuan judul. Buku tercetak dihitung per eksemplar, judul digital per judul, langganan per paket. Menjumlahkan ketiganya membuat angka Tabel 17 membengkak.</p>
    <div class="row mt12" style="gap:28px;font-size:9.5px;color:var(--ink3)">
      <span><b style="color:var(--ink2)">eksemplar</b> · 1 buku = 1 eksemplar</span>
      <span><b style="color:var(--ink2)">judul</b> · 1 judul digital = 1</span>
      <span><b style="color:var(--ink2)">paket</b> · 1 database = 1 langganan</span>
    </div>
  </div></div>`
+ strip([
  [`${D.judulCetak}`, 'judul tercetak', ''],
  [`${D.ekspCetak}`, 'eksemplar', ''],
  [nf(D.rasio), 'eksemplar/mahasiswa', 'ok'],
  [`${D.langgananAktif}/${D.langgananTotal}`, 'langganan aktif', 'warn'],
  [`${D.judulDigital}`, 'judul digital', ''],
])
+ `<div class="grid two">
    <div class="card">
      <div class="card-head"><h2>Koleksi</h2><span class="note">Tabel 16 · cetak dan digital dipisah</span></div>
      <table class="tbl">
        <thead><tr><th>Jenis koleksi</th><th class="r">Judul</th><th class="r">Eksemplar</th></tr></thead>
        <tbody>
        ${KOLEKSI.map(([jenis, judul, eksp, digital]) => `<tr>
          <td>${esc(jenis)}${digital && !/digital|elektronik/i.test(jenis)
            ? ' <span class="badge lock">digital</span>' : ''}</td>
          <td class="r num">${nf(judul)}</td>
          <td class="r num ${digital ? 'muted' : ''}">${digital ? '—' : nf(eksp)}</td>
        </tr>`).join('')}
        </tbody>
      </table>
      <p class="foot-note">${D.judulCetak} judul / ${D.ekspCetak} eksemplar tercetak · ${D.judulDigital} judul digital tanpa eksemplar.</p>
    </div>
    <div class="card">
      <div class="card-head"><h2>Langganan jurnal</h2><span class="note">Tabel 17 · dihitung per paket</span></div>
      ${LANGGANAN.map(([nama, jenis, sampai, st]) => {
        const exp = st === 'kedaluwarsa';
        return `<div class="drawer" style="margin-bottom:8px;padding:10px 14px;${exp ? 'background:var(--bad-bg);border-color:var(--bad)' : ''}">
          <div class="spread"><b style="font-size:10.5px;${exp ? 'color:var(--bad)' : ''}">${esc(nama)}</b>
            ${jenis === 'terbuka' ? badge('ok', 'terbuka') : badge(exp ? 'bad' : 'lock', exp ? 'kedaluwarsa' : 'berlangganan')}</div>
          <div class="foot-note" style="margin-top:3px">
            ${jenis === 'terbuka' ? 'tidak dihitung sebagai langganan berbayar' : `s.d. ${esc(sampai)}`}</div>
        </div>`;
      }).join('')}
      <p class="foot-note">${D.langgananAktif} dari ${D.langgananTotal} berlangganan masih aktif. Yang kedaluwarsa tetap terdaftar — datanya masih dipakai, tapi Tabel 17 tidak menghitungnya.</p>
    </div>
  </div>
  <div class="grid two">
    <div class="card">
      <div class="card-head"><h2>Pemenuhan standar minimum</h2><span class="note">angka minimum dari instrumen</span></div>
      <table class="tbl"><tbody>
        ${[['Judul buku cetak + digital', D.judulCetak + D.judulDigital, 500, `${D.judulCetak} + ${D.judulDigital}`],
           ['Eksemplar per mahasiswa', D.rasio, 1, `${D.ekspCetak} / ${D.masuk}`],
           ['Jurnal berlangganan aktif', D.langgananAktif, 2, 'dari daftar di atas'],
        ].map(([lab, val, min, src]) => {
          const ok = val >= min;
          return `<tr><td>${esc(lab)}</td>
            <td class="r num strong" style="color:var(--${ok ? 'ok' : 'bad'})">${nf(val)}</td>
            <td class="r muted" style="font-size:9.5px">/ min ${nf(min)}</td>
            <td class="muted" style="font-size:9px">${esc(src)}</td></tr>`;
        }).join('')}
      </tbody></table>
    </div>
    <div class="card">
      <div class="card-head"><h2>Akses & pemakaian</h2><span class="note">Tabel 18</span></div>
      <table class="tbl"><tbody>
        ${AKSES.map(([lab, val]) => `<tr><td>${esc(lab)}</td><td class="r num strong">${val}</td></tr>`).join('')}
        <tr><td>Mahasiswa pernah mengakses</td><td class="r num strong" style="color:var(--ok)">398 dari ${D.masuk}</td></tr>
      </tbody></table>
    </div>
  </div>`;
}

export default page;
