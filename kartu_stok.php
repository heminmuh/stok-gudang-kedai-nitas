<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Kartu Stok';
$active = 'kartu';

$barangs = rows('SELECT id_barang, kode_barang, nama_barang, satuan, stok_saat_ini FROM barang ORDER BY nama_barang');
$idb = (int)g('id');
$dari = g('dari');
$sampai = g('sampai');
if ($dari !== '' && !valid_date($dari)) {
    $dari = '';
}
if ($sampai !== '' && !valid_date($sampai)) {
    $sampai = '';
}

$barang = null;
$mut = [];
if ($idb > 0) {
    $barang = row('SELECT * FROM barang WHERE id_barang = ?', [$idb]);
    if ($barang !== null) {
        $sql = 'SELECT k.*, u.nama_lengkap FROM kartu_stok k LEFT JOIN pengguna u ON u.id_pengguna = k.id_pengguna WHERE k.id_barang = ?';
        $par = [$idb];
        if ($dari !== '') {
            $sql .= ' AND k.waktu >= ?';
            $par[] = $dari . ' 00:00:00';
        }
        if ($sampai !== '') {
            $sql .= ' AND k.waktu <= ?';
            $par[] = $sampai . ' 23:59:59';
        }
        $sql .= ' ORDER BY k.waktu ASC, k.id_kartu ASC';
        $mut = rows($sql, $par);
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Kartu Stok</h1><div class="sub">Riwayat seluruh mutasi (masuk, keluar, adjustment) per barang beserta saldo</div></div>
    <?php if ($barang): ?><button class="btn btn-outline-secondary btn-sm no-print" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button><?php endif; ?>
</div>

<div class="card mb-3 no-print"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label">Barang</label>
            <select name="id" class="form-select" required><option value="">- pilih barang -</option>
            <?php foreach ($barangs as $b): ?><option value="<?= (int)$b['id_barang'] ?>" <?= $idb === (int)$b['id_barang'] ? 'selected' : '' ?>><?= e($b['kode_barang'] . ' - ' . $b['nama_barang']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Dari tanggal</label><input type="date" name="dari" class="form-control" value="<?= e($dari) ?>"></div>
        <div class="col-md-3"><label class="form-label">Sampai tanggal</label><input type="date" name="sampai" class="form-control" value="<?= e($sampai) ?>"></div>
        <div class="col-md-1"><button class="btn btn-navy w-100">Tampil</button></div>
    </form>
</div></div>

<?php if ($barang): ?>
<div class="card">
    <div class="card-header"><?= e($barang['kode_barang'] . ' - ' . $barang['nama_barang']) ?> <span class="text-muted fw-normal">&middot; stok saat ini <?= nf($barang['stok_saat_ini']) ?> <?= e($barang['satuan']) ?></span></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Waktu</th><th>Referensi</th><th>Jenis</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo</th><th>Keterangan</th><th>Petugas</th></tr></thead>
        <tbody>
        <?php foreach ($mut as $m): $j = (float)$m['jumlah']; ?>
            <tr>
                <td class="text-nowrap"><?= e(tglwaktu($m['waktu'])) ?></td>
                <td><?= e($m['referensi']) ?></td>
                <td><span class="badge text-bg-<?= $m['jenis'] === 'masuk' ? 'success' : ($m['jenis'] === 'keluar' ? 'warning' : 'info') ?>"><?= e(ucfirst($m['jenis'])) ?></span></td>
                <td class="text-end num"><?= $j > 0 ? nf($j) : '' ?></td>
                <td class="text-end num"><?= $j < 0 ? nf(abs($j)) : '' ?></td>
                <td class="text-end num"><strong><?= nf($m['saldo']) ?></strong></td>
                <td><?= e($m['keterangan']) ?></td>
                <td><?= e($m['nama_lengkap']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$mut): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada mutasi pada periode ini.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php else: ?>
<div class="alert alert-light border">Pilih barang untuk melihat kartu stoknya.</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
