# Panduan SSO: SPMI → Sistem Akreditasi LAM-PTKes

Menu **LAM-PTKes** di sidebar SPMI (`aside-auditee.php`, baris ~1216) sudah
mengarah ke `lamptkes.php`, tetapi berkas itu **belum ada**. Panduan ini mengisi
dua sisi sekaligus:

- **SPMI** (`spmi.bbg.ac.id`) — berkas baru `dashboard/lamptkes.php` yang membuat token.
- **Sistem ini** (`web/system/sso.php`) — sudah jadi, hanya perlu nilai config.

Alur: SPMI menandatangani token → browser pindah ke
`https://<lamptkes>/index.php?m=masuk&a=sso&t=<isi>.<tanda_tangan>` →
sistem ini memeriksa tanda tangan, masa berlaku, nonce, lalu membuat sesi.

---

## 1. Nilai yang harus diisi di KEDUA sisi

Rahasia harus **sama persis** di dua tempat. Beda satu karakter = semua tautan
ditolak dengan "Tanda tangan tautan SSO tidak sah".

| Berkas | Variabel | Lokal | Produksi |
|---|---|---|---|
| `spmi.bbg.ac.id/system/config.php` | `$sso_lamptkes_secret` | `rahasia-lokal-uji-sso-minimal-32-karakter-ok` | hasil `openssl rand -base64 32` |
| `spmi.bbg.ac.id/system/config.php` | `$sso_lamptkes_url` | `http://127.0.0.1:8003` | `https://akreditasi.bbg.ac.id` |
| `web/system/config.php` (sini) | `SSO_RAHASIA` | sudah terisi (lihat `config.php`) | **nilai yang sama** dengan `$sso_lamptkes_secret` |
| `web/system/config.php` (sini) | `BASE_URL` | `/` | `https://akreditasi.bbg.ac.id/` |

Catatan:

- `SSO_RAHASIA` / `$sso_lamptkes_secret` **minimal 32 karakter**. Lebih pendek →
  `sso_terima()` langsung menolak dengan pesan konfigurasi, bukan pesan samar.
- `BASE_URL` **wajib berakhiran garis miring** — semua tautan di sistem ini
  dirangkai `BASE_URL . 'index.php?...'`.
- `$sso_lamptkes_url` **tanpa** garis miring di akhir.
- Berkas `config.php` produksi tidak di-commit. Isi nilai produksi langsung di
  server lewat cPanel File Manager.

Bikin nilai produksi:

```bash
openssl rand -base64 32
```

---

## 2. Sisi SPMI — berkas baru `dashboard/lamptkes.php`

Buat berkas ini di server SPMI, **satu folder** dengan `laminfokom.php`
(`spmi.bbg.ac.id/dashboard/`). Isinya mengikuti pola `laminfokom.php`:
tidak menampilkan apa pun, hanya membuat token lalu mengalihkan browser.

```php
<?php
/**
 * LAM-PTKes — pintu masuk ke Sistem Akreditasi LAM-PTKes.
 *
 * Berkas ini TIDAK menampilkan apa pun: tugasnya membuat token bertanda tangan
 * lalu mengarahkan browser, supaya pengguna tidak login dua kali.
 *
 * KENAPA TANDA TANGAN, BUKAN CUKUP KIRIM EMAIL:
 * Kalau sistem akreditasi menerima `?email=...` tanpa tanda tangan, siapa pun
 * bisa mengetik alamat siapa pun di address bar dan masuk sebagai orang itu.
 * HMAC membuktikan permintaan ini benar datang dari server SPMI dan isinya
 * belum diubah di tengah jalan. PHP sudah punya `hash_hmac`.
 *
 * YANG PERLU DIISI (system/config.php SPMI):
 *   $sso_lamptkes_secret = 'rahasia bersama, minimal 32 karakter';
 *   $sso_lamptkes_url    = 'https://akreditasi.bbg.ac.id';
 * Nilai secret harus SAMA PERSIS dengan SSO_RAHASIA di web/system/config.php
 * sistem akreditasi.
 */

// Penyangga keluaran: berkas yang di-include bisa meninggalkan baris kosong
// sesudah penutup tag PHP, dan satu baris kosong sudah cukup membuat header()
// gagal dengan "headers already sent".
// CATATAN: JANGAN menulis penutup tag PHP di dalam komentar berkas ini —
// penutup tag itu tetap menutup mode PHP walau ada di dalam komentar.
if (ob_get_level() === 0) {
    ob_start();
}

$page_title = "LAM-PTKes";
$page_file = basename(__FILE__, '.php');

require '../system/config.php';
require '../system/function.php';
require '../system/session.php';

/** Tampilkan pesan singkat tanpa membocorkan detail teknis. */
function sso_galat($pesan)
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">';
    echo '<title>LAM-PTKes</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:80px auto;padding:0 24px;color:#1f2937;line-height:1.6}';
    echo 'code{background:#f3f4f6;padding:2px 6px;border-radius:4px}</style></head><body>';
    echo '<h2>LAM-PTKes belum siap</h2><p>' . htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="beranda.php">Kembali ke Beranda</a></p></body></html>';
    exit();
}

if (empty($sso_lamptkes_secret) || strlen($sso_lamptkes_secret) < 32) {
    sso_galat(
        'Rahasia bersama belum diatur. Hubungi pengelola web: isi $sso_lamptkes_secret '
        . 'di system/config.php (minimal 32 karakter), samakan dengan SSO_RAHASIA di sistem akreditasi.'
    );
}
if (empty($sso_lamptkes_url)) {
    sso_galat('Alamat sistem akreditasi belum diatur ($sso_lamptkes_url di system/config.php).');
}

$email = isset($_SESSION['USERNAME']) ? $_SESSION['USERNAME'] : '';
if ($email === '') {
    sso_galat('Sesi Anda tidak memuat identitas. Silakan login ulang.');
}

// Nama tampilan: ambil dari tabel dosen. `SELECT *` lalu pilih kolom yang ada,
// karena nama kolom tabel dosen tidak seragam antar server (nama_dos vs nm_dos,
// gelar vs gelar_blkg). Kalau gagal, email dipakai sebagai nama sementara.
$nama = $email;
try {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM dosen WHERE email_dos = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
        if ($hasil && ($baris = mysqli_fetch_assoc($hasil))) {
            $inti = isset($baris['nama_dos']) ? $baris['nama_dos'] : (isset($baris['nm_dos']) ? $baris['nm_dos'] : '');
            $depan = isset($baris['gelar_dpn']) ? trim($baris['gelar_dpn']) : '';
            $belakang = isset($baris['gelar']) ? $baris['gelar'] : (isset($baris['gelar_blkg']) ? $baris['gelar_blkg'] : '');
            $lengkap = trim(($depan !== '' ? $depan . ' ' : '') . $inti);
            if (!empty($belakang)) {
                $lengkap .= ', ' . $belakang;
            }
            if ($lengkap !== '') {
                $nama = $lengkap;
            }
        }
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    $nama = $email;
}

// Peran TIDAK menentukan hak di sistem akreditasi. Sistem itu punya 4 peran
// (OPERATOR/VERIFIKATOR/ADMIN/PIMPINAN) sedangkan SPMI hanya punya 2 portal.
// `r` dikirim sebagai petunjuk saja dan diabaikan oleh penerima.
$peran = isset($_SESSION['PORTAL']) ? $_SESSION['PORTAL'] : 'Auditee';

$payload = array(
    'e' => $email,
    'n' => mb_substr($nama, 0, 120),
    'r' => $peran,
    'j' => bin2hex(random_bytes(16)), // id unik: token hanya boleh dipakai sekali
    'x' => time() + 90,               // berlaku 90 detik, diperiksa penerima
);

$isi = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
$tanda_tangan = rtrim(strtr(base64_encode(hash_hmac('sha256', $isi, $sso_lamptkes_secret, true)), '+/', '-_'), '=');

if (ob_get_level() > 0) {
    ob_end_clean();
}

header('Location: ' . rtrim($sso_lamptkes_url, '/') . '/index.php?m=masuk&a=sso&t=' . $isi . '.' . $tanda_tangan, true, 302);
exit();
```

Menu di sidebar sudah menunjuk ke `lamptkes.php` — tidak perlu mengubah
`aside-auditee.php`.

---

## 3. Sisi sistem ini — yang diperiksa `sso_terima()`

| Pemeriksaan | Perilaku kalau gagal |
|---|---|
| bentuk token (satu titik, ≤ 8 KB, base64 sah) | ditolak |
| `hash_equals()` HMAC-SHA256 atas `isi` | ditolak — **sebelum** isi di-parse |
| `e` email sah, `j` 32 heksadesimal, `x` > 0 | ditolak |
| `x < time()` (kedaluwarsa 90 detik) | ditolak |
| email ada di tabel `pengguna` | ditolak, **tidak** ada akun dibuat otomatis |
| `pengguna.aktif = 1` | ditolak |
| `j` belum ada di `sso_nonce` | percobaan kedua ditolak (`INSERT IGNORE`, kunci primer yang memutuskan) |

Peran **selalu** dari `pengguna.peran_kode` di DB, bukan dari `r` di token.

Galat tidak pernah berupa stack trace: semua pesan berbahasa Indonesia dan
ditampilkan di form masuk, sehingga pengguna masih bisa memakai sandi lokal.

---

## 4. Menguji manual

### 4.1 Uji otomatis (tanpa HTTP)

```bash
cd /Users/yogiprasetyasadewa/Documents/03_Proyek/LAM-PTKES
php web/uji/uji_sso.php
```

Harus berakhir `SEMUA UJI LULUS` dan exit 0. Uji membuat akun nonaktif sendiri
dan menghapusnya lagi di akhir; tabel `sso_nonce` juga dibersihkan.

### 4.2 Uji dua server (SPMI → LAM-PTKes)

1. Jalankan sistem akreditasi:
   ```bash
   cd /Users/yogiprasetyasadewa/Documents/03_Proyek/LAM-PTKES/web
   php -S 127.0.0.1:8003 -t .
   ```
2. Pastikan `$sso_lamptkes_url = 'http://127.0.0.1:8003'` dan
   `$sso_lamptkes_secret` **sama** dengan `SSO_RAHASIA` di `web/system/config.php`.
3. Jalankan SPMI lokal, login sebagai akun yang **juga** ada di sini
   (`siti@bbg.ac.id`, `maimunah@bbg.ac.id`, `zulfikar@bbg.ac.id`,
   `zahriah@bbg.ac.id`).
4. Klik menu **LAM-PTKes** di sidebar. Browser harus mendarat di dashboard
   akreditasi, sudah masuk, tanpa form login.
5. Cek `sso_nonce` bertambah satu baris:
   ```bash
   docker exec si-lab-mysql mysql -uroot -proot bbg_lamptkes \
     -e "SELECT nonce,email,dipakai FROM sso_nonce ORDER BY dipakai DESC LIMIT 3;"
   ```

### 4.3 Uji penolakan yang harus dicoba

| Yang dilakukan | Hasil yang benar |
|---|---|
| Klik menu LAM-PTKes dua kali dengan tombol Back lalu Refresh | "Tautan SSO ini sudah dipakai" |
| Tempel tautan yang disalin > 90 detik lalu | "Tautan SSO kedaluwarsa (berlaku 90 detik)" |
| Ubah satu huruf pada bagian sebelum titik di `?t=` | "Tanda tangan tautan SSO tidak sah" |
| Login SPMI dengan akun yang tidak terdaftar di sini | "Akun belum terdaftar di sistem akreditasi. Hubungi Admin UPPS." — dan `SELECT COUNT(*) FROM pengguna` **tidak** bertambah |
| Nonaktifkan akun di `m=pengguna`, lalu klik menu lagi | "Akun ini dinonaktifkan. Hubungi Admin UPPS." |
| Buka `?m=masuk&a=sso` tanpa `t=` | "Tautan SSO tidak lengkap" |

Kalau semua tautan ditolak padahal secret terlihat sama: periksa spasi/baris
baru di akhir nilai config, dan pastikan kedua sisi memakai charset UTF-8 tanpa
BOM.

---

## 5. Kalau nanti mau mengaktifkan SSO untuk SIM-LKPS juga

Jangan menyalin `sso.php` dengan nilai rahasia yang sama. Satu rahasia dipakai
bersama oleh dua penerima berbeda berarti tautan yang bocor dari sistem yang
lemah bisa dipakai di sistem yang kuat. Pakai `$sso_lkps_secret` yang terpisah
(seperti sekarang) dan satu kolom nonce per penerima.
