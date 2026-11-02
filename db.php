<?php
require_once __DIR__ . '/../config.php';
date_default_timezone_set(TIMEZONE);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $ex) {
            http_response_code(500);
            echo '<h3>Koneksi database gagal</h3><p>Periksa pengaturan DB_HOST, DB_NAME, DB_USER, DB_PASS pada <code>config.php</code>.</p>';
            if (DEBUG) {
                echo '<pre>' . htmlspecialchars($ex->getMessage()) . '</pre>';
            }
            exit;
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function row(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetch();
    return $r ? $r : null;
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}
