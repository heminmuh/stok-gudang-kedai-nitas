<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/stock.php';
require_login();
$page_title = 'Barang Masuk (Inbound)';
$active = 'inbound';
$me = user();
$today = date('Y-m-d');

function arr_s(array $a, $i): string
{
    return (isset($a[$i]) && is_scalar($a[$i])) ? trim((string)$a[$i]) : '';
}

function save_nota(array $file, array &$errors)
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Unggah foto nota gagal (kode ' . (int)$file['error'] . ').';
        return null;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Ukuran foto nota maksimal 2 MB.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $ext = ($info && isset($map[$info[2]])) ? $map[$info[2]] : null;
    if ($ext === null) {
        $errors[] = 'Foto nota harus berformat JPG, PNG, atau WebP.';
        return null;
    }
    $name = 'nota_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $name)) {
        $errors[] = 'Foto nota tidak dapat disimpan. Periksa izin folder uploads.';
        return null;
    }
    return $name;
}

$barangs = rows('SELECT id_barang, kode_barang, nama_barang, satuan, harga_beli_terakhir FROM barang WHERE aktif = 1 ORDER BY nama_barang');
$sups = rows('SELECT id_supplier, nama_supplier FROM supplier WHERE aktif = 1 ORDER BY nama_supplier');

function inbound_row(array $barangs, $sel = 0, $jml = '', $hrg = '', $exp = ''): string
{
    $h = '<tr><td style="min-width:230px"><select name="barang[]" class="form-select form-select-sm barang-select" required><option value="">- pilih barang -</option>';
    foreach ($barangs as $b) {
        $h .= '<option value="' . (int)$b['id_barang'] . '" data-satuan="' . e($b['satuan']) . '" data-harga="' . e($b['harga_beli_terakhir']) . '"'
            . ((int)$sel === (int)$b['id_barang'] ? ' selected' : '') . '>' . e($b['kode_barang'] . ' - ' . $b['nama_barang']) . '</option>';
    }
    $h .= '</select></td>'
        . '<td style="width:150px"><div class="input-group input-group-sm"><input type="number" step="0.01" min="0.01" name="jumlah[]" class="form-control" value="' . e($jml) . '" required><span class="input-group-text row-unit" style="min-width:44px"></span></div></td>'
        . '<td style="width:150px"><input type="number" step="0.01" min="0" name="harga[]" class="form-control form-control-sm harga-input" value="' . e($hrg) . '" placeholder="0"></td>'
        . '<td style="width:170px"><input type="date" name="exp[]" class="form-control form-control-sm" value="' . e($exp) . '"></td>'
        . '<td style="width:44px"><button type="button" class="btn btn-sm btn-outline-danger rm-line" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td></tr>';
    return $h;
}

$errors = [];
$showForm = g('new') !== '';
$f = ['tanggal' => $today, 'id_supplier' => '', 'catatan' => ''];
$lines = [];

if (is_post() && p('action') === 'save') {
    csrf_check();
    $showForm = true;
    $f = ['tanggal' => p('tanggal'), 'id_supplier' => p('id_supplier'), 'catatan' => p('catatan')];
    $ids = p_arr('barang');
    $jml = p_arr('jumlah');
    $hrg = p_arr('harga');
    $exs = p_arr('exp');
    $valid = [];
    foreach ($barangs as $b) {
        $valid[(int)$b['id_barang']] = true;
    }

    if (!valid_date($f['tanggal']) || $f['tanggal'] > $today) {
        $errors[] = 'Tanggal terima tidak valid (tidak boleh di masa depan).';
    }
    $supRow = (int)$f['id_supplier'] > 0 ? row('SELECT id_supplier, nama_supplier FROM supplier WHERE id_supplier = ? AND aktif = 1', [(int)$f['id_supplier']]) : null;
    if ($supRow === null) {
        $errors[] = 'Pilih supplier.';
    }
    if (strlen($f['catatan']) > 255) {
        $errors[] = 'Catatan maksimal 255 karakter.';
    }

    foreach ($ids as $i => $idb) {
        $idb = (int)(is_scalar($idb) ? $idb : 0);
        if ($idb <= 0) {
            continue;
        }
        $j = arr_s($jml, $i);
        $h = arr_s($hrg, $i);
        $x = arr_s($exs, $i);
        $lines[] = ['id' => $idb, 'jumlah' => $j, 'harga' => $h, 'exp' => $x];
    }
    if (!$lines) {
        $errors[] = 'Tambahkan minimal satu barang.';
    }
    $clean = [];
    foreach ($lines as $n => $l) {
        $no = $n + 1;
        if (!isset($valid[$l['id']])) {
            $errors[] = "Baris $no: barang tidak valid.";
            continue;
        }
        if (!is_numeric($l['jumlah']) || (float)$l['jumlah'] <= 0) {
            $errors[] = "Baris $no: jumlah harus lebih dari 0.";
            continue;
        }
        if ($l['harga'] !== '' && (!is_numeric($l['harga']) || (float)$l['harga'] < 0)) {
            $errors[] = "Baris $no: harga tidak valid.";
            continue;
        }
        if ($l['exp'] !== '') {
            if (!valid_date($l['exp'])) {
                $errors[] = "Baris $no: tanggal kedaluwarsa tidak valid.";
                continue;
            }
            if (valid_date($f['tanggal']) && $l['exp'] < $f['tanggal']) {
                $errors[] = "Baris $no: tanggal kedaluwarsa tidak boleh sebelum tanggal terima.";
                continue;
            }
        }
        $clean[] = ['id' => $l['id'], 'jumlah' => round((float)$l['jumlah'], 2), 'harga' => round((float)($l['harga'] === '' ? 0 : $l['harga']), 2), 'exp' => $l['exp'] === '' ? null : $l['exp']];
    }

    if (!$errors) {
        $foto = save_nota(isset($_FILES['foto_nota']) ? $_FILES['foto_nota'] : [], $errors);
    } else {
        $foto = null;
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $noTrx = next_no('IN', 'inbound', $f['tanggal']);
            q('INSERT INTO inbound (no_transaksi, tanggal, id_supplier, jenis, foto_nota, catatan, id_pengguna, dibuat_pada) VALUES (?,?,?,?,?,?,?,?)',
              [$noTrx, $f['tanggal'], (int)$supRow['id_supplier'], 'pembelian', $foto, $f['catatan'], $me['id'], date('Y-m-d H:i:s')]);
            $idIn = (int)$pdo->lastInsertId();
            foreach ($clean as $l) {
                q('INSERT INTO inbound_detail (id_inbound, id_barang, jumlah, sisa_jumlah, harga_beli, tgl_kedaluwarsa) VALUES (?,?,?,?,?,?)',
                  [$idIn, $l['id'], $l['jumlah'], $l['jumlah'], $l['harga'], $l['exp']]);
                ubah_stok($l['id'], $l['jumlah']);
                if ($l['harga'] > 0) {
                    q('UPDATE barang SET harga_beli_terakhir = ? WHERE id_barang = ?', [$l['harga'], $l['id']]);
                }
                kartu($l['id'], 'masuk', $noTrx, $l['jumlah'], 'Barang masuk dari ' . $supRow['nama_supplier'], (int)$me['id']);
            }
            $pdo->commit();
            flash('success', 'Barang masuk ' . $noTrx . ' tersimpan. Stok bertambah otomatis dan kartu stok tercatat.');
            redirect('inbound.php?view=' . $idIn);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($foto) {
                @unlink(__DIR__ . '/uploads/' . $foto);
            }
            $errors[] = 'Transaksi gagal disimpan' . (DEBUG ? ': ' . $ex->getMessage() : '. Tidak ada data yang berubah.');
        }
    }
}

// ---- tampilan detail ----
$view = null;
$viewLines = [];
if (!$showForm && g('view') !== '') {
    $view = row(
        'SELECT i.*, s.nama_supplier, u.nama_lengkap FROM inbound i
         LEFT JOIN supplier s ON s.id_supplier = i.id_supplier
         LEFT JOIN pengguna u ON u.id_pengguna = i.id_pengguna WHERE i.id_inbound = ?',
        [(int)g('view')]
    );
    if ($view === null) {
        flash('danger', 'Transaksi tidak ditemukan.');
        redirect('inbound.php');
    }
    $viewLines = rows(
        'SELECT d.*, b.kode_barang, b.nama_barang, b.satuan FROM inbound_detail d
         JOIN barang b ON b.id_barang = d.id_barang WHERE d.id_inbound = ? ORDER BY d.id_inbound_detail',
        [$view['id_inbound']]
    );
}

$list = [];
if (!$showForm && $view === null) {
    $list = rows(
        "SELECT i.*, s.nama_supplier, u.nama_lengkap,
            (SELECT COUNT(*) FROM inbound_detail d WHERE d.id_inbound = i.id_inbound) AS jml_item,
            (SELECT COALESCE(SUM(d.jumlah * d.harga_beli),0) FROM inbound_detail d WHERE d.id_inbound = i.id_inbound) AS total
         FROM inbound i
         LEFT JOIN supplier s ON s.id_supplier = i.id_supplier
         LEFT JOIN pengguna u ON u.id_pengguna = i.id_pengguna
         WHERE i.jenis = 'pembelian'
         ORDER BY i.tanggal DESC, i.id_inbound DESC LIMIT 300"
    );
}

require __DIR__ . '/includes/header.php';
?>
<?php if ($showForm): ?>
<div class="page-head">
    <div><h1>Catat Barang Masuk</h1><div class="sub">Nomor transaksi dibuat otomatis. Stok dan kartu stok diperbarui saat disimpan.</div></div>
    <a href="inbound.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="card mb-3">
        <div class="card-header">Data penerimaan</div>
        <div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label">Tanggal terima *</label><input type="date" class="form-control" name="tanggal" max="<?= e($today) ?>" value="<?= e($f['tanggal']) ?>" required></div>
            <div class="col-md-5"><label class="form-label">Supplier *</label>
                <select class="form-select" name="id_supplier" required><option value="">- pilih supplier -</option>
                <?php foreach ($sups as $s): ?><option value="<?= (int)$s['id_supplier'] ?>" <?= (string)$s['id_supplier'] === (string)$f['id_supplier'] ? 'selected' : '' ?>><?= e($s['nama_supplier']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Foto nota (opsional)</label><input type="file" class="form-control" name="foto_nota" accept="image/jpeg,image/png,image/webp"><div class="hint">JPG/PNG/WebP, maks. 2 MB</div></div>
            <div class="col-12"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="255" value="<?= e($f['catatan']) ?>" placeholder="Opsional"></div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Rincian barang</span><button type="button" id="add-line" class="btn btn-sm btn-navy"><i class="bi bi-plus-lg"></i> Tambah baris</button></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Barang</th><th>Jumlah</th><th>Harga beli / satuan (Rp)</th><th>Tgl. kedaluwarsa</th><th></th></tr></thead>
                <tbody id="lines">
                <?php foreach ($lines as $l) { echo inbound_row($barangs, $l['id'], $l['jumlah'], $l['harga'], $l['exp']); } ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white hint">Kosongkan tanggal kedaluwarsa jika barang tidak memiliki masa simpan (sistem memakai urutan FIFO untuk batch tersebut).</div>
    </div>
    <template id="line-tpl"><?= inbound_row($barangs) ?></template>
    <button class="btn btn-gold px-4" type="submit"><i class="bi bi-check2-circle"></i> Simpan transaksi</button>
    <a href="inbound.php" class="btn btn-outline-secondary">Batal</a>
</form>

<?php elseif ($view !== null): ?>
<div class="page-head">
    <div><h1><?= e($view['no_transaksi']) ?></h1><div class="sub">Detail barang masuk &middot; transaksi tersimpan tidak dapat diubah (koreksi melalui stock opname)</div></div>
    <div class="d-flex gap-2 no-print"><button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button><a href="inbound.php" class="btn btn-outline-secondary btn-sm">Kembali</a></div>
</div>
<div class="card mb-3"><div class="card-body row g-3">
    <div class="col-md-3"><div class="hint">Tanggal terima</div><strong><?= e(tgl($view['tanggal'])) ?></strong></div>
    <div class="col-md-3"><div class="hint">Supplier</div><strong><?= e($view['nama_supplier']) ?></strong></div>
    <div class="col-md-3"><div class="hint">Dicatat oleh</div><strong><?= e($view['nama_lengkap']) ?></strong></div>
    <div class="col-md-3"><div class="hint">Waktu input</div><strong><?= e(tglwaktu($view['dibuat_pada'])) ?></strong></div>
    <?php if ($view['catatan']): ?><div class="col-12"><div class="hint">Catatan</div><?= e($view['catatan']) ?></div><?php endif; ?>
    <?php if ($view['foto_nota']): ?><div class="col-12"><div class="hint mb-1">Foto nota</div><a href="uploads/<?= e($view['foto_nota']) ?>" target="_blank" rel="noopener"><img src="uploads/<?= e($view['foto_nota']) ?>" alt="Foto nota" style="max-height:180px;border-radius:10px;border:1px solid var(--line)"></a></div><?php endif; ?>
</div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Barang</th><th class="text-end">Jumlah</th><th class="text-end">Sisa batch</th><?php if (is_admin()): ?><th class="text-end">Harga beli</th><th class="text-end">Subtotal</th><?php endif; ?><th>Kedaluwarsa</th></tr></thead>
    <tbody>
    <?php $tot = 0; foreach ($viewLines as $l): $tot += (float)$l['jumlah'] * (float)$l['harga_beli']; ?>
        <tr>
            <td><strong><?= e($l['nama_barang']) ?></strong><div class="hint"><?= e($l['kode_barang']) ?></div></td>
            <td class="text-end num"><?= nf($l['jumlah']) ?> <?= e($l['satuan']) ?></td>
            <td class="text-end num"><?= nf($l['sisa_jumlah']) ?> <?= e($l['satuan']) ?></td>
            <?php if (is_admin()): ?><td class="text-end num"><?= e(rupiah($l['harga_beli'])) ?></td><td class="text-end num"><?= e(rupiah((float)$l['jumlah'] * (float)$l['harga_beli'])) ?></td><?php endif; ?>
            <td><?= e(tgl($l['tgl_kedaluwarsa'])) ?> <?= $l['tgl_kedaluwarsa'] ? badge(status_exp($l['tgl_kedaluwarsa'])) : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if (is_admin()): ?><tfoot><tr><th colspan="4" class="text-end">Total pembelian</th><th class="text-end"><?= e(rupiah($tot)) ?></th><th></th></tr></tfoot><?php endif; ?>
</table></div></div>

<?php else: ?>
<div class="page-head">
    <div><h1>Barang Masuk</h1><div class="sub">Riwayat penerimaan barang dari supplier</div></div>
    <a href="inbound.php?new=1" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Catat Barang Masuk</a>
</div>
<div class="card">
    <div class="card-body pb-2"><input type="search" class="form-control" placeholder="Cari nomor, supplier, petugas..." data-filter="#tbl"></div>
    <div class="table-responsive"><table class="table table-hover mb-0" id="tbl">
        <thead><tr><th>No. transaksi</th><th>Tanggal</th><th>Supplier</th><th class="text-end">Item</th><?php if (is_admin()): ?><th class="text-end">Total</th><?php endif; ?><th>Petugas</th><th>Nota</th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): ?>
            <tr>
                <td><a href="inbound.php?view=<?= (int)$r['id_inbound'] ?>"><strong><?= e($r['no_transaksi']) ?></strong></a></td>
                <td><?= e(tgl($r['tanggal'])) ?></td>
                <td><?= e($r['nama_supplier']) ?></td>
                <td class="text-end"><?= (int)$r['jml_item'] ?></td>
                <?php if (is_admin()): ?><td class="text-end num"><?= e(rupiah($r['total'])) ?></td><?php endif; ?>
                <td><?= e($r['nama_lengkap']) ?></td>
                <td><?= $r['foto_nota'] ? '<a href="uploads/' . e($r['foto_nota']) . '" target="_blank" rel="noopener"><i class="bi bi-image"></i></a>' : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada transaksi barang masuk.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
