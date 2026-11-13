/* Shell: drawer sidebar + konfirmasi aksi destruktif. Tanpa framework. */
(function () {
  'use strict';

  var body = document.body;
  var toggle = document.getElementById('sbToggle');

  if (toggle) {
    toggle.addEventListener('click', function () {
      var buka = body.classList.toggle('sb-open');
      toggle.setAttribute('aria-expanded', String(buka));
    });
  }

  document.addEventListener('click', function (e) {
    if (!body.classList.contains('sb-open')) return;
    if (e.target.closest('.sidebar') || e.target.closest('#sbToggle')) return;
    body.classList.remove('sb-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      body.classList.remove('sb-open');
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }
  });

  // Setiap form bertanda data-konfirmasi minta persetujuan dulu. Dipakai untuk
  // kunci tabel, nonaktifkan akun, dan buka kunci — aksi yang tidak boleh
  // terjadi karena salah klik.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!(f instanceof HTMLFormElement)) return;
    var pesan = f.getAttribute('data-konfirmasi');
    if (pesan && !window.confirm(pesan)) e.preventDefault();
  });
})();
