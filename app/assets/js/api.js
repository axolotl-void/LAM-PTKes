/* Klien API tipis. Tanpa library — fetch sudah cukup.
 *
 * Kalau server tidak jalan (mis. file dibuka lewat file://), semua fungsi
 * mengembalikan null dan UI tetap memakai angka statis dari data.js. Jadi
 * app tidak mati hanya karena backend belum ada.
 */
const BASE = '';

async function call(method, path, body) {
  try {
    const res = await fetch(BASE + path, {
      method,
      headers: body ? { 'Content-Type': 'application/json' } : undefined,
      body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({ error: `HTTP ${res.status}` }));
      throw new Error(err.error || `HTTP ${res.status}`);
    }
    return await res.json();
  } catch (e) {
    console.warn('[api]', path, e.message);
    return null;
  }
}

export const api = {
  ringkasan: () => call('GET', '/api/ringkasan'),
  tabel: () => call('GET', '/api/tabel'),
  riwayat: (batas = 20) => call('GET', `/api/riwayat?batas=${batas}`),
  pengguna: () => call('GET', '/api/pengguna'),
  ubahPeran: (id, peran, status, oleh) => call('POST', `/api/pengguna/${id}/peran`, { peran, status, oleh }),
  pisahPeran: (id, oleh) => call('POST', `/api/pengguna/${id}/pisah`, { oleh }),
  isi: (kode, terisi, oleh) => call('POST', `/api/tabel/${kode}/isi`, { terisi, oleh }),
  kunci: (kode, oleh) => call('POST', `/api/tabel/${kode}/kunci`, { oleh }),
  buka: (kode, oleh) => call('POST', `/api/tabel/${kode}/buka`, { oleh }),
  reset: () => call('POST', '/api/reset', {}),
};

/* Simpan ringkasan terakhir supaya halaman bisa membacanya tanpa await.
   Dashboard merender sinkron; angka DB diambil di boot lalu diperbarui
   setiap kali ada tulisan.
   
   Objek mutable, bukan `export let`: kalau binding di-rebind, modul yang
   mengimpornya lewat perantara (views.js) bisa tetap memegang nilai lama.
   Dengan objek yang isinya diganti, semua pemegang referensi ikut melihat. */
export const LIVE = { ringkasan: null, tabel: null, pengguna: null, siap: false };

export async function hydrate() {
  const [r, t, p] = await Promise.all([api.ringkasan(), api.tabel(), api.pengguna()]);
  if (r && t) {
    LIVE.ringkasan = r;
    LIVE.tabel = t;
    LIVE.pengguna = p ? p.daftar : null;
    LIVE.siap = true;
  }
  return LIVE.siap ? LIVE : null;
}

/* Angka siap pakai: pakai DB kalau ada, kalau tidak jatuh ke konstanta. */
export const R = (kunci, cadangan) =>
  LIVE.siap && LIVE.ringkasan[kunci] != null ? LIVE.ringkasan[kunci] : cadangan;
