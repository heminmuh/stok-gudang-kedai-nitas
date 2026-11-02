<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Data Supplier';
$active = 'supplier';

$errors = [];
$showForm = false;
$f = ['id' => 0, 'nama_supplier' => '', 'kontak' => '', 'telepon' => '', 'alamat' => '', 'aktif' => '1'];

if (is_post()) {
    csrf_check();
    require_admin();
    $act = p('action');
    if ($act === 'save') {
        $f = ['id' => (int)p('id'), 'nama_supplier' => p('nama_supplier'), 'kontak' => p('kontak'), 'telepon' => p('telepon'),
              'alamat' => p('alamat'), 'aktif' => p('aktif') === '1' ? '1' : '0'];
        if ($f['nama_supplier'] === '') {
            $errors[] = 'Nama supplier wajib diisi.';
        }
        if (strlen($f['nama_supplier']) > 120 || strlen($f['kontak']) > 100 || strlen($f['telepon']) > 30 || strlen($f['alamat']) > 255) {
            $errors[] = 'Ada isian yang terlalu panjang.';
        }
        if (!$errors) {
            if ($f['id'] > 0) {
                q('UPDATE supplier SET nama_supplier=?, kontak=?, telepon=?, alamat=?, aktif=? WHERE id_supplier=?',
                  [$f['nama_supplier'], $f['kontak'], $f['telepon'], $f['alamat'], (int)$f['aktif'], $f['id']]);
                flash('success', 'Data supplier diperbarui.');
            } else {
                q('INSERT INTO supplier (nama_supplier, kontak, telepon, alamat, aktif) VALUES (?,?,?,?,?)',
                  [$f['nama_supplier'], $f['kontak'], $f['telepon'], $f['alamat'], (int)$f['aktif']]);
                flash('success', 'Supplier baru ditambahkan.');
            }
            redirect('supplier.php');
        }
        $showForm = true;
    } elseif ($act === 'delete') {
        $id = (int)p('id');
        try {
            q('DELETE FROM supplier WHERE id_supplier = ?', [$id]);
            flash('success', 'Supplier dihapus.');
        } catch (PDOException $ex) {
            q('UPDATE supplier SET aktif = 0 WHERE id_supplier = ?', [$id]);
            flash('info', 'Supplier sudah dipakai pada transaksi sehingga hanya dinonaktifkan.');
        }
        redirect('supplier.php');
    }
}

if (!is_post() && (g('new') !== '' || g('edit') !== '')) {
    require_admin();
    $showForm = true;
    if (g('edit') !== '') {
        $r = row('SELECT * FROM supplier WHERE id_supplier = ?', [(int)g('edit')]);
        if ($r === null) {
            redirect('supplier.php');
        }
        $f = ['id' => (int)$r['id_supplier'], 'nama_supplier' => $r['nama_supplier'], 'kontak' => (string)$r['kontak'],
              'telepon' => (string)$r['telepon'], 'alamat' => (string)$r['alamat'], 'aktif' => (string)$r['aktif']];
    }
}

$list = $showForm ? [] : rows(
    'SELECT s.*, (SELECT COUNT(*) FROM inbound i WHERE i.id_supplier = s.id_supplier) AS jml_trx FROM supplier s ORDER BY s.aktif DESC, s.nama_supplier'
);

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Data Supplier</h1><div class="sub">Pemasok bahan baku Kedai Nita's</div></div>
    <?php if (!$showForm && is_admin()): ?><a href="supplier.php?new=1" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Tambah Supplier</a><?php endif; ?>
</div>

<?php if ($showForm): ?>
<div class="card" style="max-width:720px">
    <div class="card-header"><?= $f['id'] ? 'Ubah supplier' : 'Supplier baru' ?></div>
    <div class="card-body">
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <div class="col-12"><label class="form-label">Nama supplier *</label><input class="form-control" name="nama_supplier" maxlength="120" value="<?= e($f['nama_supplier']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Nama kontak</label><input class="form-control" name="kontak" maxlength="100" value="<?= e($f['kontak']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Telepon</label><input class="form-control" name="telepon" maxlength="30" value="<?= e($f['telepon']) ?>"></div>
            <div class="col-12"><label class="form-label">Alamat</label><textarea class="form-control" name="alamat" rows="2" maxlength="255"><?= e($f['alamat']) ?></textarea></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="aktif" value="1" id="aktif" <?= $f['aktif'] === '1' ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Supplier aktif</label></div></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-gold" type="submit">Simpan</button><a href="supplier.php" class="btn btn-outline-secondary">Batal</a></div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body pb-2"><input type="search" class="form-control" placeholder="Cari supplier..." data-filter="#tbl"></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="tbl">
            <thead><tr><th>Nama</th><th>Kontak</th><th>Telepon</th><th>Alamat</th><th class="text-end">Transaksi</th><?php if (is_admin()): ?><th class="no-print"></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($list as $r): ?>
                <tr class="<?= $r['aktif'] ? '' : 'text-muted' ?>">
                    <td><strong><?= e($r['nama_supplier']) ?></strong><?= $r['aktif'] ? '' : ' <span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td><?= e($r['kontak']) ?></td><td><?= e($r['telepon']) ?></td><td><?= e($r['alamat']) ?></td>
                    <td class="text-end"><?= (int)$r['jml_trx'] ?></td>
                    <?php if (is_admin()): ?>
                    <td class="text-nowrap no-print">
                        <a class="btn btn-sm btn-outline-primary" href="supplier.php?edit=<?= (int)$r['id_supplier'] ?>"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" data-confirm="Hapus supplier ini?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id_supplier'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada supplier.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
