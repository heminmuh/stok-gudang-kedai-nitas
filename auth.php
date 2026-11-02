<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('stoknita');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function user()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function is_admin(): bool
{
    $u = user();
    return $u !== null && $u['peran'] === 'admin';
}

function require_login(): void
{
    $u = user();
    if ($u === null) {
        redirect('login.php');
    }
    $last = isset($_SESSION['last']) ? (int)$_SESSION['last'] : 0;
    if (time() - $last > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        redirect('login.php?timeout=1');
    }
    $_SESSION['last'] = time();

    // pastikan akun masih aktif
    $aktif = val('SELECT aktif FROM pengguna WHERE id_pengguna = ?', [$u['id']]);
    if ((int)$aktif !== 1) {
        session_unset();
        session_destroy();
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('danger', 'Akses ditolak: fitur ini hanya untuk Admin/Owner.');
        redirect('dashboard.php');
    }
}

function attempt_login(string $username, string $password): bool
{
    $r = row('SELECT * FROM pengguna WHERE username = ? AND aktif = 1', [$username]);
    if ($r === null) {
        // samakan waktu proses agar username tidak mudah ditebak
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345');
        return false;
    }
    if (!password_verify($password, $r['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$r['id_pengguna'],
        'nama' => $r['nama_lengkap'],
        'username' => $r['username'],
        'peran' => $r['peran'],
    ];
    $_SESSION['last'] = time();
    return true;
}
