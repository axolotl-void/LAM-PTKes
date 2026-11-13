/* kurikulum — dibentuk dari definisi field di server (/api/mata_kuliah).
 *
 * Halaman ini tidak punya HTML sendiri. Kolom tabel, form, dan validasi
 * semuanya diturunkan dari `koleksi_def()['mata_kuliah']` di
 * web/system/koleksi.php. Kalau field ditambah di sana, halaman ini ikut
 * berubah tanpa disentuh.
 */
import { buatHalaman } from '../koleksi.js';

const h = buatHalaman('mata_kuliah');

export const muat = h.muat;
export default h.page;
