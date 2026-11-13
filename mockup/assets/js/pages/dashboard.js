/* dashboard — dipisah dari views.js agar bisa dikerjakan paralel. */
import { ST_LABEL } from '../views.js';
import { LIVE, R } from '../api.js';
import {
  esc, nf, head, strip, badge, card, cardHead, callout, dlist, mbar, empty, table,
  D, KRITERIA, INSTRUMEN, PERIODE, DATA_PER, PRODI, ANGKATAN, MK, PENGGUNA,
  WAHANA, SARANA, KOLEKSI, LANGGANAN, UPPS, PRODI_FIELDS, JENJANG,
  KONSISTENSI_GAGAL, PERINGKAT, DOKUMEN, TABEL_TERBARU, KUNCI, RIWAYAT,
  FEEDS, MASALAH_MHS, AKSES, TOTAL_MK_INSTRUMEN, ASPEK, IZIN, MATRIX,
  PERMINTAAN, K3, NIM_GANDA, UNIK,
} from '../pagekit.js';

function page(state) {
  const lengkapPct = Math.round((D.tabelLengkap / D.tabelWajib) * 100);
  // "Selesai" = lengkap (25) + terkunci (1) = 26, sama dengan D.tabelLengkap.
  // Halaman Data Dukung memecahnya jadi 25 + 1; dashboard menyebut totalnya
  // supaya angkanya sama di dua tempat.
  // Angka DB menang; konstanta data.js jadi cadangan kalau server mati.
  const selesai = R('lengkap', D.tabelLengkap);
  const tabelWajib = R('tabel_wajib', D.tabelWajib);
  const belum = R('belum', D.tabelWajib - D.tabelLengkap);
  const sebagian = 2;
  const kosong = belum - sebagian;
  const na = INSTRUMEN.tabelNA.length;

  /* Celah per kriteria. Kalau DB hidup, hitung dari tabel — kalau tidak,
     pakai konstanta. Tanpa ini blok "Celah terbesar" tidak ikut berubah
     saat tabel diisi, dan halaman saling bertentangan. */
  /* Penghalang ekspor. Yang berasal dari data hidup (konflik peran) dihitung
     ulang; sisanya tetap dari konstanta karena belum ada sumbernya di DB. */
  const konflikDB = LIVE.siap && LIVE.pengguna
    ? LIVE.pengguna.filter((p) => p.status === 'konflik').length
    : D.konflik;
  const penghalang = D.penghalang
    .map((p) => (p.ke === 'pengguna'
      ? { ...p, t: `${konflikDB} akun memegang peran ganda` }
      : p))
    .filter((p) => !(p.ke === 'pengguna' && konflikDB === 0));
  const blokir = penghalang.filter((p) => p.jenis === 'blokir').length;
  const locked = penghalang.length > 0;

  const celahDB = (() => {
    if (!LIVE.siap) return D.celah;
    const peta = LIVE.ringkasan.per_kriteria;
    return KRITERIA.map((k) => {
      const p = peta[k.kode] || { total: k.tabel[1], lengkap: k.tabel[0] };
      return {
        k: `${k.kode} ${k.singkat}`,
        butir: k.butir,
        done: p.lengkap,
        tot: p.total,
        kurang: p.total - p.lengkap,
      };
    });
  })();
  const belumDisentuh = INSTRUMEN.totalTabel - D.tabelWajib;
  // 26 + 6 wajib belum lengkap + 6 tak berlaku = 38. Kalau tidak tutup, jangan render.
  if (selesai + belum + na !== INSTRUMEN.totalTabel) {
    throw new Error(`dashboard tidak tutup: ${selesai}+${belum}+${na} ≠ ${INSTRUMEN.totalTabel}`);
  }
  // Bar tersegmentasi digambar terhadap total 38. Kalau penyebutnya campur
  // (32 untuk sebagian segmen, 38 untuk yang lain) total lebar jadi 115,8%
  // dan bar meluber melewati kartu.
  if (selesai + sebagian + kosong + na !== INSTRUMEN.totalTabel) {
    throw new Error(`segbar tidak tutup: ${selesai}+${sebagian}+${kosong}+${na} ≠ ${INSTRUMEN.totalTabel}`);
  }
  /* Baris tabel di bawah. DB hidup: tampilkan yang paling perlu dikerjakan
     dulu (kosong -> sebagian -> terkunci -> lengkap), maksimal 6 baris.
     DB mati: pakai TABEL_TERBARU lama supaya halaman tetap terisi. */
  const barisTabel = (() => {
    if (!LIVE.siap) {
      return TABEL_TERBARU.map(([kode, judul, st, ket, aksi]) => ({
        kode: kode.replace(/^T-0*/, ''), judul, status: st, ket, aksi,
      }));
    }
    const bobot = { kosong: 0, sebagian: 1, lengkap: 2, terkunci: 3, na: 4 };
    return LIVE.tabel
      .filter((t) => t.berlaku)
      .sort((a, b) => (bobot[a.status] - bobot[b.status]) || (a.kode > b.kode ? 1 : -1))
      .slice(0, 6)
      .map((t) => ({
        kode: t.kode,
        judul: t.judul,
        status: t.status,
        ket: t.status === 'terkunci'
          ? `dikunci ${t.dikunci_oleh}`
          : `${t.field_terisi}/${t.field_total} field${t.status === 'lengkap' ? ' · lengkap' : t.status === 'kosong' ? ' · belum diisi' : ' · belum lengkap'}`,
        aksi: t.status === 'lengkap' || t.status === 'terkunci' ? 'Buka' : 'Isi',
      }));
  })();

  return head('Dashboard',
    `${esc(state.prodi + ' · ' + (PRODI.find((p) => p.nama === state.prodi)?.jenjang || '') + ' · TS ' + PERIODE.ts)} · data per ${DATA_PER}`,
    '<button class="btn btn-ghost" disabled>' + (locked ? 'Ekspor terkunci' : 'Ekspor PDF') + '</button>')
  // ── baris 1: satu kartu, tiga blok ──────────────────────────────
  + `<div class="statuscard">
      <div class="blk">
        <div class="lbl">Peringkat akreditasi</div>
        <div class="grade">${PERINGKAT.nilai}</div>
        <div class="grade-name">${esc(PERINGKAT.sebutan)}</div>
        <div class="lbl" style="margin-top:6px">berlaku s.d. ${esc(PERINGKAT.berlaku)}</div>
        <div class="scale">
          ${PERINGKAT.skala.map((s) => `<span class="${s === PERINGKAT.nilai ? 'on' : ''}">${s}</span>`).join('')}
        </div>
        <div class="lbl" style="margin-top:8px">${D.hari} hari lagi</div>
      </div>
      <div class="blk">
        <div class="spread"><span class="lbl">Data dukung</span>
          <span class="lbl nowrap">${selesai} selesai · ${belum} belum</span></div>
        <div class="segbar" title="${selesai} selesai · ${sebagian} sebagian · ${kosong} kosong · ${na} tak berlaku = ${INSTRUMEN.totalTabel} tabel">
          <i class="ok" style="width:${(selesai / INSTRUMEN.totalTabel) * 100}%"></i>
          <i class="warn" style="width:${(R('sebagian', sebagian) / INSTRUMEN.totalTabel) * 100}%"></i>
          <i class="muted" style="width:${(R('kosong', kosong) / INSTRUMEN.totalTabel) * 100}%"></i>
          <i class="na" style="width:${(na / INSTRUMEN.totalTabel) * 100}%"></i>
        </div>
        <div class="legend" style="margin-top:8px">
          <span><span class="swatch ok"></span>lengkap ${selesai}</span>
          <span><span class="swatch warn"></span>sebagian ${R('sebagian', sebagian)}</span>
          <span><span class="swatch muted"></span>kosong ${R('kosong', kosong)}</span>
          <span><span class="swatch na"></span>tak berlaku ${na}</span>
        </div>
        <div class="lbl" style="margin-top:8px">${belum === 0
          ? `Semua tabel terisi. Ekspor menunggu ${blokir} blokir${KONSISTENSI_GAGAL.length ? ` · konsistensi <a data-nav="konsistensi" class="lnk" style="font-size:10.5px">gagal</a>` : ''}`
          : `${belum} tabel belum lengkap — ekspor menunggu ${blokir} blokir${KONSISTENSI_GAGAL.length ? ` · konsistensi <a data-nav="konsistensi" class="lnk" style="font-size:10.5px">gagal</a>` : ''}`}</div>
      </div>
      <div class="blk">
        <div class="lbl">Dokumen bukti</div>
        <div class="big">${DOKUMEN.terunggah}<span> dari ${DOKUMEN.total} bukti</span></div>
        <div class="segbar"><i class="ind" style="width:${(DOKUMEN.terunggah / DOKUMEN.total) * 100}%"></i></div>
        <div class="lbl" style="margin-top:8px">${DOKUMEN.total - DOKUMEN.terunggah} belum diunggah, termasuk ${DOKUMEN.kedaluwarsa} kedaluwarsa</div>
      </div>
    </div>`
  // ── baris 2: celah (62) + penghalang (38) ───────────────────────
  + `<div class="row2">
    <div class="card">
      <div class="card-head"><h2>Celah terbesar</h2>
        <span class="note">sisa ${belum} tabel di ${celahDB.filter((c) => c.kurang > 0).length} kriteria</span>
        ${belum > 0 ? '<span class="note" style="margin-left:auto">sisa / total</span>' : ''}</div>
      ${belum > 0 ? `<div class="gaplegend">
        <span><i class="gap"></i>panjang bar = andil ke sisa tabel</span>
        <span class="gaphint">diurut dari celah terbesar</span>
      </div>` : ''}
      ${belum === 0 ? `
        <div class="gapdone">
          <b>Semua ${tabelWajib} tabel wajib sudah lengkap.</b>
          <p>Semua kolom wajib terisi, tapi ekspor masih tertahan ${blokir} blokir —
          dan cakupan data (impor Tabel 1) belum penuh. Terisi bukan berarti final.</p>
        </div>` : ''}
      <div class="gaplist">
      ${(() => {
        // Urut dan panjang bar mengukur hal yang sama: andil ke total celah.
        // Versi sebelumnya urut pakai jumlah sisa tapi bar pakai proporsi
        // per-kriteria — K5 (2 dari 10) di atas tapi K7 (1 dari 2) bar-nya
        // paling panjang. Mata dan urutan bertengkar.
        const rows = celahDB.filter((c) => c.kurang > 0).sort((a, b) => b.kurang - a.kurang);
        const totalSisa = rows.reduce((s, r) => s + r.kurang, 0);
        return rows.map(({ k, butir, done, tot, kurang }, i) => `
        <div class="gapbox" data-nav="datadukung" role="link" tabindex="0">
          <div class="gk">
            <span class="grank">${i + 1}</span>
            <span class="gcode">${k.split(' ')[0]}</span>
          </div>
          <div class="gi">
            <div class="gt">${esc(k.replace(/^K\d+\s+/, ''))}</div>
            <div class="gs">${esc(butir)}</div>
            <div class="gbar" title="${kurang} dari ${totalSisa} tabel yang belum">
              <i style="width:${(kurang / totalSisa) * 100}%"></i>
            </div>
          </div>
          <div class="gr">
            <div class="gmiss"><b>${kurang}</b><span>tabel kurang</span></div>
            <div class="gtot">${done} dari ${tot} tabel</div>
          </div>
        </div>`).join('');
      })()}
      </div>
      <div class="card-foot">
        <span>Lengkap: ${celahDB.filter((c) => c.kurang === 0).map((c) => c.k.split(' ')[0]).join(', ') || '—'}</span>
        <a data-nav="ringkasan" class="lnk">Lihat semua →</a>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Menghalangi ekspor</h2>
        <span class="note">${blokir} blokir · ${penghalang.length - blokir} peringatan</span></div>
      ${penghalang.map((p) => `<div class="blockitem">
        <span class="dot ${p.jenis === 'blokir' ? 'bad' : 'warn'}"></span>
        <div><div class="spread"><h4>${esc(p.t)}</h4>
          <span class="badge ${p.jenis === 'blokir' ? 'bad' : 'warn'}">${p.jenis === 'blokir' ? 'blokir' : 'peringatan'}</span></div>
        <p>${esc(p.d)}</p>
        <a data-nav="${p.ke}" class="lnk">${esc(p.aksi)}</a></div>
      </div>`).join('')}
      <div class="card-foot">
        <span>${penghalang.length} penghalang: ${blokir} blokir · ${penghalang.length - blokir} peringatan</span>
        <span class="lbl">${locked ? 'ekspor terkunci' : 'ekspor aktif'}</span>
      </div>
    </div>
  </div>`
  // ── baris 3: tabel data dukung ─────────────────────────────────
  + `<div class="card">
      <div class="card-head"><h2>Data dukung</h2>
        <span class="note">${barisTabel.length} dari ${INSTRUMEN.totalTabel} tabel · ${LIVE.siap ? (belum === 0 ? 'terakhir dikunci' : 'butuh perhatian dulu') : 'terbaru'}</span>
        <a data-nav="datadukung" class="lnk" style="margin-left:auto">Semua ${INSTRUMEN.totalTabel} tabel →</a></div>
      <table class="tbl">
        <thead><tr><th>Tabel</th><th>Judul</th><th>Status</th><th>Keterangan</th><th class="r">Aksi</th></tr></thead>
        <tbody>
        ${barisTabel.map((r) => `<tr>
          <td class="num muted">T-${r.kode.padStart(2, '0')}</td>
          <td class="strong">${esc(r.judul)}</td>
          <td>${badge(r.status, ST_LABEL[r.status])}</td>
          <td class="muted">${esc(r.ket)}</td>
          <td class="r"><a class="lnk" data-nav="datadukung">${r.aksi} →</a></td>
        </tr>`).join('')}
        </tbody>
      </table>
    </div>`;
}

export default page;
