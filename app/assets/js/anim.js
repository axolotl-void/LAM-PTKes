/* Animasi masuk. Dipanggil setelah .main diisi ulang.
 *
 * Kenapa di JS dan bukan pure CSS: urutan masuk butuh indeks elemen, dan CSS
 * tidak punya cara menghitung "elemen ke berapa" tanpa nth-child per level.
 * Di sini cukup satu pass: setiap blok dapat `--i`, anak-anaknya dapat pecahan
 * dari `--i` induknya supaya masuk berkelompok, bukan satu per satu.
 */
const STEP = 0.4;   // jarak antar anak dalam satu blok, dalam satuan --i
const CAP = 14;     // blok ke-14 dst. masuk bersamaan

export function animateIn(root) {
  if (!root || typeof window === 'undefined') return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const blocks = [...root.children];
  blocks.forEach((el, i) => {
    const base = Math.min(i, CAP);
    el.style.setProperty('--i', base);
    el.classList.add('an');

    // Anak langsung dari grid boleh ikut bergilir, tapi jangan sampai
    // menambah urutan global — pakai pecahan supaya tetap dalam blok yang sama.
    const kids = el.matches('.grid')
      ? [...el.children]
      : [...el.querySelectorAll('.grid > *, .strip > .cell')];
    kids.forEach((k, j) => {
      k.style.setProperty('--i', (base + j * STEP).toFixed(2));
      k.classList.add('an');
    });

    // Baris tabel: nomor baris untuk jeda 22ms, ambil dari posisi blok.
    const rows = el.querySelectorAll('.tbl tbody tr');
    rows.forEach((tr, r) => tr.style.setProperty('--r', Math.min(r, 11)));
  });

  // Judul + subjudul halaman ikut urutan blok paling awal.
  const h = root.querySelector('.page-head');
  if (h) h.style.setProperty('--i', 0);
}
