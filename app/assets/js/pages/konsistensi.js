/* konsistensi — dipisah dari views.js agar bisa dikerjakan paralel. */
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

// dipindah ke data.js saat backend ada
// [id, nama aturan, tabelA, nilaiA, satuanA, tabelB, nilaiB, satuanB, status, jenis, catatan]
// status: lulus | gagal ; jenis: blokir | peringatan | —
const ATURAN = [
  ['R1', 'Lulusan tepat waktu = lulusan tercatat TS-2',
    'Tabel 5', 41, 'lulusan', 'Tabel 2', 44, 'lulusan',
    'gagal', 'blokir', 'Tabel 5 catat 41 lulusan tepat waktu, Tabel 2 catat 44 lulusan TS-2. Selisih 3 lulusan hilang dari Tabel 5 — periksa apakah 3 lulusan terlambat atau belum dimasukkan.'],
  ['R2', 'Penggunaan dana = 100% anggaran TS-1',
    'Tabel 34', '99,9', '%', 'Tabel 33', '100', '%',
    'gagal', 'blokir', 'Tabel 34 laporkan penggunaan 99,9%, Tabel 33 tetapkan 100%. Selisih 0,1% = sisa anggaran belum direalisasi atau belum dicatat. Tabel 34 harus diperbaiki.'],
  ['R3', 'Jumlah mahasiswa aktif konsisten antar tabel',
    'Tabel 9', D.aktif, 'mahasiswa', 'Tabel 10', D.aktif, 'mahasiswa',
    'lulus', '—', 'Tabel 9 dan Tabel 10 sama-sama mencatat ' + D.aktif + ' mahasiswa aktif. Tidak ada selisih.'],
  ['R4', 'Setiap wahana praktik punya MoU berlaku',
    'Tabel 30', D.wahana, 'wahana', 'Tabel 19', D.mouValid + ' berlaku', 'wahana',
    'lulus', '—', 'Tabel 30 mendaftar ' + D.wahana + ' wahana, ' + D.mouValid + ' dengan MoU berlaku. ' +
      D.mouExpired + ' kedaluwarsa tidak dihitung ke kapasitas. Tabel 30 khusus tahap profesi — prodi ini punya tahap profesi, jadi aturan ini berlaku.'],
  ['R5', 'Lokasi sarana unik, tidak dihitung ganda',
    'Tabel 15', SARANA.length, 'jenis', 'Tabel 28', SARANA.length, 'jenis',
    'lulus', '—', 'Tabel 15 dan Tabel 28 mendaftar lokasi sarana yang sama, tidak ada duplikasi. Setiap ruangan hanya muncul sekali.'],
  ['R6', 'Total SKS kurikulum = jumlah SKS semua MK',
    'Tabel 7', D.sksTotal, 'SKS', 'Tabel 8', D.sksTotal, 'SKS',
    'lulus', '—', 'Tabel 7 deklarasi ' + D.sksTotal + ' SKS total, Tabel 8 penjumlahan MK = ' + D.sksTotal + ' SKS. Cocok.'],
  ['R7', 'Jumlah dosen = jumlah NIDN unik',
    'Tabel 6', 24, 'dosen', 'Tabel 6a', 24, 'NIDN',
    'lulus', '—', 'Tabel 6 mendaftar 24 dosen, Tabel 6a verifikasi 24 NIDN unik. Tidak ada duplikasi NIDN.'],
  ['R8', 'Judul pustaka = jumlah eksemplar masuk daftar',
    'Tabel 16', D.judulCetak + D.judulDigital, 'judul', 'Tabel 16a', D.judulCetak + D.judulDigital, 'judul',
    'lulus', '—', 'Tabel 16 dan Tabel 16a sama-sama mencatat ' + (D.judulCetak + D.judulDigital) + ' judul. Setiap judul punya eksemplar tercatat.'],
  ['R9', 'Jumlah pengguna = jumlah akun terdaftar',
    'F-22', PENGGUNA.length, 'akun', 'Sistem', PENGGUNA.length, 'akun',
    'lulus', '—', 'Form F-22 dan sistem sama-sama mencatat ' + PENGGUNA.length + ' akun. Tidak ada akun yang hilang atau ganda.'],
  ['R10', 'Periode pelaporan mencakup TS-6 sampai TS',
    'Tabel 1', 'TS-6 s/d TS', 'periode', 'F-02', 'TS-6 s/d TS', 'periode',
    'lulus', '—', 'Tabel 1 dan Form F-02 sama-sama menetapkan rentang TS-6 sampai TS (7 tahun). Cocok.'],
];

export const css = `
/* konsistensi — CSS khusus halaman */
.kon-detail { display: grid; grid-template-columns: minmax(90px, 110px) 1fr; gap: 6px 12px;
  font-size: var(--fs-sm); margin-top: 8px; }
.kon-detail .k { color: var(--ink3); font-size: var(--fs-xs); }
.kon-detail .v { color: var(--ink1); font-weight: 600; }
.kon-detail .v.bad { color: var(--bad); }
.kon-detail .v.ok { color: var(--ok); }
.kon-selisih { font-family: var(--mono); font-size: var(--fs-sm); font-weight: 700; }
`;

function page(state) {
  const gagal = ATURAN.filter((r) => r[8] === 'gagal');
  const lulus = ATURAN.filter((r) => r[8] === 'lulus');
  const blokir = gagal.filter((r) => r[9] === 'blokir').length;
  const peringatan = gagal.filter((r) => r[9] === 'peringatan').length;

  return head('Konsistensi',
    `10 aturan lintas tabel · ${gagal.length} gagal · badge "2 gagal" di sidebar merujuk halaman ini`)
  + callout('bad',
    `${gagal.length} aturan gagal — ekspor PDF/XLSX terkunci`,
    `<p>Selama ada aturan yang gagal, borang tidak bisa diekspor. Asesor akan menanyakan kedua nilai yang dibandingkan, bukan hanya "tidak cocok". Perbaiki tabel yang ditandai di tiap aturan.</p>`)
  + strip([
    [`${ATURAN.length}`, 'aturan diperiksa', ''],
    [`${lulus.length}`, 'aturan lolos', 'ok'],
    [`${gagal.length}`, 'aturan gagal', 'bad'],
    [`${blokir}`, 'blokir', 'bad'],
    [`${peringatan}`, 'peringatan', 'warn'],
  ])
  + card(
    cardHead('Daftar aturan lintas tabel',
      `${ATURAN.length} aturan · ${gagal.length} gagal disorot`)
    + table(
      [['#', ''], ['Aturan', ''], ['Tabel A', ''], ['Nilai A', 'r'], ['Tabel B', ''], ['Nilai B', 'r'], ['Selisih', 'r'], ['Status', '']],
      ATURAN.map((r) => ({
        cls: r[8] === 'gagal' ? 'is-bad' : '',
        cells: [
          r[0],
          `<span class="strong">${esc(r[1])}</span>`,
          `<span class="muted">${esc(r[2])}</span>`,
          `<span class="num">${esc(r[3])} ${esc(r[4])}</span>`,
          `<span class="muted">${esc(r[5])}</span>`,
          `<span class="num">${esc(r[6])} ${esc(r[7])}</span>`,
          r[8] === 'gagal'
            ? `<span class="kon-selisih" style="color:var(--bad)">${selisih(r)}</span>`
            : `<span class="kon-selisih" style="color:var(--ok)">0</span>`,
          badge(r[8] === 'gagal' ? 'bad' : 'ok', r[8]),
        ],
      })),
    )
    + `<p class="foot-note">Aturan yang gagal wajib menampilkan kedua nilai dan selisih. Asesor butuh angka konkret, bukan label "tidak cocok".</p>`
  )
  + card(
    cardHead('Detail aturan yang gagal',
      `${gagal.length} aturan · perbaiki tabel yang ditandai`)
    + gagal.map((r) => {
      const sel = selisih(r);
      const perbaiki = r[0] === 'R1' ? 'Tabel 5' : 'Tabel 34';
      return `<div class="drawer" style="margin-bottom:10px;border-left:3px solid var(--bad)">
        <h3>${esc(r[1])}</h3>
        <div class="muted" style="font-size:var(--fs-xs)">${esc(r[2])} vs ${esc(r[5])} · ${badge('bad', r[9])}</div>
        <div class="kon-detail">
          <span class="k">${esc(r[2])}</span><span class="v bad">${esc(r[3])} ${esc(r[4])}</span>
          <span class="k">${esc(r[5])}</span><span class="v">${esc(r[6])} ${esc(r[7])}</span>
          <span class="k">Selisih</span><span class="v bad">${esc(sel)}</span>
          <span class="k">Perbaiki</span><span class="v" style="color:var(--orn)">${esc(perbaiki)}</span>
        </div>
        <p class="foot-note" style="margin-top:8px">${esc(r[10])}</p>
      </div>`;
    }).join('')
  )
  + card(
    cardHead('Aturan yang lolos',
      `${lulus.length} aturan · contoh konsistensi yang benar`)
    + dlist(lulus.map((r) => [
      `${r[0]} · ${r[1]}`,
      `${esc(r[2])} = <b>${esc(r[3])} ${esc(r[4])}</b>, ${esc(r[5])} = <b>${esc(r[6])} ${esc(r[7])}</b>. ${esc(r[10])}`,
    ]))
  );
}

/** hitung selisih untuk ditampilkan — string, bukan angka mentah */
function selisih(r) {
  // R1: 41 vs 44 → "3 lulusan"
  // R2: 99,9% vs 100% → "0,1%"
  if (r[0] === 'R1') return '3 ' + r[4];
  if (r[0] === 'R2') return '0,1 ' + r[4];
  // generik: coba parse
  const a = parseFloat(String(r[3]).replace(',', '.'));
  const b = parseFloat(String(r[6]).replace(',', '.'));
  if (!isNaN(a) && !isNaN(b)) return nf(Math.abs(b - a)) + ' ' + r[4];
  return '—';
}

export default page;
