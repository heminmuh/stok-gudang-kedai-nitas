<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Lokasi Rak';
$active = 'rak';

$errors = [];
$showForm = false;
$f = ['id' => 0, 'kode_rak' => '', 'nama_rak' => '', 'jenis' => 'Rak', 'keterangan' => ''];

if (is_post()) {
    csrf_check();
    require_admin();
    $act = p('action');
    if ($act === 'save') {
        $f = ['id' => (int)p('id'), 'kode_rak' => strtoupper(p('kode_rak')), 'nama_rak' => p('nama_rak'), 'jenis' => p('jenis', 'Rak'), 'keterangan' => p('keterangan')];
        if ($f['kode_rak'] === '' || $f['nama_rak'] === '') {
            $errors[] = 'Kode dan nama rak wajib diisi.';
        }
        if (strlen($f['kode_rak']) > 20 || strlen($f['nama_rak']) > 100 || strlen($f['jenis']) > 30 || strlen($f['keterangan']) > 255) {
            $errors[] = 'Ada isian yang terlalu panjang.';
        }
        if ((int)val('SELECT COUNT(*) FROM lokasi_rak WHERE kode_rak = ? AND id_rak <> ?', [$f['kode_rak'], $f['id']]) > 0) {
            $errors[] = 'Kode rak sudah dipakai.';
        }
        if (!$errors) {
            if ($f['id'] > 0) {
                q('UPDATE lokasi_rak SET kode_rak=?, nama_rak=?, jenis=?, keterangan=? WHERE id_rak=?', [$f['kode_rak'], $f['nama_rak'], $f['jenis'], $f['keterangan'], $f['id']]);
                flash('success', 'Lokasi rak diperbarui.');
            } else {
                q('INSERT INTO lokasi_rak (kode_rak, nama_rak, jenis, keterangan) VALUES (?,?,?,?)', [$f['kode_rak'], $f['nama_rak'], $f['jenis'], $f['keterangan']]);
                flash('success', 'Lokasi rak ditambahkan.');
            }
            redirect('rak.php');
        }
        $showForm = true;
    } elseif ($act === 'delete') {
        try {
            q('DELETE FROM lokasi_rak WHERE id_rak = ?', [(int)p('id')]);
            flash('success', 'Lokasi rak dihapus.');
        } catch (PDOException $ex) {
            flash('danger', 'Lokasi rak masih dipakai oleh data barang, tidak dapat dihapus.');
        }
        redirect('rak.php');
    }
}

if (!is_post() && (g('new') !== '' || g('edit') !== '')) {
    require_admin();
    $showForm = true;
    if (g('edit') !== '') {
        $r = row('SELECT * FROM lokasi_rak WHERE id_rak = ?', [(int)g('edit')]);
        if ($r === null) {
            redirect('rak.php');
        }
        $f = ['id' => (int)$r['id_rak'], 'kode_rak' => $r['kode_rak'], 'nama_rak' => $r['nama_rak'], 'jenis' => (string)$r['jenis'], 'keterangan' => (string)$r['keterangan']];
    }
}

$list = $showForm ? [] : rows('SELECT r.*, (SELECT COUNT(*) FROM barang b WHERE b.id_rak = r.id_rak) AS jml FROM lokasi_rak r ORDER BY r.kode_rak');

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Lokasi Rak</h1><div class="sub">Rak, chiller, dan freezer penyimpanan bahan</div></div>
    <?php if (!$showForm && is_admin()): ?><a href="rak.php?new=1" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Tambah Lokasi</a><?php endif; ?>
</div>

<?php if ($showForm): ?>
<div class="card" style="max-width:640px">
    <div class="card-header"><?= $f['id'] ? 'Ubah lokasi' : 'Lokasi baru' ?></div>
    <div class="card-body">
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <div class="col-md-4"><label class="form-label">Kode *</label><input class="form-control" name="kode_rak" maxlength="20" value="<?= e($f['kode_rak']) ?>" required></div>
            <div class="col-md-8"><label class="form-label">Nama *</label><input class="form-control" name="nama_rak" maxlength="100" value="<?= e($f['nama_rak']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Jenis</label>
                <select class="form-select" name="jenis"><?php foreach (['Rak', 'Chiller', 'Freezer', 'Gudang kering', 'Lainnya'] as $j): ?><option <?= $f['jenis'] === $j ? 'selected' : '' ?>><?= e($j) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">Keterangan</label><input class="form-control" name="keterangan" maxlength="255" value="<?= e($f['keterangan']) ?>"></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-gold" type="submit">Simpan</button><a href="rak.php" class="btn btn-outline-secondary">Batal</a></div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Keterangan</th><th class="text-end">Jumlah barang</th><?php if (is_admin()): ?><th class="no-print"></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($list as $r): ?>
                <tr>
                    <td><strong><?= e($r['kode_rak']) ?></strong></td><td><?= e($r['nama_rak']) ?></td><td><?= e($r['jenis']) ?></td><td><?= e($r['keterangan']) ?></td>
                    <td class="text-end"><?= (int)$r['jml'] ?></td>
                    <?php if (is_admin()): ?>
                    <td class="text-nowrap no-print">
                        <a class="btn btn-sm btn-outline-primary" href="rak.php?edit=<?= (int)$r['id_rak'] ?>"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" data-confirm="Hapus lokasi ini?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id_rak'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada lokasi rak.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
