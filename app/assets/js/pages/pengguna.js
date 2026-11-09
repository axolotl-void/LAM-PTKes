/* pengguna — F-22. Halaman ini soal PEMISAHAN TUGAS, bukan daftar akun.
 *
 * Aturan yang dipegang:
 * - Operator (mengisi) dan Verifikator (menyetujui) tidak boleh satu orang.
 * - Admin UPPS memegang keduanya, karena itu dibatasi 1 akun.
 * - Konflik ditandai di UI DAN ditegakkan server (db._konflik).
 *
 * Kalau DB hidup, data akun diambil dari SQLite; kalau tidak, dari data.js.
 */
import { LIVE, api } from '../api.js';
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

const PERAN_PANJANG = ['Operator Prodi', 'Verifikator', 'Admin UPPS', 'Pimpinan'];

/* Akun dari DB kalau ada, kalau tidak dari konstanta. Bentuknya disamakan. */
function akun() {
  if (!LIVE.siap || !LIVE.pengguna) {
    return PENGGUNA.map(([nama, peran, cak, st, aktif], i) => ({
      id: i + 1, nama, peran, prodi: cak, status: st, aktif_terakhir: aktif,
    }));
  }
  return LIVE.pengguna;
}

function ringkas(rows) {
  const aktif = rows.filter((r) => r.status === 'aktif').length;
  const konflik = rows.filter((r) => r.status === 'konflik').length;
  const nonaktif = rows.filter((r) => r.status === 'nonaktif').length;
  const peran = new Set(rows.flatMap((r) => r.peran.split('+').map((p) => p.trim())));
  return { aktif, konflik, nonaktif, peran: peran.size };
}

function page(state) {
  const rows = akun();
  const r = ringkas(rows);
  const konflikRows = rows.filter((x) => x.status === 'konflik');

  return head('Pengguna & Hak Akses',
    `F-22 · ${rows.length} akun · ${r.peran} peran · yang diperiksa bukan jumlah akun, tapi pemisahan tugas`)

  // ── callout konflik: hanya muncul kalau memang ada ──────────────────
  + (konflikRows.length ? `<div class="callout bad"><div class="c-body">
      <h3>${konflikRows.length} akun memegang peran yang tidak boleh digabung</h3>
      <p>${esc(konflikRows.map((x) => x.nama.split(',')[0]).join(', '))} mengisi data sekaligus
      memverifikasinya. Artinya data yang dia isi bisa dia setujui sendiri — asesor akan
      menanyakan itu, dan ekspor tetap terkunci sampai dibereskan.</p>
      <div class="row mt8" style="font-size:10px;color:var(--ink3)">
        <span><b>Operator</b> · mengisi</span><span style="color:var(--bad);font-weight:700">✕</span>
        <span><b>Verifikator</b> · menyetujui</span>
      </div>
    </div>
    <button class="btn btn-danger" data-pisah="${konflikRows[0].id}">Pisahkan peran</button></div>`
    : `<div class="callout ok"><div class="c-body">
      <h3>Tidak ada akun dengan peran ganda</h3>
      <p>Semua akun yang mengisi berbeda dari yang menyetujui. Pemisahan tugas terpenuhi —
      ini yang membuka salah satu dari ${D.penghalang.length} penghalang ekspor.</p>
    </div></div>`)

  + strip([
    [`${r.aktif}`, 'aktif tanpa konflik', r.aktif ? 'ok' : ''],
    [`${r.konflik}`, 'konflik peran', r.konflik ? 'bad' : 'ok'],
    [`${r.nonaktif}`, 'nonaktif', 'muted'],
    [`${r.peran}`, 'peran dipakai', ''],
    ['0', 'tanpa verifikasi 2 langkah', 'ok'],
  ])

  // ── matriks izin ───────────────────────────────────────────────────
  + `<div class="card">
    <div class="card-head"><h2>Peran menentukan izin</h2>
      <span class="note">satu-satunya tempat aturan ini ditulis</span></div>
    <table class="tbl matrix">
      <thead><tr><th>Peran</th>${IZIN.map((i) => `<th class="r">${i}</th>`).join('')}</tr></thead>
      <tbody>
      ${Object.entries(MATRIX).map(([peran, row]) => `<tr>
        <td class="strong">${esc(peran)}${peran === 'Admin UPPS' ? ' <span class="badge warn">maks 1 akun</span>' : ''}</td>
        ${row.map((v) => `<td class="r">${v === 1 ? '<span class="yes">✓</span>' : v === 2 ? '<span class="viewonly">M</span>' : '<span class="no">—</span>'}</td>`).join('')}
      </tr>`).join('')}
      </tbody>
    </table>
    <div class="legend mt12"><span>✓ boleh</span><span><span class="viewonly">M</span> hanya lihat</span><span>— tidak boleh</span></div>
    <p class="foot-note">Operator dan Verifikator tidak boleh dipegang satu orang.
    Admin UPPS memegang keduanya — karena itu dibatasi 1 akun.</p>
  </div>`

  + `<div class="grid two">
    <div class="card">
      <div class="card-head"><h2>Akun</h2>
        <span class="note">${rows.length} akun · ${r.nonaktif} nonaktif</span></div>
      <table class="tbl">
        <thead><tr><th>Nama</th><th>Peran</th><th>Cakupan</th><th>Status</th><th class="r">Aksi</th></tr></thead>
        <tbody>
        ${rows.map((p) => `<tr class="${p.status === 'konflik' ? 'is-bad' : ''}">
          <td><div class="strong">${esc(p.nama)}</div>
            <div class="muted" style="font-size:10px">aktif ${esc(p.aktif_terakhir || '—')}</div></td>
          <td class="${p.status === 'konflik' ? 'strong' : 'muted'}" ${p.status === 'konflik' ? 'style="color:var(--bad)"' : ''}>
            ${esc(p.peran)}</td>
          <td class="muted">${esc(p.prodi || '—')}</td>
          <td>${badge(p.status === 'aktif' ? 'ok' : p.status === 'konflik' ? 'bad' : 'na', p.status)}</td>
          <td class="r">
            ${p.status === 'konflik'
              ? `<button class="lnk pu-pisah" data-pisah="${p.id}">Pisahkan →</button>`
              : p.status === 'nonaktif'
                ? `<button class="lnk pu-toggle" data-id="${p.id}" data-status="aktif">Aktifkan →</button>`
                : `<button class="lnk pu-toggle" data-id="${p.id}" data-status="nonaktif">Nonaktifkan →</button>`}
          </td>
        </tr>`).join('')}
        </tbody>
      </table>
      <p class="foot-note">Akun nonaktif tetap disimpan supaya riwayat pengisian tidak
      hilang. Menonaktifkan bukan menghapus.</p>
    </div>
    <div class="card">
      <div class="card-head"><h2>Permintaan buka kunci</h2>
        <span class="note">${D.menunggu} menunggu</span></div>
      ${PERMINTAAN.map(([mod, field, siapa, kapan, st]) => `
        <div class="drawer" style="margin-bottom:10px;${st === 'menunggu' ? 'background:var(--warn-bg);border-color:var(--warn)' : ''}">
          <h3>${esc(mod)}</h3>
          <div class="muted" style="font-size:10px">${esc(field)}</div>
          <div class="foot-note">${esc(siapa)} · ${esc(kapan)}</div>
          ${st === 'menunggu'
            ? `<div class="mt8 pu-pending">Belum bisa diputuskan dari layar ini —
               persetujuan butuh login Admin UPPS.</div>`
            : '<div class="mt8" style="color:var(--ok);font-size:10px;font-weight:600">✓ disetujui</div>'}
        </div>`).join('')}
      <p class="foot-note" style="margin-top:2px">Panel ini daftar permintaan, bukan
      tempat memutuskan. Tombol setujui/tolak muncul setelah login aktif — tanpa sesi,
      siapa pun bisa menyetujui permintaan siapa pun.</p>
      <div class="card-head mt16"><h2>Yang dicatat sistem</h2></div>
      <div class="dlist">
        <div class="item"><span class="dot"></span><div><h4>Setiap buka kunci</h4><p>siapa, kapan, field apa, alasan</p></div></div>
        <div class="item"><span class="dot"></span><div><h4>Setiap verifikasi</h4><p>tidak bisa oleh pengisi data yang sama</p></div></div>
        <div class="item"><span class="dot"></span><div><h4>Setiap perubahan peran</h4><p>peran lama → baru, oleh siapa</p></div></div>
      </div>
    </div>
  </div>`;
}

export const css = `
.pu-pisah, .pu-toggle { background:none; border:0; padding:0; cursor:pointer;
  font:inherit; color:var(--ind); font-weight:600; }
.pu-pisah { color:var(--bad); }
.pu-pisah:hover, .pu-toggle:hover { text-decoration:underline; }
.pu-pending { font-size:10px; color:var(--warn); line-height:1.5;
  border-left:2px solid var(--warn); padding-left:8px; }
.callout.ok { background:var(--ok-bg); border-color:#ABEFC6; }
.callout.ok h3 { color:var(--ok); }
`;

export default page;
