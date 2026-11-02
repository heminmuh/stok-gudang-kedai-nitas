<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Dashboard';
$active = 'dashboard';

$today = date('Y-m-d');
$limitExp = date('Y-m-d', strtotime('+' . EXPIRY_DAYS . ' days'));
$d7 = date('Y-m-d', strtotime('-6 days'));
$d30 = date('Y-m-d', strtotime('-30 days'));

$totalItem = (int)val('SELECT COUNT(*) FROM barang WHERE aktif = 1');
$totalLow = (int)val('SELECT COUNT(*) FROM barang WHERE aktif = 1 AND stok_saat_ini <= stok_minimum');
$totalExp = (int)val(
    'SELECT COUNT(*) FROM inbound_detail WHERE sisa_jumlah > 0 AND tgl_kedaluwarsa IS NOT NULL AND tgl_kedaluwarsa <= ?',
    [$limitExp]
);
$trxToday = (int)val("SELECT COUNT(*) FROM inbound WHERE tanggal = ? AND jenis = 'pembelian'", [$today])
    + (int)val('SELECT COUNT(*) FROM outbound WHERE tanggal = ?', [$today]);
$nilaiStok = is_admin() ? (float)val('SELECT COALESCE(SUM(sisa_jumlah * harga_beli),0) FROM inbound_detail WHERE sisa_jumlah > 0') : 0;
$opnameMenunggu = (int)val("SELECT COUNT(*) FROM stock_opname WHERE status = 'menunggu'");

$low = rows(
    'SELECT b.kode_barang, b.nama_barang, b.satuan, b.stok_saat_ini, b.stok_minimum
     FROM barang b WHERE b.aktif = 1 AND b.stok_saat_ini <= b.stok_minimum
     ORDER BY (b.stok_saat_ini - b.stok_minimum) ASC LIMIT 8'
);
$exp = rows(
    'SELECT d.sisa_jumlah, d.tgl_kedaluwarsa, b.nama_barang, b.satuan, i.no_transaksi
     FROM inbound_detail d
     JOIN barang b ON b.id_barang = d.id_barang
     JOIN inbound i ON i.id_inbound = d.id_inbound
     WHERE d.sisa_jumlah > 0 AND d.tgl_kedaluwarsa IS NOT NULL AND d.tgl_kedaluwarsa <= ?
     ORDER BY d.tgl_kedaluwarsa ASC LIMIT 8',
    [$limitExp]
);

// grafik transaksi 7 hari terakhir
$labels = [];
$map = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($d));
    $map[$d] = [0, 0];
}
foreach (rows("SELECT tanggal, COUNT(*) c FROM inbound WHERE jenis = 'pembelian' AND tanggal >= ? GROUP BY tanggal", [$d7]) as $r) {
    if (isset($map[$r['tanggal']])) {
        $map[$r['tanggal']][0] = (int)$r['c'];
    }
}
foreach (rows('SELECT tanggal, COUNT(*) c FROM outbound WHERE tanggal >= ? GROUP BY tanggal', [$d7]) as $r) {
    if (isset($map[$r['tanggal']])) {
        $map[$r['tanggal']][1] = (int)$r['c'];
    }
}
$serMasuk = [];
$serKeluar = [];
foreach ($map as $v) {
    $serMasuk[] = $v[0];
    $serKeluar[] = $v[1];
}

$fast = rows(
    'SELECT b.nama_barang, b.satuan, SUM(od.jumlah) AS total
     FROM outbound_detail od
     JOIN outbound o ON o.id_outbound = od.id_outbound
     JOIN barang b ON b.id_barang = od.id_barang
     WHERE o.tanggal >= ?
     GROUP BY b.id_barang, b.nama_barang, b.satuan
     ORDER BY total DESC LIMIT 5',
    [$d30]
);
$slow = rows(
    'SELECT b.kode_barang, b.nama_barang, b.satuan, b.stok_saat_ini FROM barang b
     WHERE b.aktif = 1 AND b.stok_saat_ini > 0 AND NOT EXISTS (
        SELECT 1 FROM outbound_detail od JOIN outbound o ON o.id_outbound = od.id_outbound
        WHERE od.id_barang = b.id_barang AND o.tanggal >= ?)
     ORDER BY b.stok_saat_ini DESC LIMIT 5',
    [$d30]
);
$recent = rows(
    "(SELECT 'Masuk' AS jenis, id_inbound AS id, no_transaksi, tanggal, dibuat_pada FROM inbound WHERE jenis = 'pembelian')
     UNION ALL
     (SELECT 'Keluar' AS jenis, id_outbound AS id, no_transaksi, tanggal, dibuat_pada FROM outbound)
     ORDER BY tanggal DESC, dibuat_pada DESC LIMIT 8"
);

$fastLabels = [];
$fastVals = [];
foreach ($fast as $f) {
    $fastLabels[] = $f['nama_barang'] . ' (' . $f['satuan'] . ')';
    $fastVals[] = (float)$f['total'];
}

$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script><script>
(function(){
  if (typeof Chart === "undefined") return;
  new Chart(document.getElementById("chTrx"), {
    type: "bar",
    data: { labels: ' . json_encode($labels) . ', datasets: [
      { label: "Barang masuk", data: ' . json_encode($serMasuk) . ', backgroundColor: "#2DD4BF", borderRadius: 4 },
      { label: "Barang keluar", data: ' . json_encode($serKeluar) . ', backgroundColor: "#FFC93C", borderRadius: 4 }
    ]},
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
  });
  var fl = ' . json_encode($fastLabels) . ';
  if (fl.length) new Chart(document.getElementById("chFast"), {
    type: "bar",
    data: { labels: fl, datasets: [{ label: "Jumlah keluar (30 hari)", data: ' . json_encode($fastVals) . ', backgroundColor: "#16306B", borderRadius: 4 }] },
    options: { indexAxis: "y", responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
  });
})();
</script>';

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="sub">Ringkasan kondisi gudang per <?= e(tgl($today)) ?></div>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="inbound.php?new=1" class="btn btn-gold btn-sm"><i class="bi bi-plus-lg"></i> Barang Masuk</a>
        <a href="outbound.php?new=1" class="btn btn-navy btn-sm"><i class="bi bi-dash-lg"></i> Barang Keluar</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card"><div class="stat">
        <div class="ico" style="background:#E6EAF9;color:#16306B"><i class="bi bi-box-seam"></i></div>
        <div><div class="num"><?= $totalItem ?></div><div class="lbl">Item barang aktif</div></div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card"><div class="stat">
        <div class="ico" style="background:#FFF3CF;color:#8a6200"><i class="bi bi-exclamation-triangle"></i></div>
        <div><div class="num"><?= $totalLow ?></div><div class="lbl">Stok menipis / habis</div></div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card"><div class="stat">
        <div class="ico" style="background:#FFE1E5;color:#b4233a"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="num"><?= $totalExp ?></div><div class="lbl">Batch segera kedaluwarsa (<?= EXPIRY_DAYS ?> hari)</div></div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card"><div class="stat">
        <div class="ico" style="background:#D8F7F1;color:#0b7d6e"><i class="bi bi-arrow-left-right"></i></div>
        <div><div class="num"><?= $trxToday ?></div><div class="lbl">Transaksi hari ini</div></div>
    </div></div></div>
</div>

<?php if (is_admin() && $opnameMenunggu > 0): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center py-2">
        <span><i class="bi bi-clipboard-check me-1"></i> Ada <strong><?= $opnameMenunggu ?></strong> stock opname menunggu persetujuan Anda.</span>
        <a href="opname.php" class="btn btn-sm btn-navy">Tinjau</a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Transaksi 7 hari terakhir</div>
            <div class="card-body"><div style="height:260px"><canvas id="chTrx"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Barang paling sering keluar (30 hari)</div>
            <div class="card-body">
                <?php if ($fast): ?>
                    <div style="height:260px"><canvas id="chFast"></canvas></div>
                <?php else: ?>
                    <p class="text-muted mb-0">Belum ada pengeluaran dalam 30 hari terakhir.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-exclamation-triangle text-warning"></i> Peringatan stok menipis</span><a href="stok.php" class="small">Lihat semua</a></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Barang</th><th class="text-end">Stok</th><th class="text-end">Minimum</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($low as $r): $st = status_stok((float)$r['stok_saat_ini'], (float)$r['stok_minimum']); ?>
                        <tr>
                            <td><strong><?= e($r['nama_barang']) ?></strong><div class="hint"><?= e($r['kode_barang']) ?></div></td>
                            <td class="text-end num"><?= nf($r['stok_saat_ini']) ?> <?= e($r['satuan']) ?></td>
                            <td class="text-end num"><?= nf($r['stok_minimum']) ?></td>
                            <td><?= badge($st) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$low): ?><tr><td colspan="4" class="text-center text-muted py-3">Semua stok di atas batas minimum.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-hourglass-split text-danger"></i> Segera kedaluwarsa (&le; <?= EXPIRY_DAYS ?> hari)</span><a href="laporan.php?jenis=kedaluwarsa" class="small">Laporan</a></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Barang / batch</th><th class="text-end">Sisa</th><th>Kedaluwarsa</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($exp as $r): ?>
                        <tr>
                            <td><strong><?= e($r['nama_barang']) ?></strong><div class="hint"><?= e($r['no_transaksi']) ?></div></td>
                            <td class="text-end num"><?= nf($r['sisa_jumlah']) ?> <?= e($r['satuan']) ?></td>
                            <td><?= e(tgl($r['tgl_kedaluwarsa'])) ?></td>
                            <td><?= badge(status_exp($r['tgl_kedaluwarsa'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$exp): ?><tr><td colspan="4" class="text-center text-muted py-3">Tidak ada batch yang mendekati kedaluwarsa.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Transaksi terbaru</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>No. transaksi</th><th>Tanggal</th><th>Jenis</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): $isIn = $r['jenis'] === 'Masuk'; ?>
                        <tr>
                            <td><a href="<?= $isIn ? 'inbound.php' : 'outbound.php' ?>?view=<?= (int)$r['id'] ?>"><?= e($r['no_transaksi']) ?></a></td>
                            <td><?= e(tgl($r['tanggal'])) ?></td>
                            <td><span class="badge text-bg-<?= $isIn ? 'success' : 'warning' ?>"><?= e($r['jenis']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="3" class="text-center text-muted py-3">Belum ada transaksi.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Slow moving (tidak keluar &gt; 30 hari)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Barang</th><th class="text-end">Stok</th></tr></thead>
                    <tbody>
                    <?php foreach ($slow as $r): ?>
                        <tr><td><?= e($r['nama_barang']) ?> <span class="hint"><?= e($r['kode_barang']) ?></span></td><td class="text-end num"><?= nf($r['stok_saat_ini']) ?> <?= e($r['satuan']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$slow): ?><tr><td colspan="2" class="text-center text-muted py-3">Tidak ada barang slow moving.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (is_admin()): ?>
                <div class="card-footer bg-white small text-muted">Nilai persediaan saat ini: <strong><?= e(rupiah($nilaiStok)) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
