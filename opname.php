<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/stock.php';
require_login();
$page_title = 'Stock Opname';
$active = 'opname';
$me = user();
$today = date('Y-m-d');

$errors = [];
$showForm = g('new') !== '';
$f = ['tanggal' => $today, 'catatan' => ''];
$fisikIn = [];
$alasanIn = [];

// ---------- simpan hasil hitung (Petugas / Admin) ----------
if (is_post() && p('action') === 'save') {
    csrf_check();
    $showForm = true;
    $f = ['tanggal' => p('tanggal'), 'catatan' => p('catatan')];
    $fisikIn = p_arr('fisik');
    $alasanIn = p_arr('alasan');

    if (!valid_date($f['tanggal']) || $f['tanggal'] > $today) {
        $errors[] = 'Tanggal opname tidak valid.';
    }
    if (strlen($f['catatan']) > 255) {
        $errors[] = 'Catatan maksimal 255 karakter.';
    }
    $counted = [];
    foreach ($fisikIn as $idb => $v) {
        $idb = (int)$idb;
        $v = is_scalar($v) ? trim((string)$v) : '';
        if ($idb <= 0 || $v === '') {
            continue;
        }
        if (!is_numeric($v) || (float)$v < 0) {
            $errors[] = 'Stok fisik harus berupa angka tidak negatif.';
            break;
        }
        $a = (isset($alasanIn[$idb]) && is_scalar($alasanIn[$idb])) ? trim((string)$alasanIn[$idb]) : '';
        $counted[$idb] = ['fisik' => round((float)$v, 2), 'alasan' => $a];
    }
    if (!$counted && !$errors) {
        $errors[] = 'Isi stok fisik untuk minimal satu barang.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $noTrx = next_no('OPN', 'stock_opname', $f['tanggal']);
            q('INSERT INTO stock_opname (no_transaksi, tanggal, status, catatan, id_pengguna, dibuat_pada) VALUES (?,?,?,?,?,?)',
              [$noTrx, $f['tanggal'], 'menunggu', $f['catatan'], $me['id'], date('Y-m-d H:i:s')]);
            $idOp = (int)$pdo->lastInsertId();
            $adaSelisih = 0;
            ksort($counted);
            foreach ($counted as $idb => $c) {
                $b = row('SELECT nama_barang, stok_saat_ini FROM barang WHERE id_barang = ? AND aktif = 1 FOR UPDATE', [$idb]);
                if ($b === null) {
                    throw new UserError('Ada barang yang tidak valid.');
                }
                $sis = round((float)$b['stok_saat_ini'], 2);
                $sel = round($c['fisik'] - $sis, 2);
                if (abs($sel) > 0.00001) {
                    $adaSelisih++;
                    if ($c['alasan'] === '') {
                        throw new UserError('Alasan wajib diisi untuk selisih pada ' . $b['nama_barang'] . ' (aturan BR-08).');
                    }
                }
                q('INSERT INTO stock_opname_detail (id_opname, id_barang, stok_sistem, stok_fisik, selisih, alasan) VALUES (?,?,?,?,?,?)',
                  [$idOp, $idb, $sis, $c['fisik'], $sel, cut_str($c['alasan'], 255)]);
            }
            $pdo->commit();
            flash('success', 'Stock opname ' . $noTrx . ' tersimpan' . ($adaSelisih ? ' dengan ' . $adaSelisih . ' selisih; menunggu persetujuan Admin/Owner.' : '. Tidak ada selisih; menunggu konfirmasi Admin/Owner.'));
            redirect('opname.php?view=' . $idOp);
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

// ---------- persetujuan / penolakan (Admin) ----------
if (is_post() && (p('action') === 'approve' || p('action') === 'reject')) {
    csrf_check();
    require_admin();
    $id = (int)p('id');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $op = row('SELECT * FROM stock_opname WHERE id_opname = ? FOR UPDATE', [$id]);
        if ($op === null || $op['status'] !== 'menunggu') {
            throw new UserError('Stock opname tidak ditemukan atau sudah diproses.');
        }
        $uid = (int)$me['id'];
        if (p('action') === 'reject') {
            q("UPDATE stock_opname SET status = 'ditolak', id_penyetuju = ?, tgl_persetujuan = ?, catatan_penyetuju = ? WHERE id_opname = ?",
              [$uid, date('Y-m-d H:i:s'), cut_str(p('catatan_penyetuju'), 255), $id]);
            $pdo->commit();
            flash('info', 'Stock opname ' . $op['no_transaksi'] . ' ditolak. Stok tidak diubah.');
            redirect('opname.php?view=' . $id);
        }
        $dets = rows(
            'SELECT d.*, b.nama_barang, b.harga_beli_terakhir FROM stock_opname_detail d JOIN barang b ON b.id_barang = d.id_barang WHERE d.id_opname = ? ORDER BY d.id_barang',
            [$id]
        );
        $idAdj = 0;
        $n = 0;
        foreach ($dets as $d) {
            $sel = (float)$d['selisih'];
            if (abs($sel) < 0.00001) {
                continue;
            }
            $idb = (int)$d['id_barang'];
            $cur = (float)val('SELECT stok_saat_ini FROM barang WHERE id_barang = ? FOR UPDATE', [$idb]);
            if ($sel < 0) {
                $qty = abs($sel);
                if ($qty > $cur + 0.00001) {
                    throw new UserError('Adjustment ' . $d['nama_barang'] . ' (' . nf($qty) . ') melebihi stok saat ini (' . nf($cur) . '). Tolak opname ini lalu hitung ulang.');
                }
                fefo_take($idb, $qty);
                ubah_stok($idb, -$qty);
            } else {
                if ($idAdj === 0) {
                    $noAdj = next_no('ADJ', 'inbound', $op['tanggal']);
                    q('INSERT INTO inbound (no_transaksi, tanggal, id_supplier, jenis, foto_nota, catatan, id_pengguna, dibuat_pada) VALUES (?,?,?,?,?,?,?,?)',
                      [$noAdj, $op['tanggal'], null, 'adjustment', null, 'Adjustment stock opname ' . $op['no_transaksi'], $uid, date('Y-m-d H:i:s')]);
                    $idAdj = (int)$pdo->lastInsertId();
                }
                q('INSERT INTO inbound_detail (id_inbound, id_barang, jumlah, sisa_jumlah, harga_beli, tgl_kedaluwarsa) VALUES (?,?,?,?,?,?)',
                  [$idAdj, $idb, $sel, $sel, (float)$d['harga_beli_terakhir'], null]);
                ubah_stok($idb, $sel);
            }
            kartu($idb, 'adjustment', $op['no_transaksi'], $sel, 'Adjustment stock opname: ' . $d['alasan'], $uid);
            $n++;
        }
        q("UPDATE stock_opname SET status = 'disetujui', id_penyetuju = ?, tgl_persetujuan = ?, catatan_penyetuju = ? WHERE id_opname = ?",
          [$uid, date('Y-m-d H:i:s'), cut_str(p('catatan_penyetuju'), 255), $id]);
        $pdo->commit();
        flash('success', 'Stock opname ' . $op['no_transaksi'] . ' disetujui. ' . $n . ' adjustment diterapkan ke stok dan kartu stok.');
        redirect('opname.php?view=' . $id);
    } catch (UserError $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', $ex->getMessage());
        redirect('opname.php?view=' . $id);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', 'Proses gagal' . (DEBUG ? ': ' . $ex->getMessage() : '. Tidak ada data yang berubah.'));
        redirect('opname.php?view=' . $id);
    }
}

$barangs = $showForm ? rows('SELECT id_barang, kode_barang, nama_barang, satuan, stok_saat_ini FROM barang WHERE aktif = 1 ORDER BY nama_barang') : [];

$view = null;
$viewLines = [];
if (!$showForm && g('view') !== '') {
    $view = row(
        'SELECT o.*, u.nama_lengkap AS petugas, a.nama_lengkap AS penyetuju FROM stock_opname o
         LEFT JOIN pengguna u ON u.id_pengguna = o.id_pengguna
         LEFT JOIN pengguna a ON a.id_pengguna = o.id_penyetuju WHERE o.id_opname = ?',
        [(int)g('view')]
    );
    if ($view === null) {
        flash('danger', 'Data tidak ditemukan.');
        redirect('opname.php');
    }
    $viewLines = rows(
        'SELECT d.*, b.kode_barang, b.nama_barang, b.satuan FROM stock_opname_detail d JOIN barang b ON b.id_barang = d.id_barang WHERE d.id_opname = ? ORDER BY b.nama_barang',
        [$view['id_opname']]
    );
}

$list = [];
if (!$showForm && $view === null) {
    $list = rows(
        'SELECT o.*, u.nama_lengkap AS petugas, a.nama_lengkap AS penyetuju,
            (SELECT COUNT(*) FROM stock_opname_detail d WHERE d.id_opname = o.id_opname) AS jml_item,
            (SELECT COUNT(*) FROM stock_opname_detail d WHERE d.id_opname = o.id_opname AND d.selisih <> 0) AS jml_selisih
         FROM stock_opname o
         LEFT JOIN pengguna u ON u.id_pengguna = o.id_pengguna
         LEFT JOIN pengguna a ON a.id_pengguna = o.id_penyetuju
         ORDER BY o.tanggal DESC, o.id_opname DESC LIMIT 200'
    );
}

function opname_badge(string $s): string
{
    $m = ['menunggu' => ['Menunggu persetujuan', 'warning'], 'disetujui' => ['Disetujui', 'success'], 'ditolak' => ['Ditolak', 'danger']];
    return badge(isset($m[$s]) ? $m[$s] : [$s, 'secondary']);
}

require __DIR__ . '/includes/header.php';
?>
<?php if ($showForm): ?>
<div class="page-head">
    <div><h1>Stock Opname Baru</h1><div class="sub">Masukkan hasil hitung fisik. Selisih dihitung otomatis; alasan wajib bila ada selisih. Adjustment baru berlaku setelah disetujui Admin/Owner.</div></div>
    <a href="opname.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">Tanggal opname *</label><input type="date" class="form-control" name="tanggal" max="<?= e($today) ?>" value="<?= e($f['tanggal']) ?>" required></div>
        <div class="col-md-9"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="255" value="<?= e($f['catatan']) ?>" placeholder="Mis. opname bulanan gudang kering"></div>
    </div></div>
    <div class="card mb-3">
        <div class="card-header">Hasil hitung fisik <span class="hint fw-normal">(isi hanya barang yang dihitung)</span></div>
        <div class="card-body pb-2"><input type="search" class="form-control" placeholder="Cari barang..." data-filter="#tbl"></div>
        <div class="table-responsive"><table class="table table-sm mb-0" id="tbl">
            <thead><tr><th>Barang</th><th class="text-end">Stok sistem</th><th style="width:170px">Stok fisik</th><th>Alasan selisih</th></tr></thead>
            <tbody>
            <?php foreach ($barangs as $b): $id = (int)$b['id_barang']; ?>
                <tr>
                    <td><strong><?= e($b['nama_barang']) ?></strong><div class="hint"><?= e($b['kode_barang']) ?></div></td>
                    <td class="text-end num"><?= nf($b['stok_saat_ini']) ?> <?= e($b['satuan']) ?></td>
                    <td><div class="input-group input-group-sm"><input type="number" step="0.01" min="0" class="form-control" name="fisik[<?= $id ?>]" value="<?= e(isset($fisikIn[$id]) && is_scalar($fisikIn[$id]) ? $fisikIn[$id] : '') ?>"><span class="input-group-text"><?= e($b['satuan']) ?></span></div></td>
                    <td><input class="form-control form-control-sm" name="alasan[<?= $id ?>]" maxlength="255" placeholder="Mis. rusak, tercecer, salah catat" value="<?= e(isset($alasanIn[$id]) && is_scalar($alasanIn[$id]) ? $alasanIn[$id] : '') ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <button class="btn btn-gold px-4" type="submit"><i class="bi bi-check2-circle"></i> Simpan hasil opname</button>
    <a href="opname.php" class="btn btn-outline-secondary">Batal</a>
</form>

<?php elseif ($view !== null): ?>
<div class="page-head">
    <div><h1><?= e($view['no_transaksi']) ?></h1><div class="sub">Detail stock opname <?= opname_badge($view['status']) ?></div></div>
    <div class="d-flex gap-2 no-print"><button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button><a href="opname.php" class="btn btn-outline-secondary btn-sm">Kembali</a></div>
</div>
<div class="card mb-3"><div class="card-body row g-3">
    <div class="col-md-3"><div class="hint">Tanggal opname</div><strong><?= e(tgl($view['tanggal'])) ?></strong></div>
    <div class="col-md-3"><div class="hint">Petugas penghitung</div><strong><?= e($view['petugas']) ?></strong></div>
    <div class="col-md-3"><div class="hint">Disetujui / ditolak oleh</div><strong><?= e($view['penyetuju'] ?: '-') ?></strong></div>
    <div class="col-md-3"><div class="hint">Waktu keputusan</div><strong><?= e(tglwaktu($view['tgl_persetujuan'])) ?></strong></div>
    <?php if ($view['catatan']): ?><div class="col-12"><div class="hint">Catatan</div><?= e($view['catatan']) ?></div><?php endif; ?>
    <?php if ($view['catatan_penyetuju']): ?><div class="col-12"><div class="hint">Catatan keputusan</div><?= e($view['catatan_penyetuju']) ?></div><?php endif; ?>
</div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Barang</th><th class="text-end">Stok sistem</th><th class="text-end">Stok fisik</th><th class="text-end">Selisih</th><th>Alasan</th></tr></thead>
    <tbody>
    <?php foreach ($viewLines as $l): $s = (float)$l['selisih']; ?>
        <tr>
            <td><strong><?= e($l['nama_barang']) ?></strong><div class="hint"><?= e($l['kode_barang']) ?></div></td>
            <td class="text-end num"><?= nf($l['stok_sistem']) ?> <?= e($l['satuan']) ?></td>
            <td class="text-end num"><?= nf($l['stok_fisik']) ?> <?= e($l['satuan']) ?></td>
            <td class="text-end num fw-bold <?= $s < 0 ? 'text-danger' : ($s > 0 ? 'text-success' : 'text-muted') ?>"><?= ($s > 0 ? '+' : '') . nf($s) ?></td>
            <td><?= e($l['alasan']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php if (is_admin() && $view['status'] === 'menunggu'): ?>
<div class="card no-print"><div class="card-body">
    <form method="post" class="row g-2 align-items-end">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$view['id_opname'] ?>">
        <div class="col-md-6"><label class="form-label">Catatan keputusan (opsional)</label><input class="form-control" name="catatan_penyetuju" maxlength="255"></div>
        <div class="col-md-6 d-flex gap-2">
            <button name="action" value="approve" class="btn btn-gold" onclick="return confirm('Setujui dan terapkan adjustment ke stok?')"><i class="bi bi-check2-circle"></i> Setujui &amp; terapkan</button>
            <button name="action" value="reject" class="btn btn-outline-danger" onclick="return confirm('Tolak stock opname ini?')"><i class="bi bi-x-circle"></i> Tolak</button>
        </div>
    </form>
    <div class="hint mt-2">Adjustment negatif mengurangi batch dengan urutan FEFO; adjustment positif dicatat sebagai batch penyesuaian tanpa tanggal kedaluwarsa.</div>
</div></div>
<?php elseif ($view['status'] === 'menunggu'): ?>
<div class="alert alert-info">Menunggu persetujuan Admin/Owner. Stok belum diubah.</div>
<?php endif; ?>

<?php else: ?>
<div class="page-head">
    <div><h1>Stock Opname</h1><div class="sub">Pencocokan stok sistem dengan hasil hitung fisik</div></div>
    <a href="opname.php?new=1" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Opname Baru</a>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>No. transaksi</th><th>Tanggal</th><th class="text-end">Item</th><th class="text-end">Selisih</th><th>Status</th><th>Petugas</th><th>Penyetuju</th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
        <tr>
            <td><a href="opname.php?view=<?= (int)$r['id_opname'] ?>"><strong><?= e($r['no_transaksi']) ?></strong></a></td>
            <td><?= e(tgl($r['tanggal'])) ?></td>
            <td class="text-end"><?= (int)$r['jml_item'] ?></td>
            <td class="text-end"><?= (int)$r['jml_selisih'] ?></td>
            <td><?= opname_badge($r['status']) ?></td>
            <td><?= e($r['petugas']) ?></td>
            <td><?= e($r['penyetuju'] ?: '-') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada stock opname.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
