/* Router hash-based + shell. Tanpa framework, tanpa build step. */
import { validate, PRODI } from './data.js';
import { sidebar, render } from './views.js';
import { animateIn } from './anim.js';
import { hydrate, api } from './api.js';

const state = { prodi: 'Kebidanan' };
const PAGES = new Set(['dashboard', 'ringkasan', 'identitas', 'periode', 'dosen', 'keuangan',
  'kerjasama', 'pengguna', 'mahasiswa', 'kurikulum', 'sarana', 'pustaka', 'uji', 'survei',
  'datadukung', 'dokumen', 'konsistensi']);

const $ = (sel) => document.querySelector(sel);
const page = () => {
  const h = location.hash.replace('#/', '');
  return PAGES.has(h) ? h : 'dashboard';
};

const setDrawer = (open) => {
  document.body.classList.toggle('sb-open', open);
  $('#sbToggle')?.setAttribute('aria-expanded', String(open));
};

function paint() {
  const p = page();
  $('.sidebar').innerHTML = sidebar(p, state);
  $('.main').innerHTML = render(p, state);
  animateIn($('.main'));
  document.title = `${p === 'dashboard' ? 'Dashboard' : p} · LAM-PTKes`;
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

    // ── kelola akun: pisahkan peran / aktif-nonaktif ──
    const pisah = e.target.closest('[data-pisah]');
    if (pisah) {
      const id = pisah.dataset.pisah;
      if (!confirm('Pisahkan peran akun ini? Verifikator dipertahankan, Operator dihapus dari akun ini.')) return;
      pisah.disabled = true;
      api.pisahPeran(id, 'Dr. Ir. Zulfikar').then(async (r) => {
        if (!r || r.error) { pisah.disabled = false; alert(r?.error || 'Gagal.'); return; }
        await hydrate();
        paint();
      });
      return;
    }

    const tog = e.target.closest('[data-id][data-status]');
    if (tog) {
      const id = tog.dataset.id;
      const status = tog.dataset.status;
      if (status === 'nonaktif' && !confirm('Nonaktifkan akun ini? Riwayat pengisiannya tetap disimpan.')) return;
      tog.disabled = true;
      api.ubahPeran(id, null, status, 'Dr. Ir. Zulfikar').then(async (r) => {
        if (!r || r.error) { tog.disabled = false; alert(r?.error || 'Gagal.'); return; }
        await hydrate();
        paint();
      });
      return;
    }

    // ── isi tabel: satu prompt, satu POST, lalu muat ulang angka ──
    const isi = e.target.closest('[data-isi]');
    if (isi) {
      const kode = isi.dataset.isi;
      const total = +isi.dataset.total;
      const kini = +isi.dataset.kini;
      const jwb = prompt(`Tabel ${kode}: berapa field terisi dari ${total}?`, String(total));
      if (jwb === null) return;
      const n = Number(jwb);
      if (!Number.isFinite(n) || n < 0 || n > total) {
        alert(`Isi antara 0 dan ${total}.`);
        return;
      }
      isi.disabled = true;
      api.isi(kode, n, 'Siti Rahmawati').then(async (r) => {
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

  window.addEventListener('hashchange', paint);
  paint();

  // Ambil angka dari SQLite. Kalau server tidak jalan / db kosong, hydrate()
  // mengembalikan null dan halaman tetap pakai angka statis data.js — app
  // tidak boleh blank hanya karena backend belum siap.
  hydrate().then((live) => {
    if (!live) return;
    document.body.dataset.live = '1';
    paint();
  });
}

boot();
