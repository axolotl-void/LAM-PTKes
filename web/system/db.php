<?php
declare(strict_types=1);

/**
 * Koneksi + helper query. PDO prepared statement, TANPA kecuali.
 *
 * Kenapa bukan mysqli + sanitize() seperti SPMI: `sanitize()` di SPMI adalah
 * strip_tags + mysqli_real_escape_string. Itu aman hanya kalau setiap query
 * ingat memakainya. Dengan 243 field dan 10 agent menulis kode, satu yang lupa
 * berarti data akreditasi bisa diubah siapa pun. Placeholder tidak bisa lupa
 * karena nilainya tidak pernah masuk ke string SQL.
 */

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, defined('DB_PORT') ? DB_PORT : '3306', DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // prepared statement ASLI
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        if (DEBUG) {
            throw $e;
        }
        http_response_code(500);
        exit('Koneksi database gagal. Hubungi pengelola sistem.');
    }
    return $pdo;
}

/** SELECT banyak baris. */
function q(string $sql, array $param = []): array
{
    $st = db()->prepare($sql);
    $st->execute($param);
    return $st->fetchAll();
}

/** SELECT satu baris, atau null. */
function q1(string $sql, array $param = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($param);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

/** SELECT satu nilai kolom pertama. */
function qv(string $sql, array $param = [], mixed $bawaan = null): mixed
{
    $st = db()->prepare($sql);
    $st->execute($param);
    $v = $st->fetchColumn();
    return $v === false ? $bawaan : $v;
}

/** INSERT/UPDATE/DELETE. Mengembalikan jumlah baris terpengaruh. */
function ex(string $sql, array $param = []): int
{
    $st = db()->prepare($sql);
    $st->execute($param);
    return $st->rowCount();
}

/** INSERT lalu kembalikan id barunya. */
function sisip(string $tabel, array $data): int
{
    $kolom = array_keys($data);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $tabel,
        implode(',', array_map(fn($k) => "`$k`", $kolom)),
        implode(',', array_fill(0, count($kolom), '?'))
    );
    ex($sql, array_values($data));
    return (int) db()->lastInsertId();
}

/**
 * UPDATE berdasarkan satu kolom kunci.
 * $data = ['kolom' => nilai]. Kunci tidak boleh ikut di $data.
 */
function ubah(string $tabel, array $data, string $kolomKunci, mixed $nilaiKunci): int
{
    $set = implode(',', array_map(fn($k) => "`$k` = ?", array_keys($data)));
    $sql = sprintf('UPDATE %s SET %s WHERE %s = ?', $tabel, $set, $kolomKunci);
    return ex($sql, [...array_values($data), $nilaiKunci]);
}

/** Bungkus beberapa tulisan jadi satu transaksi. */
function transaksi(callable $kerja): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $hasil = $kerja();
        $pdo->commit();
        return $hasil;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
