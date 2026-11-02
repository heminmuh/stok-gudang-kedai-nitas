<?php
require_once __DIR__ . '/auth.php';
require_login();
$me = user();
$page_title = isset($page_title) ? $page_title : APP_NAME;
$active = isset($active) ? $active : '';

$menu = [
    ['Dashboard', 'dashboard.php', 'bi-speedometer2', 'dashboard'],
    ['_', 'TRANSAKSI'],
    ['Barang Masuk', 'inbound.php', 'bi-box-arrow-in-down', 'inbound'],
    ['Barang Keluar', 'outbound.php', 'bi-box-arrow-up', 'outbound'],
    ['Stock Opname', 'opname.php', 'bi-clipboard-check', 'opname'],
    ['_', 'MONITORING'],
    ['Stok & Peringatan', 'stok.php', 'bi-exclamation-triangle', 'stok'],
    ['Kartu Stok', 'kartu_stok.php', 'bi-journal-text', 'kartu'],
    ['Laporan', 'laporan.php', 'bi-file-earmark-bar-graph', 'laporan'],
    ['_', 'DATA MASTER'],
    ['Barang', 'barang.php', 'bi-box-seam', 'barang'],
    ['Supplier', 'supplier.php', 'bi-truck', 'supplier'],
    ['Lokasi Rak', 'rak.php', 'bi-grid-3x3-gap', 'rak'],
];
if (is_admin()) {
    $menu[] = ['Pengguna', 'pengguna.php', 'bi-people', 'pengguna'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%A6%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<aside id="sidebar" class="no-print">
    <div class="brand">
        <span class="brand-ico"><i class="bi bi-box-seam-fill"></i></span>
        <div>
            <div class="brand-name">Stok Gudang</div>
            <div class="brand-sub">Kedai Nita's Rembang</div>
        </div>
    </div>
    <nav>
        <?php foreach ($menu as $m): ?>
            <?php if ($m[0] === '_'): ?>
                <div class="nav-label"><?= e($m[1]) ?></div>
            <?php else: ?>
                <a href="<?= e($m[1]) ?>" class="nav-item<?= $active === $m[3] ? ' active' : '' ?>">
                    <i class="bi <?= e($m[2]) ?>"></i><span><?= e($m[0]) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>
<div id="backdrop" class="no-print"></div>

<div id="main">
    <header class="topbar no-print">
        <button type="button" id="menu-toggle" class="btn btn-light btn-sm d-lg-none" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
        <div class="topbar-title"><?= e($page_title) ?></div>
        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="fw-semibold small"><?= e($me['nama']) ?></div>
                <div class="text-muted" style="font-size:.72rem"><?= $me['peran'] === 'admin' ? 'Admin / Owner' : 'Petugas Gudang' ?></div>
            </div>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        </div>
    </header>
    <main class="content">
        <?php foreach (flashes() as $f): ?>
            <div class="alert alert-<?= e($f[0]) ?> alert-dismissible fade show no-print" role="alert">
                <?= e($f[1]) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endforeach; ?>
