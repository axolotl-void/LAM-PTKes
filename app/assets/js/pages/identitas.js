/* identitas — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

function page(state) {
  const kosong = [...UPPS, ...PRODI_FIELDS].filter((f) => f[3] === 'kosong');
  const terisi = UPPS.length + PRODI_FIELDS.length - kosong.length;
  const total = UPPS.length + PRODI_FIELDS.length;
  const jenjangAktif = PRODI_FIELDS.find((f) => f[0] === 'jenjang')[2];
  const j = JENJANG.find((x) => x.label === jenjangAktif) || JENJANG[0];
  const tabelNA = j.tabelNA;
  const wajib = INSTRUMEN.totalTabel - tabelNA;

  const field = ([key, label, val, status], bisaUbah) => {
    const missing = status === 'kosong';
    const lockField = key === KUNCI.menunggu;
    return `<div class="fld ${missing ? 'is-missing' : ''} ${lockField ? 'is-lock' : ''}">
      <span class="fld-lab">${esc(label)}</span>
      <span class="fld-val">${missing ? '<i>belum diisi</i>' : esc(val)}</span>
      ${lockField ? `<span class="fld-tag">menunggu buka kunci · nilai sekarang</span>` : ''}
    </div>`;
  };

  return head('Identitas UPPS & Prodi',
    `F-01 · ${total} field · ${terisi} terisi · ${kosong.length} kosong · menentukan ${wajib} tabel wajib`)
  + `<div class="callout lock"><div class="c-body">
      <h3>Terkunci — ${kosong.length + 1} hal di halaman ini</h3>
      <p>Data identitas dikunci setelah verifikasi ${esc(KUNCI.dikunci_pada)} oleh ${esc(KUNCI.dikunci_oleh)}.
      Field <b>${esc(KUNCI.menunggu)}</b> sedang diminta diubah oleh ${esc(KUNCI.permintaan_oleh)}
      (${esc(KUNCI.permintaan_pada)}): <i>${esc(KUNCI.alasan)}</i>.</p>
    </div><button class="btn btn-ghost btn-sm" disabled>Minta buka kunci</button></div>`
  + `<div class="idgrid">
      <section class="idcard">
        <div class="idhead"><h2>Profil UPPS</h2><span class="idn">${UPPS.filter((f) => f[3] === 'ok').length}/${UPPS.length}</span></div>
        ${UPPS.map((f) => field(f, false)).join('')}
      </section>
      <section class="idcard">
        <div class="idhead"><h2>Profil Program Studi</h2><span class="idn">${PRODI_FIELDS.filter((f) => f[3] === 'ok').length}/${PRODI_FIELDS.length}</span></div>
        ${PRODI_FIELDS.map((f) => field(f, false)).join('')}
      </section>
    </div>`
  + `<section class="idcard idcard-wide">
      <div class="idhead"><h2>Konsekuensi jenjang</h2>
        <span class="idn">${esc(j.label)}</span></div>
      <p class="idnote">Field <b>jenjang</b> dan <b>punya tahap profesi</b> menentukan tabel mana yang wajib
      diisi di seluruh aplikasi. Ini bukan preferensi tampilan — mengubahnya mengubah
      target ekspor.</p>
      <div class="conc">
        <div class="conc-cell">
          <span class="conc-lab">Tabel wajib</span>
          <b>${wajib}</b><span class="conc-sub">dari ${INSTRUMEN.totalTabel}</span>
        </div>
        <div class="conc-cell">
          <span class="conc-lab">Tidak berlaku</span>
          <b>${tabelNA}</b><span class="conc-sub">${tabelNA ? 'Khusus Profesi: Tabel ' + INSTRUMEN.tabelNA.join(' · ') : 'semua tabel berlaku'}</span>
        </div>
        <div class="conc-cell">
          <span class="conc-lab">Target dokumen</span>
          <b>${INSTRUMEN.totalDokumen}</b><span class="conc-sub">butir wajib — progres ${D.dokTerunggah} terunggah</span>
        </div>
      </div>
      ${tabelNA ? `<p class="idwarn">Kalau jenjang diubah ke <b>Sarjana dan Profesi</b>, ${tabelNA} tabel
        yang sekarang "tidak berlaku" akan jadi wajib — target naik dari ${wajib} ke ${INSTRUMEN.totalTabel}
        dan ${tabelNA * 2} butir dokumen baru muncul (79 tetap, tapi isinya bertambah).</p>` : ''}
    </section>`
  + `<section class="idcard idcard-wide">
      <div class="idhead"><h2>Riwayat perubahan</h2><span class="idn">${RIWAYAT.length} entri</span></div>
      <div class="rwt">
        ${RIWAYAT.map(([waktu, siapa, apa, jenis]) => `<div class="rwt-row">
          <span class="rwt-t">${esc(waktu)}</span>
          <span class="rwt-who">${esc(siapa)}</span>
          <span class="rwt-what">${esc(apa)}</span>
          <span class="badge ${jenis === 'menunggu' ? 'warn' : jenis === 'dikunci' ? 'lock' : 'na'}">${esc(jenis)}</span>
        </div>`).join('')}
      </div>
    </section>`;
}

export default page;
