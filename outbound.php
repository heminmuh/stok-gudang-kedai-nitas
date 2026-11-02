<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/stock.php';
require_login();
$page_title = 'Barang Keluar (Outbound)';
$active = 'outbound';
$me = user();
$today = date('Y-m-d');
$tujuanList = tujuan_list();

function arr_s(array $a, $i): string
{
    return (isset($a[$i]) && is_scalar($a[$i])) ? trim((string)$a[$i]) : '';
}

$barangs = rows(
    'SELECT b.id_barang, b.kode_barang, b.nama_barang, b.satuan, b.stok_saat_ini,
        (SELECT MIN(d.tgl_kedaluwarsa) FROM inbound_detail d WHERE d.id_barang = b.id_barang AND d.sisa_jumlah > 0) AS exp_terdekat
     FROM barang b WHERE b.aktif = 1 AND b.stok_saat_ini > 0 ORDER BY b.nama_barang'
);

function outbound_row(array $barangs, $sel = 0, $jml = ''): string
{
    $h = '<tr><td style="min-width:260px"><select name="barang[]" class="form-select form-select-sm barang-select" required><option value="">- pilih barang -</option>';
    foreach ($barangs as $b) {
        $h .= '<option value="' . (int)$b['id_barang'] . '" data-satuan="' . e($b['satuan']) . '" data-stok="' . e(nf($b['stok_saat_ini'])) . '" data-exp="'
            . e($b['exp_terdekat'] ? tgl($b['exp_terdekat']) : '') . '"' . ((int)$sel === (int)$b['id_barang'] ? ' selected' : '') . '>'
            . e($b['kode_barang'] . ' - ' . $b['nama_barang']) . '</option>';
    }
    $h .= '</select><div class="hint row-info mt-1"></div></td>'
        . '<td style="width:170px"><div class="input-group input-group-sm"><input type="number" step="0.01" min="0.01" name="jumlah[]" class="form-control" value="' . e($jml) . '" required><span class="input-group-text row-unit" style="min-width:44px"></span></div></td>'
        . '<td style="width:44px"><button type="button" class="btn btn-sm btn-outline-danger rm-line" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td></tr>';
    return $h;
}

$errors = [];
$showForm = g('new') !== '';
$f = ['tanggal' => $today, 'tujuan' => 'produksi_dapur', 'catatan' => ''];
$lines = [];

if (is_post() && p('action') === 'save') {
    csrf_check();
    $showForm = true;
    $f = ['tanggal' => p('tanggal'), 'tujuan' => p('tujuan'), 'catatan' => p('catatan')];
    $ids = p_arr('barang');
    $jml = p_arr('jumlah');
    $valid = [];
    foreach ($barangs as $b) {
        $valid[(int)$b['id_barang']] = true;
    }

    if (!valid_date($f['tanggal']) || $f['tanggal'] > $today) {
        $errors[] = 'Tanggal keluar tidak valid (tidak boleh di masa depan).';
    }
    if (!isset($tujuanList[$f['tujuan']])) {
        $errors[] = 'Pilih tujuan alokasi.';
    }
    if (strlen($f['catatan']) > 255) {
        $errors[] = 'Catatan maksimal 255 karakter.';
    }
    $sum = [];
    foreach ($ids as $i => $idb) {
        $idb = (int)(is_scalar($idb) ? $idb : 0);
        if ($idb <= 0) {
            continue;
        }
        $j = arr_s($jml, $i);
        $lines[] = ['id' => $idb, 'jumlah' => $j];
        if (!isset($valid[$idb])) {
            $errors[] = 'Ada barang yang tidak valid atau stoknya kosong.';
            continue;
        }
        if (!is_numeric($j) || (float)$j <= 0) {
            $errors[] = 'Jumlah tiap barang harus lebih dari 0.';
            continue;
        }
        $sum[$idb] = (isset($sum[$idb]) ? $sum[$idb] : 0) + round((float)$j, 2);
    }
    if (!$lines) {
        $errors[] = 'Tambahkan minimal satu barang.';
    }
    $errors = array_values(array_unique($errors));

    if (!$errors) {
        ksort($sum);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $noTrx = next_no('OUT', 'outbound', $f['tanggal']);
            q('INSERT INTO outbound (no_transaksi, tanggal, tujuan, catatan, id_pengguna, dibuat_pada) VALUES (?,?,?,?,?,?)',
              [$noTrx, $f['tanggal'], $f['tujuan'], $f['catatan'], $me['id'], date('Y-m-d H:i:s')]);
            $idOut = (int)$pdo->lastInsertId();
            $warnExp = [];
            $lowIds = [];
            foreach ($sum as $idb => $qty) {
                $b = row('SELECT nama_barang, satuan, stok_saat_ini, stok_minimum FROM barang WHERE id_barang = ? AND aktif = 1 FOR UPDATE', [$idb]);
                if ($b === null) {
                    throw new UserError('Barang tidak ditemukan.');
                }
                if ($qty > (float)$b['stok_saat_ini'] + 0.00001) {
                    throw new UserError('Stok ' . $b['nama_barang'] . ' tidak cukup: tersedia ' . nf($b['stok_saat_ini']) . ' ' . $b['satuan'] . ', diminta ' . nf($qty) . ' ' . $b['satuan'] . '. Transaksi ditolak.');
                }
                $alloc = fefo_take($idb, $qty);
                foreach ($alloc as $a) {
                    q('INSERT INTO outbound_detail (id_outbound, id_barang, id_inbound_detail, jumlah) VALUES (?,?,?,?)', [$idOut, $idb, $a['id'], $a['jumlah']]);
                    if ($a['exp'] !== null && $a['exp'] < $f['tanggal'] && $f['tujuan'] !== 'rusak_kedaluwarsa') {
                        $warnExp[$b['nama_barang']] = true;
                    }
                }
                ubah_stok($idb, -$qty);
                kartu($idb, 'keluar', $noTrx, -$qty, 'Barang keluar - ' . $tujuanList[$f['tujuan']], (int)$me['id']);
                $after = (float)$b['stok_saat_ini'] - $qty;
                if ($after <= (float)$b['stok_minimum'] + 0.00001) {
                    $lowIds[] = $b['nama_barang'] . ' (sisa ' . nf($after) . ' ' . $b['satuan'] . ')';
                }
            }
            $pdo->commit();
            flash('success', 'Barang keluar ' . $noTrx . ' tersimpan (pengeluaran mengikuti FEFO).');
            if ($lowIds) {
                flash('warning', 'Low stock alert: ' . implode(', ', $lowIds) . ' sudah mencapai batas minimum. Pertimbangkan restock.');
            }
            if ($warnExp) {
                flash('warning', 'Perhatian: transaksi mengambil batch yang sudah kedaluwarsa untuk ' . implode(', ', array_keys($warnExp)) . '.');
            }
            redirect('outbound.php?view=' . $idOut);
        } catch (UserError $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Transaksi gagal disimpan' . (DEBUG ? ': ' . $ex->getMessage() : '. Tidak ada data yang berubah.');
        }
    }
}

$view = null;
$viewLines = [];
if (!$showForm && g('view') !== '') {
    $view = row(
        'SELECT o.*, u.nama_lengkap FROM outbound o LEFT JOIN pengguna u ON u.id_pengguna = o.id_pengguna WHERE o.id_outbound = ?',
        [(int)g('view')]
    );
    if ($view === null) {
        flash('danger', 'Transaksi tidak ditemukan.');
        redirect('outbound.php');
    }
    $viewLines = rows(
        'SELECT od.jumlah, b.kode_barang, b.nama_barang, b.satuan, d.tgl_kedaluwarsa, i.no_transaksi AS no_batch
         FROM outbound_detail od
         JOIN barang b ON b.id_barang = od.id_barang
         JOIN inbound_detail d ON d.id_inbound_detail = od.id_inbound_detail
         JOIN inbound i ON i.id_inbound = d.id_inbound
         WHERE od.id_outbound = ? ORDER BY od.id_outbound_detail',
        [$view['id_outbound']]
    );
}

$list = [];
if (!$showForm && $view === null) {
    $list = rows(
        'SELECT o.*, u.nama_lengkap,
            (SELECT COUNT(DISTINCT od.id_barang) FROM outbound_detail od WHERE od.id_outbound = o.id_outbound) AS jml_item
         FROM outbound o LEFT JOIN pengguna u ON u.id_pengguna = o.id_pengguna
         ORDER BY o.tanggal DESC, o.id_outbound DESC LIMIT 300'
    );
}

require __DIR__ . '/includes/header.php';
?>
<?php if ($showForm): ?>
<div class="page-head">
    <div><h1>Catat Barang Keluar</h1><div class="sub">Sistem memilih batch dengan kedaluwarsa terdekat (FEFO) dan menolak jumlah melebihi stok.</div></div>
    <a href="outbound.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<?php if (!$barangs): ?><div class="alert alert-warning">Tidak ada barang dengan stok tersedia. Catat barang masuk terlebih dahulu.</div><?php endif; ?>
<form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="card mb-3">
        <div class="card-header">Data pengeluaran</div>
        <div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label">Tanggal keluar *</label><input type="date" class="form-control" name="tanggal" max="<?= e($today) ?>" value="<?= e($f['tanggal']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Tujuan alokasi *</label>
                <select class="form-select" name="tujuan" required><?php foreach ($tujuanList as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['tujuan'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-5"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="255" value="<?= e($f['catatan']) ?>" placeholder="Opsional"></div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Rincian barang</span><button type="button" id="add-line" class="btn btn-sm btn-navy"><i class="bi bi-plus-lg"></i> Tambah baris</button></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Barang</th><th>Jumlah keluar</th><th></th></tr></thead>
            <tbody id="lines"><?php foreach ($lines as $l) { echo outbound_row($barangs, $l['id'], $l['jumlah']); } ?></tbody>
        </table></div>
    </div>
    <template id="line-tpl"><?= outbound_row($barangs) ?></template>
    <button class="btn btn-gold px-4" type="submit"><i class="bi bi-check2-circle"></i> Simpan transaksi</button>
    <a href="outbound.php" class="btn btn-outline-secondary">Batal</a>
</form>

<?php elseif ($view !== null): ?>
<div class="page-head">
    <div><h1><?= e($view['no_transaksi']) ?></h1><div class="sub">Detail barang keluar &middot; transaksi tersimpan tidak dapat diubah (koreksi melalui stock opname)</div></div>
    <div class="d-flex gap-2 no-print"><button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button><a href="outbound.php" class="btn btn-outline-secondary btn-sm">Kembali</a></div>
</div>
<div class="card mb-3"><div class="card-body row g-3">
    <div class="col-md-3"><div class="hint">Tanggal keluar</div><strong><?= e(tgl($view['tanggal'])) ?></strong></div>
    <div class="col-md-3"><div class="hint">Tujuan</div><strong><?= e(isset($tujuanList[$view['tujuan']]) ? $tujuanList[$view['tujuan']] : $view['tujuan']) ?></strong></div>
    <div class="col-md-3"><div class="hint">Dicatat oleh</div><strong><?= e($view['nama_lengkap']) ?></strong></div>
    <div class="col-md-3"><div class="hint">Waktu input</div><strong><?= e(tglwaktu($view['dibuat_pada'])) ?></strong></div>
    <?php if ($view['catatan']): ?><div class="col-12"><div class="hint">Catatan</div><?= e($view['catatan']) ?></div><?php endif; ?>
</div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Barang</th><th class="text-end">Jumlah</th><th>Batch asal (FEFO)</th><th>Kedaluwarsa batch</th></tr></thead>
    <tbody>
    <?php foreach ($viewLines as $l): ?>
        <tr>
            <td><strong><?= e($l['nama_barang']) ?></strong><div class="hint"><?= e($l['kode_barang']) ?></div></td>
            <td class="text-end num"><?= nf($l['jumlah']) ?> <?= e($l['satuan']) ?></td>
            <td><?= e($l['no_batch']) ?></td>
            <td><?= e(tgl($l['tgl_kedaluwarsa'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>

<?php else: ?>
<div class="page-head">
    <div><h1>Barang Keluar</h1><div class="sub">Riwayat pengeluaran barang untuk produksi dan lainnya</div></div>
    <a href="outbound.php?new=1" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Catat Barang Keluar</a>
</div>
<div class="card">
    <div class="card-body pb-2"><input type="search" class="form-control" placeholder="Cari nomor, tujuan, petugas..." data-filter="#tbl"></div>
    <div class="table-responsive"><table class="table table-hover mb-0" id="tbl">
        <thead><tr><th>No. transaksi</th><th>Tanggal</th><th>Tujuan</th><th class="text-end">Item</th><th>Petugas</th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): ?>
            <tr>
                <td><a href="outbound.php?view=<?= (int)$r['id_outbound'] ?>"><strong><?= e($r['no_transaksi']) ?></strong></a></td>
                <td><?= e(tgl($r['tanggal'])) ?></td>
                <td><?= e(isset($tujuanList[$r['tujuan']]) ? $tujuanList[$r['tujuan']] : $r['tujuan']) ?></td>
                <td class="text-end"><?= (int)$r['jml_item'] ?></td>
                <td><?= e($r['nama_lengkap']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada transaksi barang keluar.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
