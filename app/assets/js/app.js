/* Router hash-based + shell. Tanpa framework, tanpa build step.
 *
 * Gerbang: tanpa sesi, yang tampil hanya layar masuk. Ini bukan sekadar
 * menyembunyikan tombol — server juga menolak POST tanpa token.
 */
import { validate, PRODI } from './data.js';
import { sidebar, render } from './views.js';
import { animateIn } from './anim.js';
import { hydrate, api, SESI, boleh } from './api.js';

const state = { prodi: 'Kebidanan' };
const PAGES = new Set(['dashboard', 'ringkasan', 'identitas', 'periode', 'dosen', 'keuangan',
  'kerjasama', 'pengguna', 'mahasiswa', 'kurikulum', 'sarana', 'pustaka', 'uji', 'survei',
  'datadukung', 'dokumen', 'konsistensi']);

const $ = (sel) => document.querySelector(sel);
const page = () => {
  const h = location.hash.replace('#/', '');
  return PAGES.has(h) ? h : 'dashboard';
};

/* "Siti Rahmawati, S.ST., M.Keb" → "SR" */
const inisial = (nama) =>
  nama.replace(/,.*$/, '').split(/\s+/).map((w) => w[0]).join('').slice(0, 2).toUpperCase();

const setDrawer = (open) => {
  document.body.classList.toggle('sb-open', open);
  $('#sbToggle')?.setAttribute('aria-expanded', String(open));
};

/* ── layar masuk ──────────────────────────────────────────────────── */
function layarMasuk(pesan) {
  document.body.classList.add('belum-masuk');
  $('.main').innerHTML = `
    <div class="masuk">
      <div class="masuk-kiri">
        <div class="brand-mark big"></div>
        <h1>Sistem Akreditasi LAM-PTKes</h1>
        <p>Data dukung 38 tabel, 78 dokumen pendukung, satu tempat.
           Setiap aksi tercatat atas nama akun yang masuk.</p>
        <div class="masuk-demo">
          <div class="nav-label">AKUN DEMO · sandi <code>demo1234</code></div>
          <button data-akun="Siti Rahmawati, S.ST., M.Keb">Siti — Operator Kebidanan</button>
          <button data-akun="Dr. Maimunah, M.Keb">Maimunah — Verifikator Kebidanan</button>
          <button data-akun="Dr. Ir. Zulfikar, M.Pd">Zulfikar — Admin UPPS</button>
          <button data-akun="Prof. Dr. Cut Zahriah, M.Pd">Cut Zahriah — Pimpinan (lihat)</button>
        </div>
      </div>
      <form class="masuk-kanan" id="formMasuk">
        <h2>Masuk</h2>
        <label>Nama akun
          <input name="nama" autocomplete="username" required
                 placeholder="Nama persis seperti di daftar pengguna">
        </label>
        <label>Sandi
          <input name="sandi" type="password" autocomplete="current-password"
                 value="demo1234" required>
        </label>
        <label>Prodi yang dibuka
          <select name="prodi">${PRODI.map((p) => `<option>${p.nama}</option>`).join('')}</select>
        </label>
        <p class="masuk-err" role="alert">${pesan || ''}</p>
        <button class="btn btn-main" type="submit">Masuk</button>
      </form>
    </div>`;
  document.querySelector('.sidebar').innerHTML = '';
}

function pasangMasuk() {
  const form = $('#formMasuk');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = new FormData(form);
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    const r = await api.masuk(f.get('nama'), f.get('sandi'), f.get('prodi'));
    btn.disabled = false;
    if (!r || r.error) {
      form.querySelector('.masuk-err').textContent = r?.error || 'Server tidak merespons.';
      return;
    }
    const prodi = r.sesi.prodi;
    if (PRODI.some((p) => p.nama === prodi)) state.prodi = prodi;
    await hydrate();
    document.body.classList.remove('belum-masuk');
    paint();
  });

  document.querySelectorAll('[data-akun]').forEach((b) => {
    b.addEventListener('click', () => {
      const inp = form.querySelector('[name=nama]');
      inp.value = b.dataset.akun;
      form.querySelector('[name=sandi]').focus();
    });
  });
}

/* ── shell ────────────────────────────────────────────────────────── */
/* Dipakai modul halaman yang memuat datanya sendiri (mis. kerjasama) untuk
   meminta render ulang setelah data datang. Tanpa ini halaman berhenti di
   "Memuat…" karena render pertama terjadi sebelum fetch selesai. */
window.__paint = () => paint();

/* Halaman yang memuat datanya sendiri (kerjasama/Tabel 38): panggil `muat()`
   modulnya setelah render. Dideklarasikan SEBELUM dipakai oleh listener
   hashchange — `const` tidak di-hoist seperti function. */
const pemuat = {
  kerjasama: () => import('./pages/kerjasama.js').then((m) => m.muat?.()),
  dosen: () => import('./pages/dosen.js').then((m) => m.muat?.()),
};
function muatHalaman() {
  const p = page();
  if (pemuat[p]) pemuat[p]();
}

function paint() {
  if (!SESI.data) return;
  const p = page();
  $('.sidebar').innerHTML = sidebar(p, state);
  $('.main').innerHTML = render(p, state);
  animateIn($('.main'));
  document.title = `${p === 'dashboard' ? 'Dashboard' : p} · LAM-PTKes`;
  const av = $('#whoAvatar');
  if (av) av.textContent = inisial(SESI.data.nama);
  const nm = $('#whoNama');
  if (nm) nm.textContent = SESI.data.nama.replace(/,.*$/, '');
  const pr = $('#whoPeran');
  if (pr) pr.textContent = `${SESI.data.peran} · ${SESI.data.prodi || '—'}`;
  $('#btnKeluar')?.removeAttribute('hidden');

  setDrawer(false);
  window.scrollTo(0, 0);
}

function boot() {
  try {
    validate();
  } catch (err) {
    document.body.innerHTML = `
      <div style="max-width:720px;margin:12vh auto;padding:0 20px;
                  font:var(--fs-body)/1.6 ui-sans-serif,system-ui,sans-serif">
        <h1 style="font-size:20px;margin-bottom:12px">Data tidak konsisten — halaman tidak dirender</h1>
        <pre style="background:#FEF3F2;color:#B42318;padding:16px;border-radius:8px;
                    white-space:pre-wrap;font-family:ui-monospace,monospace;font-size:12px">${err.message}</pre>
        <p style="color:#667085;margin-top:12px">Perbaiki <code>assets/js/data.js</code> dulu. Ini disengaja:
        angka yang tidak tutup tidak boleh tampil.</p>
      </div>`;
    throw err;
  }

  $('#sbToggle')?.addEventListener('click', () => {
    setDrawer(!document.body.classList.contains('sb-open'));
  });

  // tutup drawer kalau klik di luar, tekan Esc, atau layar dibesarkan
  document.addEventListener('click', (e) => {
    if (document.body.classList.contains('sb-open')
        && !e.target.closest('.sidebar')
        && !e.target.closest('#sbToggle')) {
      setDrawer(false);
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setDrawer(false);
  });
  const mq = window.matchMedia('(min-width: 901px)');
  mq.addEventListener('change', (e) => { if (e.matches) setDrawer(false); });

  document.addEventListener('click', (e) => {
    const nav = e.target.closest('[data-nav]');
    if (nav) { location.hash = `#/${nav.dataset.nav}`; setDrawer(false); return; }

    const sw = e.target.closest('[data-prodi]');
    if (sw) {
      state.prodi = sw.dataset.prodi;
      const p = PRODI.find((x) => x.nama === state.prodi);
      document.documentElement.style.setProperty('--orn', p.accent === 'orn' ? '#FF8200' : '#2F2A95');
      paint();
      return;
    }

    const keluar = e.target.closest('[data-keluar]');
    if (keluar) {
      api.keluar().then(async () => {
        SESI.data = null;
        await hydrate();
        if (!SESI.data) layarMasuk('Sudah keluar.');
      });
      return;
    }

    // ── kelola akun: pisahkan peran / aktif-nonaktif ──
    const pisah = e.target.closest('[data-pisah]');
    if (pisah) {
      if (!confirm('Pisahkan peran akun ini? Verifikator dipertahankan, Operator dihapus dari akun ini.')) return;
      pisah.disabled = true;
      api.pisahPeran(pisah.dataset.pisah).then(async (r) => {
        if (!r || r.error) { pisah.disabled = false; alert(r?.error || 'Gagal.'); return; }
        await hydrate();
        paint();
      });
      return;
    }

    const tog = e.target.closest('[data-id][data-status]');
    if (tog) {
      const status = tog.dataset.status;
      if (status === 'nonaktif' && !confirm('Nonaktifkan akun ini? Riwayat pengisiannya tetap disimpan.')) return;
      tog.disabled = true;
      api.ubahPeran(tog.dataset.id, null, status).then(async (r) => {
        if (!r || r.error) { tog.disabled = false; alert(r?.error || 'Gagal.'); return; }
        await hydrate();
        paint();
      });
      return;
    }

    // ── verifikasi (kunci tabel) — hanya peran ber-"Verifikasi" ──
    const kunci = e.target.closest('[data-kunci]');
    if (kunci) {
      if (!confirm(`Kunci T-${kunci.dataset.kunci}? Setelah dikunci isian ditolak sampai Admin UPPS membuka.`)) return;
      kunci.disabled = true;
      api.kunci(kunci.dataset.kunci).then(async (r) => {
        if (!r || r.error) { kunci.disabled = false; alert(r?.error || 'Gagal.'); return; }
        await hydrate();
        paint();
      });
      return;
    }

    const buka = e.target.closest('[data-buka]');
    if (buka) {
      buka.disabled = true;
      api.buka(buka.dataset.buka).then(async (r) => {
        if (!r || r.error) { buka.disabled = false; alert(r?.error || 'Gagal.'); return; }
        await hydrate();
        paint();
      });
      return;
    }

    // ── isi tabel: satu prompt, satu POST, lalu muat ulang angka ──
    const isi = e.target.closest('[data-isi]');
    if (isi) {
      if (!boleh('Isi')) { alert(`Peran ${SESI.data?.peran || '—'} tidak boleh mengisi.`); return; }
      const kode = isi.dataset.isi;
      const total = +isi.dataset.total;
      const jwb = prompt(`Tabel ${kode}: berapa field terisi dari ${total}?`, String(total));
      if (jwb === null) return;
      const n = Number(jwb);
      if (!Number.isFinite(n) || n < 0 || n > total) {
        alert(`Isi antara 0 dan ${total}.`);
        return;
      }
      isi.disabled = true;
      api.isi(kode, n).then(async (r) => {
        if (!r) { isi.disabled = false; alert('Gagal menyimpan — server tidak merespons.'); return; }
        if (r.error) { isi.disabled = false; alert(r.error); return; }
        await hydrate();
        paint();
      });
      return;
    }

    const ex = e.target.closest('[data-export]');
    if (ex) {
      alert(`Ekspor ${ex.dataset.export.toUpperCase()} untuk prodi ${state.prodi}.\n` +
            `Butuh endpoint: GET /api/ekspor?prodi=...&format=${ex.dataset.export}`);
    }
  });

  window.addEventListener('hashchange', () => { paint(); muatHalaman(); });

  // Cek sesi dulu, baru render. Tanpa sesi: layar masuk, bukan dashboard.
  hydrate().then((live) => {
    if (!SESI.data) { layarMasuk(); pasangMasuk(); return; }
    document.body.classList.remove('belum-masuk');
    if (live) document.body.dataset.live = '1';
    const prodiSesi = SESI.data.prodi;
    if (PRODI.some((p) => p.nama === prodiSesi)) state.prodi = prodiSesi;
    paint();
    muatHalaman();
  });
}

boot();
