/* uji — dibentuk dari definisi field di server (/api/uji_kompetensi).
 *
 * Halaman ini tidak punya HTML sendiri. Kolom tabel, form, dan validasi
 * semuanya diturunkan dari `koleksi_def()['uji_kompetensi']` di
 * web/system/koleksi.php. Kalau field ditambah di sana, halaman ini ikut
 * berubah tanpa disentuh.
 */
import { buatHalaman } from '../koleksi.js';

const h = buatHalaman('uji_kompetensi');

export const muat = h.muat;
export default h.page;
