/* survei — dibentuk dari definisi field di server (/api/survei).
 *
 * Halaman ini tidak punya HTML sendiri. Kolom tabel, form, dan validasi
 * semuanya diturunkan dari `koleksi_def()['survei']` di
 * web/system/koleksi.php. Kalau field ditambah di sana, halaman ini ikut
 * berubah tanpa disentuh.
 */
import { buatHalaman } from '../koleksi.js';

const h = buatHalaman('survei');

export const muat = h.muat;
export default h.page;
