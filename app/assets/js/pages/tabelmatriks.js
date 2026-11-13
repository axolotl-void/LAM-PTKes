/* tabelmatriks — Tabel 15: matriks dosen tetap × prodi (#/tabelmatriks/15).
 *
 * KENAPA HALAMAN SENDIRI: bentuknya bukan daftar baris yang bisa ditambah
 * operator, tapi matriks hitung — 8 baris tetap (5 jabatan + 3 pendidikan),
 * satu kolom per prodi, plus Total UPPS. Menampilkannya lewat halaman isian
 * biasa akan memberi tombol "Tambah baris" yang tidak ada artinya.
 *
 * CATATAN INSTRUMEN YANG HARUS TAMPIL DI LAYAR (bukan hanya di kode):
 * jumlah kolom prodi BOLEH melebihi Total UPPS, karena satu dosen bisa
 * mengajar di dua prodi sedangkan Total menghitungnya sekali. Tanpa
 * penjelasan ini, orang akan mengira ada salah hitung lalu "membetulkan"nya.
 */
import { esc, head, strip, card, cardHead, callout } from '../pagekit.js';

let kode = null;
let data = null;
let galat = '';

export async function muat() {
  const h = location.hash.replace('#/tabelmatriks/', '').replace('#/tabelmatriks', '');
  kode = h.replace(/[^0-9]/g, '').padStart(2, '0');
  if (!kode || kode === '00') { galat = 'Kode tabel tidak dikenal.'; return; }
  try {
    const r = await fetch(`/api/tabel/${kode}/matriks`, { credentials: 'same-origin' });
    const j = await r.json();
    if (!r.ok) { galat = j.error || `HTTP ${r.status}`; data = null; }
    else { data = j; galat = ''; }
  } catch (e) {
    galat = 'Server tidak merespons.';
  }
  if (window.__paint) window.__paint();
}

function page() {
  if (!data) {
    return head(`Tabel ${kode || ''}`, '', '<a class="btn btn-ghost" href="#/datadukung">← Daftar tabel</a>')
      + (galat
        ? callout('bad', 'Tidak bisa dimuat', `<p>${esc(galat)}</p>`)
        : '<p class="muted">Memuat…</p>');
  }

  const { prodi, baris, total_prodi, total_upps, jumlah_dosen_tetap, catatan } = data;

  const kepala = `<tr>
    <th>No</th><th>Hal</th>
    ${prodi.map((p) => `<th class="r">${esc(p.nama)}</th>`).join('')}
    <th class="r strong">Total UPPS</th>
  </tr>`;

  /* Baris dikelompokkan per blok supaya terlihat sebagai dua bagian
     (Jabatan Fungsional / Pendidikan Tertinggi), persis seperti instrumen. */
  let isi = '';
  let blokAktif = null;
  const JUDUL_BLOK = {
    A: 'Jabatan Fungsional',
    B: 'Pendidikan Tertinggi',
  };

  baris.forEach((b) => {
    if (b.blok !== blokAktif) {
      blokAktif = b.blok;
      isi += `<tr class="mat-blok"><td colspan="${prodi.length + 3}">
        <b>${JUDUL_BLOK[b.blok] || b.blok}</b></td></tr>`;
    }
    isi += `<tr>
      <td class="num muted">${esc(b.no)}</td>
      <td>${esc(b.hal)}</td>
      ${prodi.map((p) => {
        const n = b.per_prodi[p.id] ?? 0;
        return `<td class="r num">${n === 0 ? '<span class="muted">0</span>' : n}</td>`;
      }).join('')}
      <td class="r num strong">${b.total}</td>
    </tr>`;
  });

  // Total per prodi di kaki tabel.
  const kaki = `<tr class="mat-total">
    <td></td><td class="strong">Total</td>
    ${prodi.map((p) => `<td class="r num strong">${total_prodi[p.id] ?? 0}</td>`).join('')}
    <td class="r num strong">${total_upps}</td>
  </tr>`;

  return head(`Tabel ${kode}`,
    `${esc(data.judul || 'Dosen Tetap pada Unit Pengelola Program Studi')} · dihitung dari master dosen`,
    `<a class="btn btn-ghost" href="#/datadukung">← Daftar tabel</a>`)
    + strip([
      [`${jumlah_dosen_tetap}`, 'dosen tetap dihitung', ''],
      [`${prodi.length}`, 'prodi', 'muted'],
      [`${total_upps}`, 'total UPPS', 'ok'],
    ])
    + callout('info', 'Tabel ini tidak diisi manual',
      `<p>Seluruh angka dihitung dari halaman <a href="#/dosen">Dosen &amp; Tendik</a>. `
      + `Memperbaiki data dosen di sana langsung mengubah tabel ini — tidak perlu tarik ulang.</p>`)
    + card(cardHead('Dosen tetap per jabatan dan pendidikan',
      `${baris.length} baris × ${prodi.length} prodi`)
      + `<div style="overflow-x:auto">
        <table class="tbl mat-baris">
          <thead>${kepala}</thead>
          <tbody>${isi}</tbody>
          <tfoot>${kaki}</tfoot>
        </table>
      </div>
      <p class="foot-note">${esc(catatan)}</p>`);
}

export default page;
