<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Data Barang';
$active = 'barang';

$errors = [];
$showForm = false;
$f = ['id' => 0, 'kode_barang' => '', 'nama_barang' => '', 'kategori' => '', 'satuan' => '', 'stok_minimum' => '0',
      'harga_beli_terakhir' => '0', 'id_rak' => '', 'id_supplier_utama' => '', 'aktif' => '1'];

if (is_post()) {
    csrf_check();
    require_admin();
    $act = p('action');

    if ($act === 'save') {
        $f = [
            'id' => (int)p('id'),
            'kode_barang' => strtoupper(p('kode_barang')),
            'nama_barang' => p('nama_barang'),
            'kategori' => p('kategori'),
            'satuan' => p('satuan'),
            'stok_minimum' => p('stok_minimum', '0'),
            'harga_beli_terakhir' => p('harga_beli_terakhir', '0'),
            'id_rak' => p('id_rak'),
            'id_supplier_utama' => p('id_supplier_utama'),
            'aktif' => p('aktif') === '1' ? '1' : '0',
        ];
        if ($f['kode_barang'] === '' || $f['nama_barang'] === '' || $f['satuan'] === '') {
            $errors[] = 'Kode, nama, dan satuan wajib diisi.';
        }
        if (strlen($f['kode_barang']) > 30 || strlen($f['nama_barang']) > 120 || strlen($f['satuan']) > 20 || strlen($f['kategori']) > 60) {
            $errors[] = 'Ada isian yang terlalu panjang.';
        }
        if (!is_numeric($f['stok_minimum']) || (float)$f['stok_minimum'] < 0 || !is_numeric($f['harga_beli_terakhir']) || (float)$f['harga_beli_terakhir'] < 0) {
            $errors[] = 'Stok minimum dan harga harus berupa angka tidak negatif.';
        }
        if ((int)val('SELECT COUNT(*) FROM barang WHERE kode_barang = ? AND id_barang <> ?', [$f['kode_barang'], $f['id']]) > 0) {
            $errors[] = 'Kode barang sudah dipakai barang lain.';
        }
        if (!$errors) {
            $rak = (int)$f['id_rak'] > 0 ? (int)$f['id_rak'] : null;
            $sup = (int)$f['id_supplier_utama'] > 0 ? (int)$f['id_supplier_utama'] : null;
            if ($f['id'] > 0) {
                q('UPDATE barang SET kode_barang=?, nama_barang=?, kategori=?, satuan=?, stok_minimum=?, harga_beli_terakhir=?, id_rak=?, id_supplier_utama=?, aktif=? WHERE id_barang=?',
                  [$f['kode_barang'], $f['nama_barang'], $f['kategori'], $f['satuan'], round((float)$f['stok_minimum'], 2), round((float)$f['harga_beli_terakhir'], 2), $rak, $sup, (int)$f['aktif'], $f['id']]);
                flash('success', 'Data barang diperbarui.');
            } else {
                q('INSERT INTO barang (kode_barang, nama_barang, kategori, satuan, stok_minimum, stok_saat_ini, harga_beli_terakhir, id_rak, id_supplier_utama, aktif) VALUES (?,?,?,?,?,0,?,?,?,?)',
                  [$f['kode_barang'], $f['nama_barang'], $f['kategori'], $f['satuan'], round((float)$f['stok_minimum'], 2), round((float)$f['harga_beli_terakhir'], 2), $rak, $sup, (int)$f['aktif']]);
                flash('success', 'Barang baru ditambahkan. Stok awal diisi lewat menu Barang Masuk.');
            }
            redirect('barang.php');
        }
        $showForm = true;
    } elseif ($act === 'delete') {
        $id = (int)p('id');
        try {
            q('DELETE FROM barang WHERE id_barang = ?', [$id]);
            flash('success', 'Barang dihapus.');
        } catch (PDOException $ex) {
            q('UPDATE barang SET aktif = 0 WHERE id_barang = ?', [$id]);
            flash('info', 'Barang sudah memiliki riwayat transaksi sehingga tidak dihapus, hanya dinonaktifkan.');
        }
        redirect('barang.php');
    }
}

if (!is_post() && (g('new') !== '' || g('edit') !== '')) {
    require_admin();
    $showForm = true;
    if (g('edit') !== '') {
        $r = row('SELECT * FROM barang WHERE id_barang = ?', [(int)g('edit')]);
        if ($r === null) {
            redirect('barang.php');
        }
        $f = [
            'id' => (int)$r['id_barang'], 'kode_barang' => $r['kode_barang'], 'nama_barang' => $r['nama_barang'],
            'kategori' => (string)$r['kategori'], 'satuan' => $r['satuan'], 'stok_minimum' => $r['stok_minimum'],
            'harga_beli_terakhir' => $r['harga_beli_terakhir'], 'id_rak' => (string)$r['id_rak'],
            'id_supplier_utama' => (string)$r['id_supplier_utama'], 'aktif' => (string)$r['aktif'],
        ];
    }
}

$raks = rows('SELECT id_rak, kode_rak, nama_rak FROM lokasi_rak ORDER BY kode_rak');
$sups = rows('SELECT id_supplier, nama_supplier FROM supplier WHERE aktif = 1 ORDER BY nama_supplier');
$list = $showForm ? [] : rows(
    'SELECT b.*, r.nama_rak, s.nama_supplier FROM barang b
     LEFT JOIN lokasi_rak r ON r.id_rak = b.id_rak
     LEFT JOIN supplier s ON s.id_supplier = b.id_supplier_utama
     ORDER BY b.aktif DESC, b.nama_barang'
);

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Data Barang</h1>
        <div class="sub">Master barang, satuan, stok minimum, dan lokasi penyimpanan</div>
    </div>
    <?php if (!$showForm && is_admin()): ?>
        <a href="barang.php?new=1" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Tambah Barang</a>
    <?php endif; ?>
</div>

<?php if ($showForm): ?>
<div class="card" style="max-width:860px">
    <div class="card-header"><?= $f['id'] ? 'Ubah barang' : 'Barang baru' ?></div>
    <div class="card-body">
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <div class="col-md-4"><label class="form-label">Kode barang *</label>
                <input class="form-control" name="kode_barang" maxlength="30" value="<?= e($f['kode_barang']) ?>" required></div>
            <div class="col-md-8"><label class="form-label">Nama barang *</label>
                <input class="form-control" name="nama_barang" maxlength="120" value="<?= e($f['nama_barang']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Kategori</label>
                <input class="form-control" name="kategori" list="kat" maxlength="60" value="<?= e($f['kategori']) ?>">
                <datalist id="kat"><option>Bahan Kering</option><option>Produk Susu</option><option>Daging &amp; Unggas</option><option>Sayur &amp; Bumbu</option><option>Minuman</option><option>Lainnya</option></datalist></div>
            <div class="col-md-4"><label class="form-label">Satuan *</label>
                <input class="form-control" name="satuan" list="sat" maxlength="20" value="<?= e($f['satuan']) ?>" required>
                <datalist id="sat"><option>kg</option><option>gram</option><option>liter</option><option>ml</option><option>pcs</option><option>pak</option><option>botol</option><option>karton</option><option>ikat</option></datalist></div>
            <div class="col-md-4"><label class="form-label">Stok minimum</label>
                <input type="number" step="0.01" min="0" class="form-control" name="stok_minimum" value="<?= e($f['stok_minimum']) ?>">
                <div class="hint">Peringatan muncul saat stok &le; nilai ini.</div></div>
            <div class="col-md-4"><label class="form-label">Harga beli terakhir (Rp)</label>
                <input type="number" step="0.01" min="0" class="form-control" name="harga_beli_terakhir" value="<?= e($f['harga_beli_terakhir']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Lokasi rak</label>
                <select class="form-select" name="id_rak"><option value="">-</option>
                <?php foreach ($raks as $r): ?><option value="<?= (int)$r['id_rak'] ?>" <?= (string)$r['id_rak'] === (string)$f['id_rak'] ? 'selected' : '' ?>><?= e($r['kode_rak'] . ' - ' . $r['nama_rak']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-4"><label class="form-label">Supplier utama</label>
                <select class="form-select" name="id_supplier_utama"><option value="">-</option>
                <?php foreach ($sups as $s): ?><option value="<?= (int)$s['id_supplier'] ?>" <?= (string)$s['id_supplier'] === (string)$f['id_supplier_utama'] ? 'selected' : '' ?>><?= e($s['nama_supplier']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-12"><div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="aktif" value="1" id="aktif" <?= $f['aktif'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="aktif">Barang aktif</label></div>
                <?php if (!$f['id']): ?><div class="hint mt-1">Stok awal tidak diisi di sini; catat lewat menu <strong>Barang Masuk</strong> agar tercatat di kartu stok.</div><?php endif; ?></div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-gold" type="submit">Simpan</button>
                <a href="barang.php" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body pb-2"><input type="search" class="form-control" placeholder="Cari kode, nama, kategori..." data-filter="#tbl"></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="tbl">
            <thead><tr><th>Kode</th><th>Nama barang</th><th>Kategori</th><th class="text-end">Stok</th><th class="text-end">Min.</th><th>Status</th><th>Lokasi</th><th>Supplier utama</th><?php if (is_admin()): ?><th class="no-print"></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($list as $r): $st = status_stok((float)$r['stok_saat_ini'], (float)$r['stok_minimum']); ?>
                <tr class="<?= $r['aktif'] ? '' : 'text-muted' ?>">
                    <td><strong><?= e($r['kode_barang']) ?></strong></td>
                    <td><?= e($r['nama_barang']) ?><?= $r['aktif'] ? '' : ' <span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td><?= e($r['kategori']) ?></td>
                    <td class="text-end num"><?= nf($r['stok_saat_ini']) ?> <?= e($r['satuan']) ?></td>
                    <td class="text-end num"><?= nf($r['stok_minimum']) ?></td>
                    <td><?= badge($st) ?></td>
                    <td><?= e($r['nama_rak']) ?></td>
                    <td><?= e($r['nama_supplier']) ?></td>
                    <?php if (is_admin()): ?>
                    <td class="text-nowrap no-print">
                        <a class="btn btn-sm btn-outline-primary" href="barang.php?edit=<?= (int)$r['id_barang'] ?>" title="Ubah"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" data-confirm="Hapus barang ini? Jika sudah punya riwayat, barang hanya dinonaktifkan.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id_barang'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="9" class="text-center text-muted py-4">Belum ada data barang.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
