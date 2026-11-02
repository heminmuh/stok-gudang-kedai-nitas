<?php
/*
 * PEMASANG DATABASE (sekali pakai)
 * 1) Buat database kosong di hosting, isi kredensial pada config.php
 * 2) Buka halaman ini, klik "Pasang database"
 * 3) HAPUS file install.php setelah selesai
 */
require_once __DIR__ . '/includes/db.php';

$lock = __DIR__ . '/includes/install.lock';
$msg = '';
$ok = false;

$checks = [
    'PHP 7.4 atau lebih baru (terdeteksi ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'Ekstensi PDO MySQL' => extension_loaded('pdo_mysql'),
    'Folder uploads dapat ditulis' => is_writable(__DIR__ . '/uploads'),
    'Folder includes dapat ditulis (untuk install.lock)' => is_writable(__DIR__ . '/includes'),
];

$pdo = db();
$sudah = false;
try {
    $sudah = $pdo->query("SHOW TABLES LIKE 'pengguna'")->fetchColumn() !== false;
} catch (Throwable $ex) {
    $sudah = false;
}
if (file_exists($lock)) {
    $sudah = true;
}

if (!$sudah && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = file_get_contents(__DIR__ . '/database.sql');
    if ($sql === false) {
        $msg = 'File database.sql tidak ditemukan.';
    } else {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $stmts = preg_split('/;\s*[\r\n]+/', $sql);
        try {
            foreach ($stmts as $s) {
                $s = trim($s);
                if ($s !== '') {
                    $pdo->exec($s);
                }
            }
            @file_put_contents($lock, date('c'));
            $ok = true;
            $sudah = true;
        } catch (Throwable $ex) {
            $msg = 'Pemasangan gagal: ' . $ex->getMessage() . ' -- Kosongkan database lalu coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pasang Database</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:640px">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h1 class="h4 mb-3">Pemasangan Sistem Stok Gudang</h1>
        <ul class="list-group mb-3">
            <?php foreach ($checks as $label => $pass): ?>
                <li class="list-group-item d-flex justify-content-between"><span><?= htmlspecialchars($label) ?></span><span class="badge text-bg-<?= $pass ? 'success' : 'danger' ?>"><?= $pass ? 'OK' : 'Perlu diperbaiki' ?></span></li>
            <?php endforeach; ?>
            <li class="list-group-item d-flex justify-content-between"><span>Koneksi database</span><span class="badge text-bg-success">OK</span></li>
        </ul>
        <?php if ($msg !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($ok): ?>
            <div class="alert alert-success">Database berhasil dipasang. Masuk dengan username <strong>nita</strong> dan kata sandi <strong>nita123</strong>.</div>
        <?php endif; ?>
        <?php if ($sudah): ?>
            <div class="alert alert-warning">Database sudah terpasang. <strong>Hapus file install.php</strong> dari hosting demi keamanan.</div>
            <a class="btn btn-warning" href="login.php">Ke halaman login</a>
        <?php else: ?>
            <p class="text-muted">Tombol di bawah akan membuat 11 tabel beserta data contoh dan akun Admin/Owner.</p>
            <form method="post"><button class="btn btn-warning">Pasang database</button></form>
        <?php endif; ?>
    </div></div>
</div>
</body>
</html>
