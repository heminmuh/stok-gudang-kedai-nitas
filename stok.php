<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Stok & Peringatan';
$active = 'stok';

$limitExp = date('Y-m-d', strtotime('+' . EXPIRY_DAYS . ' days'));
$filter = g('status', 'semua');

$all = rows(
    'SELECT b.*, r.nama_rak, s.nama_supplier,
        (SELECT MIN(d.tgl_kedaluwarsa) FROM inbound_detail d WHERE d.id_barang = b.id_barang AND d.sisa_jumlah > 0) AS exp_terdekat
     FROM barang b
     LEFT JOIN lokasi_rak r ON r.id_rak = b.id_rak
     LEFT JOIN supplier s ON s.id_supplier = b.id_supplier_utama
     WHERE b.aktif = 1 ORDER BY b.nama_barang'
);

$cnt = ['aman' => 0, 'menipis' => 0, 'habis' => 0];
$stocks = [];
$restock = [];
foreach ($all as $b) {
    $st = status_stok((float)$b['stok_saat_ini'], (float)$b['stok_minimum']);
    $key = strtolower($st[0]);
    $cnt[$key]++;
    $b['_st'] = $st;
    if ($filter === 'semua' || $filter === $key) {
        $stocks[] = $b;
    }
    if ($key !== 'aman') {
        $target = (float)$b['stok_minimum'] * 2;
        $saran = max(1, ceil($target - (float)$b['stok_saat_ini']));
        $b['_saran'] = $saran;
        $restock[] = $b;
    }
}

$batches = rows(
    'SELECT d.sisa_jumlah, d.tgl_kedaluwarsa, b.nama_barang, b.satuan, i.no_transaksi, i.tanggal
     FROM inbound_detail d
     JOIN barang b ON b.id_barang = d.id_barang
     JOIN inbound i ON i.id_inbound = d.id_inbound
     WHERE d.sisa_jumlah > 0 AND d.tgl_kedaluwarsa IS NOT NULL AND d.tgl_kedaluwarsa <= ?
     ORDER BY d.tgl_kedaluwarsa ASC',
    [$limitExp]
);

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Stok &amp; Peringatan</h1><div class="sub">Posisi stok terkini, low stock alert, expiring soon alert, dan rekomendasi restock</div></div>
    <button class="btn btn-outline-secondary btn-sm no-print" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
</div>

<div class="row g-3 mb-3 no-print">
    <div class="col-4"><a class="text-decoration-none" href="stok.php?status=aman"><div class="card"><div class="stat"><div class="ico" style="background:#D8F7F1;color:#0b7d6e"><i class="bi bi-check2-circle"></i></div><div><div class="num"><?= $cnt['aman'] ?></div><div class="lbl">Aman</div></div></div></div></a></div>
    <div class="col-4"><a class="text-decoration-none" href="stok.php?status=menipis"><div class="card"><div class="stat"><div class="ico" style="background:#FFF3CF;color:#8a6200"><i class="bi bi-exclamation-triangle"></i></div><div><div class="num"><?= $cnt['menipis'] ?></div><div class="lbl">Menipis</div></div></div></div></a></div>
    <div class="col-4"><a class="text-decoration-none" href="stok.php?status=habis"><div class="card"><div class="stat"><div class="ico" style="background:#FFE1E5;color:#b4233a"><i class="bi bi-x-octagon"></i></div><div><div class="num"><?= $cnt['habis'] ?></div><div class="lbl">Habis</div></div></div></div></a></div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Posisi stok <?= $filter !== 'semua' ? '(' . e($filter) . ')' : '' ?></span>
        <?php if ($filter !== 'semua'): ?><a href="stok.php" class="small no-print">Tampilkan semua</a><?php endif; ?>
    </div>
    <div class="card-body pb-2 no-print"><input type="search" class="form-control" placeholder="Cari barang..." data-filter="#tbl"></div>
    <div class="table-responsive"><table class="table table-hover mb-0" id="tbl">
        <thead><tr><th>Kode</th><th>Barang</th><th class="text-end">Stok</th><th class="text-end">Minimum</th><th>Status</th><th>Kedaluwarsa terdekat</th><th>Lokasi</th></tr></thead>
        <tbody>
        <?php foreach ($stocks as $b): ?>
            <tr>
                <td><strong><?= e($b['kode_barang']) ?></strong></td>
                <td><?= e($b['nama_barang']) ?><div class="hint"><?= e($b['kategori']) ?></div></td>
                <td class="text-end num"><?= nf($b['stok_saat_ini']) ?> <?= e($b['satuan']) ?></td>
                <td class="text-end num"><?= nf($b['stok_minimum']) ?></td>
                <td><?= badge($b['_st']) ?></td>
                <td><?= $b['exp_terdekat'] ? e(tgl($b['exp_terdekat'])) . ' ' . badge(status_exp($b['exp_terdekat'])) : '-' ?></td>
                <td><?= e($b['nama_rak']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$stocks): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Rekomendasi restock</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Barang</th><th class="text-end">Stok</th><th class="text-end">Saran beli</th><th>Supplier</th></tr></thead>
                <tbody>
                <?php foreach ($restock as $b): ?>
                    <tr>
                        <td><strong><?= e($b['nama_barang']) ?></strong></td>
                        <td class="text-end num"><?= nf($b['stok_saat_ini']) ?></td>
                        <td class="text-end num"><strong><?= nf($b['_saran']) ?> <?= e($b['satuan']) ?></strong><?php if (is_admin()): ?><div class="hint">&asymp; <?= e(rupiah($b['_saran'] * (float)$b['harga_beli_terakhir'])) ?></div><?php endif; ?></td>
                        <td><?= e($b['nama_supplier']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$restock): ?><tr><td colspan="4" class="text-center text-muted py-3">Tidak ada barang yang perlu restock.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <div class="card-footer bg-white hint">Saran = 2 &times; stok minimum &minus; stok saat ini (dibulatkan ke atas).</div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Batch kedaluwarsa &amp; segera kedaluwarsa (&le; <?= EXPIRY_DAYS ?> hari)</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Barang</th><th class="text-end">Sisa</th><th>Kedaluwarsa</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($batches as $r): ?>
                    <tr>
                        <td><strong><?= e($r['nama_barang']) ?></strong><div class="hint"><?= e($r['no_transaksi']) ?></div></td>
                        <td class="text-end num"><?= nf($r['sisa_jumlah']) ?> <?= e($r['satuan']) ?></td>
                        <td><?= e(tgl($r['tgl_kedaluwarsa'])) ?></td>
                        <td><?= badge(status_exp($r['tgl_kedaluwarsa'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$batches): ?><tr><td colspan="4" class="text-center text-muted py-3">Tidak ada batch yang mendekati kedaluwarsa.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
