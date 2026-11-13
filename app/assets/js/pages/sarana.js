/* sarana — dibentuk dari definisi field di server (/api/laboratorium).
 *
 * Halaman ini tidak punya HTML sendiri. Kolom tabel, form, dan validasi
 * semuanya diturunkan dari `koleksi_def()['laboratorium']` di
 * web/system/koleksi.php. Kalau field ditambah di sana, halaman ini ikut
 * berubah tanpa disentuh.
 */
import { buatHalaman } from '../koleksi.js';

const h = buatHalaman('laboratorium');

export const muat = h.muat;
export default h.page;
