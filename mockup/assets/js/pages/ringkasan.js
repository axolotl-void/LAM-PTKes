/* ringkasan — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

function page(state) {
  const penuh = (k) => k.tabel[0] === k.tabel[1] && k.dok[0] === k.dok[1] && !k.temuan;
  const bt = (k) => k.tabel[1] - k.tabel[0];
  const bd = (k) => k.dok[1] - k.dok[0];
  const celah = (k) => bt(k) + bd(k);

  const urut = [...KRITERIA].sort((a, b) => celah(b) - celah(a));
  const tindakan = urut.filter((k) => !penuh(k));
  const lengkap = urut.filter(penuh);

  const sisaTabel = KRITERIA.reduce((s, k) => s + bt(k), 0);
  const sisaDok = KRITERIA.reduce((s, k) => s + bd(k), 0);
  const maxCelah = celah(tindakan[0]);
  const ke = (kode) => ({ K3: 'kurikulum', K4: 'mahasiswa', K5: 'dosen', K6: 'keuangan' }[kode] || 'datadukung');

  // kartu utama: satu-satunya yang dapat angka raksasa + pecahan dua kolom
  const hero = (k) => `<article class="kc kc-hero">
      <div class="kc-hero-top">
        <div>
          <span class="kc-tag">paling menghambat</span>
          <h3 class="kc-title">${esc(k.nama)}</h3>
          <p class="kc-find">${esc(k.temuan || '')}</p>
        </div>
        <div class="kc-hero-num">
          <b>${celah(k)}</b><em>celah</em>
        </div>
      </div>
      <div class="kc-hero-split">
        <div class="kc-split">
          <span class="kc-split-lab">Tabel</span>
          <b>${k.tabel[0]}<span>/${k.tabel[1]}</span></b>
          <span class="kc-split-miss">${bt(k)} belum</span>
        </div>
        <div class="kc-split">
          <span class="kc-split-lab">Dokumen</span>
          <b>${k.dok[0]}<span>/${k.dok[1]}</span></b>
          <span class="kc-split-miss">${bd(k)} belum</span>
        </div>
        <div class="kc-split kc-split-bar">
          <span class="kc-split-lab">${celah(k)} dari ${sisaTabel + sisaDok} celah total</span>
          <div class="kc-track"><i style="width:${(celah(k) / (sisaTabel + sisaDok) * 100).toFixed(1)}%"></i></div>
        </div>
      </div>
      <a class="kc-link" data-nav="${ke(k.kode)}">Buka ${k.kode} →</a>
    </article>`;

  // kartu biasa: padat, angka jadi fokus, tidak ada tombol besar
  const card = (k) => `<article class="kc">
      <div class="kc-head">
        <span class="kc-kode">${k.kode}</span>
        <span class="kc-num">${celah(k)}<i>celah</i></span>
      </div>
      <h3 class="kc-title">${esc(k.nama)}</h3>
      <div class="kc-track"><i style="width:${(celah(k) / (sisaTabel + sisaDok) * 100).toFixed(1)}%"></i></div>
      <div class="kc-meta">
        <span><b>${k.tabel[0]}/${k.tabel[1]}</b> tabel</span>
        <span><b>${k.dok[0]}/${k.dok[1]}</b> dokumen</span>
      </div>
      <p class="kc-find">${esc(k.temuan || '')}</p>
      <a class="kc-link" data-nav="${ke(k.kode)}">Buka ${k.kode} →</a>
    </article>`;

  return head('Ringkasan 8 Kriteria',
    `${INSTRUMEN.kriteria} kriteria · ${sisaTabel} tabel + ${sisaDok} dokumen belum lengkap · data per ${DATA_PER}`)
  + `<div class="rhead">
      <div class="rhead-num"><b>${lengkap.length}</b><span>/ ${INSTRUMEN.kriteria}</span><em>kriteria selesai</em></div>
      <div class="rhead-num"><b>${sisaTabel}</b><em>tabel belum</em></div>
      <div class="rhead-num"><b>${sisaDok}</b><em>dokumen belum</em></div>
      <p class="rhead-note"><b>celah</b> = tabel + dokumen yang belum masuk.
        Kartu diurut dari celah terbesar; panjang bar menunjukkan andilnya
        terhadap ${sisaTabel + sisaDok} celah total.</p>
    </div>`
  + `<section class="lsec">
      <div class="lsec-head"><h2>Butuh tindakan</h2><span class="lsec-n">${tindakan.length} kriteria</span></div>
      ${hero(tindakan[0])}
      <div class="kgrid kgrid-minor">
        ${tindakan.slice(1).map(card).join('')}
      </div>
    </section>`
  + (lengkap.length ? `<section class="lsec">
      <div class="lsec-head"><h2>Selesai</h2><span class="lsec-n">${lengkap.length} kriteria</span></div>
      <div class="kstrip">
        ${lengkap.map((k) => `<div class="kstrip-row">
          <span class="kc-kode ok">${k.kode}</span>
          <span class="kstrip-nama">${esc(k.nama)}</span>
          <span class="kstrip-meta"><b>${k.tabel[0]}/${k.tabel[1]}</b> tabel ·
            <b>${k.dok[0]}/${k.dok[1]}</b> dokumen</span>
          <span class="kstrip-ok">selesai</span>
        </div>`).join('')}
      </div>
    </section>` : '')
  + `<p class="lfoot">K1 tidak punya tabel data dukung — buktinya 4 dokumen pendukung saja.
      ${KRITERIA.filter((k) => k.tabel[1] > 0).length} kriteria lain punya tabel;
      totalnya ${KRITERIA.reduce((s2, k) => s2 + k.tabel[1], 0)} sesuai instrumen.</p>`;
}

export default page;
