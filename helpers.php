<?php
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url)
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Ambil nilai string dari $_POST (sudah di-trim). */
function p(string $key, string $default = ''): string
{
    return (isset($_POST[$key]) && is_string($_POST[$key])) ? trim($_POST[$key]) : $default;
}

/** Ambil nilai string dari $_GET (sudah di-trim). */
function g(string $key, string $default = ''): string
{
    return (isset($_GET[$key]) && is_string($_GET[$key])) ? trim($_GET[$key]) : $default;
}

function p_arr(string $key): array
{
    return (isset($_POST[$key]) && is_array($_POST[$key])) ? $_POST[$key] : [];
}

function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x !== false && $x->format('Y-m-d') === $d;
}

/* ---------- Flash message ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = (isset($_POST['_token']) && is_string($_POST['_token'])) ? $_POST['_token'] : '';
    $s = isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
    if ($t === '' || $s === '' || !hash_equals($s, $t)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa atau tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}

/* ---------- Format ---------- */
function nf($n, int $d = 2): string
{
    $s = number_format((float)$n, $d, ',', '.');
    if ($d > 0 && strpos($s, ',') !== false) {
        $s = rtrim(rtrim($s, '0'), ',');
    }
    return $s;
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tgl($d): string
{
    return $d ? date('d/m/Y', strtotime($d)) : '-';
}

function tglwaktu($d): string
{
    return $d ? date('d/m/Y H:i', strtotime($d)) : '-';
}

/* ---------- Status ---------- */
function status_stok(float $stok, float $min): array
{
    if ($stok <= 0) {
        return ['Habis', 'danger'];
    }
    if ($stok <= $min) {
        return ['Menipis', 'warning'];
    }
    return ['Aman', 'success'];
}

function days_until($d)
{
    if (!$d) {
        return null;
    }
    $a = new DateTime(date('Y-m-d'));
    $b = new DateTime(substr((string)$d, 0, 10));
    return (int)$a->diff($b)->format('%r%a');
}

function status_exp($d): array
{
    $days = days_until($d);
    if ($days === null) {
        return ['Tanpa tanggal', 'secondary'];
    }
    if ($days < 0) {
        return ['Kedaluwarsa', 'danger'];
    }
    if ($days === 0) {
        return ['Hari ini', 'danger'];
    }
    if ($days <= EXPIRY_DAYS) {
        return [$days . ' hari lagi', 'warning'];
    }
    return ['Aman', 'success'];
}

function badge(array $s): string
{
    return '<span class="badge text-bg-' . e($s[1]) . '">' . e($s[0]) . '</span>';
}

function tujuan_list(): array
{
    return [
        'produksi_dapur' => 'Produksi dapur',
        'penjualan' => 'Penjualan langsung',
        'rusak_kedaluwarsa' => 'Rusak / kedaluwarsa (dibuang)',
        'lainnya' => 'Lainnya',
    ];
}

function cut_str(string $s, int $n): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
}
