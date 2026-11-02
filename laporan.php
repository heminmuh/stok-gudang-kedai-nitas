<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Laporan';
$active = 'laporan';

$types = [
    'stok' => 'Posisi Stok',
    'masuk' => 'Barang Masuk',
    'keluar' => 'Barang Keluar',
    'kedaluwarsa' => 'Batch Kedaluwarsa',
    'opname' => 'Stock Opname',
];
if (is_admin()) {
    $types['pembelian'] = 'Pembelian & Pemakaian (Admin)';
}

$jenis = g('jenis', 'stok');
if (!isset($types[$jenis])) {
    $jenis = 'stok';
}
$today = date('Y-m-d');
$dari = g('dari', date('Y-m-01'));
$sampai = g('sampai', $today);
if (!valid_date($dari)) {
    $dari = date('Y-m-01');
}
if (!valid_date($sampai)) {
    $sampai = $today;
}
if ($dari > $sampai) {
    $tmp = $dari;
    $dari = $sampai;
    $sampai = $tmp;
}
$usePeriod = in_array($jenis, ['masuk', 'keluar', 'opname', 'pembelian'], true);
$tujuanList = tujuan_list();
$admin = is_admin();

$cols = [];
$data = [];
$foot = null;
$title = $types[$jenis];

if ($jenis === 'stok') {
    $cols = ['Kode', 'Barang', 'Kategori', 'Satuan', 'Stok', 'Minimum', 'Status', 'Kedaluwarsa terdekat', 'Lokasi'];
    if ($admin) {
        $cols[] = 'Nilai persediaan (Rp)';
    }
    $rs = rows(
        'SELECT b.*, r.nama_rak,
            (SELECT MIN(d.tgl_kedaluwarsa) FROM inbound_detail d WHERE d.id_barang = b.id_barang AND d.sisa_jumlah > 0) AS exp_terdekat,
            (SELECT COALESCE(SUM(d.sisa_jumlah * d.harga_beli),0) FROM inbound_detail d WHERE d.id_barang = b.id_barang AND d.sisa_jumlah > 0) AS nilai
         FROM barang b LEFT JOIN lokasi_rak r ON r.id_rak = b.id_rak WHERE b.aktif = 1 ORDER BY b.nama_barang'
    );
    $total = 0;
    foreach ($rs as $r) {
        $st = status_stok((float)$r['stok_saat_ini'], (float)$r['stok_minimum']);
        $line = [$r['kode_barang'], $r['nama_barang'], $r['kategori'], $r['satuan'], nf($r['stok_saat_ini']), nf($r['stok_minimum']), $st[0], $r['exp_terdekat'] ? tgl($r['exp_terdekat']) : '-', (string)$r['nama_rak']];
        if ($admin) {
            $line[] = number_format((float)$r['nilai'], 0, ',', '.');
            $total += (float)$r['nilai'];
        }
        $data[] = $line;
    }
    if ($admin) {
        $foot = array_fill(0, count($cols), '');
        $foot[count($cols) - 2] = 'Total nilai';
        $foot[count($cols) - 1] = number_format($total, 0, ',', '.');
    }
} elseif ($jenis === 'masuk') {
    $cols = ['Tanggal', 'No. transaksi', 'Supplier', 'Barang', 'Jumlah', 'Satuan'];
    if ($admin) {
        $cols[] = 'Harga beli (Rp)';
        $cols[] = 'Subtotal (Rp)';
    }
    $cols[] = 'Kedaluwarsa';
    $rs = rows(
        "SELECT i.tanggal, i.no_transaksi, s.nama_supplier, b.nama_barang, b.satuan, d.jumlah, d.harga_beli, d.tgl_kedaluwarsa
         FROM inbound_detail d
         JOIN inbound i ON i.id_inbound = d.id_inbound
         JOIN barang b ON b.id_barang = d.id_barang
         LEFT JOIN supplier s ON s.id_supplier = i.id_supplier
         WHERE i.jenis = 'pembelian' AND i.tanggal BETWEEN ? AND ?
         ORDER BY i.tanggal, i.no_transaksi, d.id_inbound_detail",
        [$dari, $sampai]
    );
    $total = 0;
    foreach ($rs as $r) {
        $line = [tgl($r['tanggal']), $r['no_transaksi'], (string)$r['nama_supplier'], $r['nama_barang'], nf($r['jumlah']), $r['satuan']];
        if ($admin) {
            $sub = (float)$r['jumlah'] * (float)$r['harga_beli'];
            $total += $sub;
            $line[] = number_format((float)$r['harga_beli'], 0, ',', '.');
            $line[] = number_format($sub, 0, ',', '.');
        }
        $line[] = $r['tgl_kedaluwarsa'] ? tgl($r['tgl_kedaluwarsa']) : '-';
        $data[] = $line;
    }
    if ($admin) {
        $foot = array_fill(0, count($cols), '');
        $foot[count($cols) - 3] = 'Total pembelian';
        $foot[count($cols) - 2] = number_format($total, 0, ',', '.');
    }
} elseif ($jenis === 'keluar') {
    $cols = ['Tanggal', 'No. transaksi', 'Tujuan', 'Barang', 'Jumlah', 'Satuan', 'Batch asal', 'Kedaluwarsa batch'];
    $rs = rows(
        'SELECT o.tanggal, o.no_transaksi, o.tujuan, b.nama_barang, b.satuan, od.jumlah, i.no_transaksi AS no_batch, d.tgl_kedaluwarsa
         FROM outbound_detail od
         JOIN outbound o ON o.id_outbound = od.id_outbound
         JOIN barang b ON b.id_barang = od.id_barang
         JOIN inbound_detail d ON d.id_inbound_detail = od.id_inbound_detail
         JOIN inbound i ON i.id_inbound = d.id_inbound
         WHERE o.tanggal BETWEEN ? AND ?
         ORDER BY o.tanggal, o.no_transaksi, od.id_outbound_detail',
        [$dari, $sampai]
    );
    foreach ($rs as $r) {
        $data[] = [tgl($r['tanggal']), $r['no_transaksi'], isset($tujuanList[$r['tujuan']]) ? $tujuanList[$r['tujuan']] : $r['tujuan'], $r['nama_barang'], nf($r['jumlah']), $r['satuan'], $r['no_batch'], $r['tgl_kedaluwarsa'] ? tgl($r['tgl_kedaluwarsa']) : '-'];
    }
} elseif ($jenis === 'kedaluwarsa') {
    $cols = ['Barang', 'No. batch', 'Tgl. terima', 'Sisa', 'Satuan', 'Kedaluwarsa', 'Status'];
    $rs = rows(
        'SELECT b.nama_barang, b.satuan, i.no_transaksi, i.tanggal, d.sisa_jumlah, d.tgl_kedaluwarsa
         FROM inbound_detail d JOIN barang b ON b.id_barang = d.id_barang JOIN inbound i ON i.id_inbound = d.id_inbound
         WHERE d.sisa_jumlah > 0 AND d.tgl_kedaluwarsa IS NOT NULL AND d.tgl_kedaluwarsa <= ?
         ORDER BY d.tgl_kedaluwarsa, b.nama_barang',
        [date('Y-m-d', strtotime('+' . EXPIRY_DAYS . ' days'))]
    );
    foreach ($rs as $r) {
        $st = status_exp($r['tgl_kedaluwarsa']);
        $data[] = [$r['nama_barang'], $r['no_transaksi'], tgl($r['tanggal']), nf($r['sisa_jumlah']), $r['satuan'], tgl($r['tgl_kedaluwarsa']), $st[0]];
    }
    $title .= ' (sudah lewat atau <= ' . EXPIRY_DAYS . ' hari)';
} elseif ($jenis === 'opname') {
    $cols = ['Tanggal', 'No. opname', 'Status', 'Barang', 'Stok sistem', 'Stok fisik', 'Selisih', 'Alasan'];
    $rs = rows(
        'SELECT o.tanggal, o.no_transaksi, o.status, b.nama_barang, b.satuan, d.stok_sistem, d.stok_fisik, d.selisih, d.alasan
         FROM stock_opname_detail d JOIN stock_opname o ON o.id_opname = d.id_opname JOIN barang b ON b.id_barang = d.id_barang
         WHERE o.tanggal BETWEEN ? AND ? ORDER BY o.tanggal, o.no_transaksi, b.nama_barang',
        [$dari, $sampai]
    );
    foreach ($rs as $r) {
        $s = (float)$r['selisih'];
        $data[] = [tgl($r['tanggal']), $r['no_transaksi'], ucfirst($r['status']), $r['nama_barang'], nf($r['stok_sistem']) . ' ' . $r['satuan'], nf($r['stok_fisik']) . ' ' . $r['satuan'], ($s > 0 ? '+' : '') . nf($s), (string)$r['alasan']];
    }
} elseif ($jenis === 'pembelian') {
    $cols = ['Barang', 'Satuan', 'Total masuk', 'Nilai pembelian (Rp)', 'Total keluar', 'Nilai pemakaian (Rp)'];
    $in = [];
    foreach (rows(
        "SELECT d.id_barang, SUM(d.jumlah) AS q, SUM(d.jumlah * d.harga_beli) AS v FROM inbound_detail d JOIN inbound i ON i.id_inbound = d.id_inbound
         WHERE i.jenis = 'pembelian' AND i.tanggal BETWEEN ? AND ? GROUP BY d.id_barang",
        [$dari, $sampai]
    ) as $r) {
        $in[(int)$r['id_barang']] = $r;
    }
    $out = [];
    foreach (rows(
        'SELECT od.id_barang, SUM(od.jumlah) AS q, SUM(od.jumlah * d.harga_beli) AS v
         FROM outbound_detail od JOIN outbound o ON o.id_outbound = od.id_outbound JOIN inbound_detail d ON d.id_inbound_detail = od.id_inbound_detail
         WHERE o.tanggal BETWEEN ? AND ? GROUP BY od.id_barang',
        [$dari, $sampai]
    ) as $r) {
        $out[(int)$r['id_barang']] = $r;
    }
    $tin = 0;
    $tout = 0;
    foreach (rows('SELECT id_barang, nama_barang, satuan FROM barang ORDER BY nama_barang') as $b) {
        $id = (int)$b['id_barang'];
        if (!isset($in[$id]) && !isset($out[$id])) {
            continue;
        }
        $vin = isset($in[$id]) ? (float)$in[$id]['v'] : 0;
        $vout = isset($out[$id]) ? (float)$out[$id]['v'] : 0;
        $tin += $vin;
        $tout += $vout;
        $data[] = [$b['nama_barang'], $b['satuan'], isset($in[$id]) ? nf($in[$id]['q']) : '0', number_format($vin, 0, ',', '.'), isset($out[$id]) ? nf($out[$id]['q']) : '0', number_format($vout, 0, ',', '.')];
    }
    $foot = ['Total', '', '', number_format($tin, 0, ',', '.'), '', number_format($tout, 0, ',', '.')];
}

// ---------- ekspor CSV (dapat dibuka di Excel) ----------
if (g('export') === 'csv') {
    $fn = 'laporan-' . $jenis . '-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [$title . ($usePeriod ? ' (' . tgl($dari) . ' - ' . tgl($sampai) . ')' : '')], ';', '"', '\\');
    fputcsv($out, $cols, ';', '"', '\\');
    foreach ($data as $line) {
        fputcsv($out, $line, ';', '"', '\\');
    }
    if ($foot) {
        fputcsv($out, $foot, ';', '"', '\\');
    }
    fclose($out);
    exit;
}

$exportQs = http_build_query(['jenis' => $jenis, 'dari' => $dari, 'sampai' => $sampai, 'export' => 'csv']);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head no-print">
    <div><h1>Laporan</h1><div class="sub">Tampilkan, cetak (simpan sebagai PDF lewat dialog cetak), atau ekspor ke Excel (CSV)</div></div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-success btn-sm" href="laporan.php?<?= e($exportQs) ?>"><i class="bi bi-file-earmark-excel"></i> Ekspor Excel (CSV)</a>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-file-earmark-pdf"></i> Cetak / PDF</button>
    </div>
</div>

<div class="card mb-3 no-print"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Jenis laporan</label>
            <select name="jenis" class="form-select"><?php foreach ($types as $k => $v): ?><option value="<?= e($k) ?>" <?= $jenis === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Dari tanggal</label><input type="date" name="dari" class="form-control" value="<?= e($dari) ?>" <?= $usePeriod ? '' : 'disabled' ?>></div>
        <div class="col-md-3"><label class="form-label">Sampai tanggal</label><input type="date" name="sampai" class="form-control" value="<?= e($sampai) ?>" <?= $usePeriod ? '' : 'disabled' ?>></div>
        <div class="col-md-2"><button class="btn btn-navy w-100">Tampilkan</button></div>
    </form>
</div></div>

<div class="card">
    <div class="card-header">
        <div><?= e($title) ?></div>
        <div class="hint fw-normal"><?= e(APP_NAME) ?> &middot; dicetak <?= e(tglwaktu(date('Y-m-d H:i:s'))) ?><?= $usePeriod ? ' &middot; periode ' . e(tgl($dari)) . ' s.d. ' . e(tgl($sampai)) : '' ?></div>
    </div>
    <div class="table-responsive"><table class="table table-sm table-striped mb-0">
        <thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($data as $line): ?>
            <tr><?php foreach ($line as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="<?= count($cols) ?>" class="text-center text-muted py-4">Tidak ada data untuk laporan ini.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($foot && $data): ?><tfoot><tr><?php foreach ($foot as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></tfoot><?php endif; ?>
    </table></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
