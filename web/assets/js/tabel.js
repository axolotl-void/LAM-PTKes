/* Form Data Dukung: tambah/hapus baris tanpa reload. Tanpa framework.
 *
 * Baris baru diambil dari <template id="tfTemplate"> supaya kontrol, nama field,
 * dan label sumbernya persis sama dengan baris yang dirender PHP — tidak ada
 * markup form kedua yang harus dirawat terpisah.
 *
 * Nomor urut baris baru = urut terbesar + 1. Server memakai urut itu sebagai
 * kunci baris_tabel (unik per periode+tabel), jadi nomor tidak boleh dipakai
 * ulang selama baris lama masih ada di halaman.
 */
(function () {
  'use strict';

  var form = document.getElementById('formTabel');
  var daftar = form && form.querySelector('.tf-baris-list');
  var tpl = document.getElementById('tfTemplate');
  var tombolTambah = document.getElementById('tfTambah');
  var hitung = document.getElementById('tfHitung');
  var wadahHapus = document.getElementById('tfHapus');
  if (!form || !daftar || !tpl || !tombolTambah) return;

  // Nomor awal dari server (baris di halaman lain juga dihitung), lalu ikut
  // naik saat baris ditambahkan di halaman ini.
  var urutMax = parseInt(form.getAttribute('data-urut-max'), 10) || 0;

  function urutBerikut() {
    urutMax += 1;
    return urutMax;
  }

  function segarkan() {
    if (hitung) hitung.textContent = daftar.querySelectorAll('.tf-baris').length + ' baris';
  }

  tombolTambah.addEventListener('click', function () {
    var urut = urutBerikut();
    var frag = tpl.content.cloneNode(true);
    var baris = frag.querySelector('.tf-baris');
    if (!baris) return;

    baris.setAttribute('data-urut', String(urut));
    baris.querySelectorAll('[name]').forEach(function (el) {
      // sel[0][kunci] -> sel[<urut>][kunci], hapus[0] -> hapus[<urut>]
      el.name = el.name.replace(/\[0\]/, '[' + urut + ']');
      if (el.id) el.id = el.id.replace(/0$/, '');
      el.value = '';
    });
    var no = baris.querySelector('.tf-baris-no');
    if (no) no.textContent = String(urut);

    daftar.appendChild(baris);
    segarkan();
    var pertama = baris.querySelector('.tf-input');
    if (pertama) pertama.focus();
  });

  // Hapus: baris baru langsung dibuang dari DOM, baris tersimpan ditandai
  // hapus[urut]=1 lalu dibuang juga — tanpa reload, tanpa dialog bawaan.
  // Baris tersimpan benar-benar dihapus saat Simpan ditekan, dalam transaksi
  // yang sama dengan isian lain.
  daftar.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-hapus-baris]');
    if (!btn) return;

    var baris = btn.closest('.tf-baris');
    if (!baris) return;

    var adaIsi = Array.prototype.some.call(baris.querySelectorAll('.tf-input'),
      function (el) { return el.value.trim() !== ''; });
    var id = parseInt(baris.getAttribute('data-baris-id'), 10) || 0;
    if ((adaIsi || id > 0) && !window.confirm('Hapus baris ini beserta isiannya?')) return;

    // hapus[] hanya untuk baris yang SUDAH tersimpan (id > 0). Baris baru
    // cukup dibuang dari DOM: tidak ada jejak di database.
    if (id > 0 && wadahHapus) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'hapus[]';
      input.value = baris.getAttribute('data-urut');
      wadahHapus.appendChild(input);
    }
    baris.remove();
    segarkan();
  });

  segarkan();
})();
