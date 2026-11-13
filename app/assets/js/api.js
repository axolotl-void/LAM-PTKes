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
  saya: () => call('GET', '/api/saya'),
  masuk: (nama, sandi, prodi) => call('POST', '/api/masuk', { nama, sandi, prodi }),
  keluar: () => call('POST', '/api/keluar', {}),
  ringkasan: () => call('GET', '/api/ringkasan'),
  tabel: () => call('GET', '/api/tabel'),
  riwayat: (batas = 20) => call('GET', `/api/riwayat?batas=${batas}`),
  pengguna: () => call('GET', '/api/pengguna'),
  ubahPeran: (id, peran, status) => call('POST', `/api/pengguna/${id}/peran`, { peran, status }),
  pisahPeran: (id) => call('POST', `/api/pengguna/${id}/pisah`, {}),
  isi: (kode, terisi) => call('POST', `/api/tabel/${kode}/isi`, { terisi }),
  kunci: (kode) => call('POST', `/api/tabel/${kode}/kunci`, {}),
  buka: (kode) => call('POST', `/api/tabel/${kode}/buka`, {}),
  reset: () => call('POST', '/api/reset', {}),
};

/* Sesi aktif. `oleh` tidak lagi dikirim dari klien: server mengambil nama dari
   cookie, jadi tombol tidak bisa mengaku jadi orang lain. */
export const SESI = { data: null };
export const boleh = (aksi) => SESI.data?.akses?.[aksi] === 1;
export const hanyaLihat = (aksi) => SESI.data?.akses?.[aksi] === 2;


/* Simpan ringkasan terakhir supaya halaman bisa membacanya tanpa await.
   Dashboard merender sinkron; angka DB diambil di boot lalu diperbarui
   setiap kali ada tulisan.
   
   Objek mutable, bukan `export let`: kalau binding di-rebind, modul yang
   mengimpornya lewat perantara (views.js) bisa tetap memegang nilai lama.
   Dengan objek yang isinya diganti, semua pemegang referensi ikut melihat. */
export const LIVE = { ringkasan: null, tabel: null, pengguna: null, siap: false };

/* Status ekspor menurut SERVER. `boleh: null` = belum tahu (server mati).
   Tombol ekspor mengikuti ini, bukan snapshot desain di data.js: snapshot
   memuat akun dan temuan karangan yang bisa mengunci ekspor selamanya.
   Objek mutable supaya bisa diisi ulang tanpa rebind binding. */
export const EKSPOR = { boleh: null, blokir: [] };

export async function muatEkspor() {
  try {
    const r = await fetch('/api/ekspor/cek', { credentials: 'same-origin' });
    if (r.ok) {
      const j = await r.json();
      EKSPOR.boleh = !!j.boleh;
      EKSPOR.blokir = j.blokir || [];
    }
  } catch (_) { /* biarkan null: tombol mati lebih baik daripada berkas tidak sah */ }
}

export async function hydrate() {
  const [s, r, t, p] = await Promise.all([
    api.saya(), api.ringkasan(), api.tabel(), api.pengguna(),
  ]);
  // Status ekspor ikut dimuat di sini supaya tombol di sidebar tahu
  // keputusan server sejak render pertama, bukan setelah diklik.
  await muatEkspor();
  SESI.data = s ? s.sesi : null;
  SESI.aksi = s ? s.aksi : [];
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
