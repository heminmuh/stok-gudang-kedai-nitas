<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$page_title = 'Pengguna & Hak Akses';
$active = 'pengguna';
$me = user();

$errors = [];
$showForm = false;
$f = ['id' => 0, 'nama_lengkap' => '', 'username' => '', 'peran' => 'petugas', 'aktif' => '1'];

if (is_post()) {
    csrf_check();
    $f = ['id' => (int)p('id'), 'nama_lengkap' => p('nama_lengkap'), 'username' => strtolower(p('username')),
          'peran' => p('peran') === 'admin' ? 'admin' : 'petugas', 'aktif' => p('aktif') === '1' ? '1' : '0'];
    $pass = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    if ($f['nama_lengkap'] === '' || $f['username'] === '') {
        $errors[] = 'Nama lengkap dan username wajib diisi.';
    }
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $f['username'])) {
        $errors[] = 'Username 3-30 karakter: huruf kecil, angka, titik, strip, atau garis bawah.';
    }
    if ((int)val('SELECT COUNT(*) FROM pengguna WHERE username = ? AND id_pengguna <> ?', [$f['username'], $f['id']]) > 0) {
        $errors[] = 'Username sudah dipakai.';
    }
    if ($f['id'] === 0 && strlen($pass) < 6) {
        $errors[] = 'Kata sandi minimal 6 karakter.';
    }
    if ($f['id'] > 0 && $pass !== '' && strlen($pass) < 6) {
        $errors[] = 'Kata sandi baru minimal 6 karakter.';
    }
    if ($f['id'] === (int)$me['id'] && ($f['peran'] !== 'admin' || $f['aktif'] !== '1')) {
        $errors[] = 'Anda tidak dapat menurunkan peran atau menonaktifkan akun Anda sendiri.';
    }
    if (!$errors) {
        if ($f['id'] > 0) {
            q('UPDATE pengguna SET nama_lengkap=?, username=?, peran=?, aktif=? WHERE id_pengguna=?', [$f['nama_lengkap'], $f['username'], $f['peran'], (int)$f['aktif'], $f['id']]);
            if ($pass !== '') {
                q('UPDATE pengguna SET password_hash=? WHERE id_pengguna=?', [password_hash($pass, PASSWORD_DEFAULT), $f['id']]);
            }
            flash('success', 'Data pengguna diperbarui.');
        } else {
            q('INSERT INTO pengguna (nama_lengkap, username, password_hash, peran, aktif, dibuat_pada) VALUES (?,?,?,?,?,?)',
              [$f['nama_lengkap'], $f['username'], password_hash($pass, PASSWORD_DEFAULT), $f['peran'], (int)$f['aktif'], date('Y-m-d H:i:s')]);
            flash('success', 'Pengguna baru ditambahkan.');
        }
        redirect('pengguna.php');
    }
    $showForm = true;
}

if (!is_post() && (g('new') !== '' || g('edit') !== '')) {
    $showForm = true;
    if (g('edit') !== '') {
        $r = row('SELECT * FROM pengguna WHERE id_pengguna = ?', [(int)g('edit')]);
        if ($r === null) {
            redirect('pengguna.php');
        }
        $f = ['id' => (int)$r['id_pengguna'], 'nama_lengkap' => $r['nama_lengkap'], 'username' => $r['username'], 'peran' => $r['peran'], 'aktif' => (string)$r['aktif']];
    }
}

$list = $showForm ? [] : rows('SELECT * FROM pengguna ORDER BY aktif DESC, peran, nama_lengkap');

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Pengguna &amp; Hak Akses</h1><div class="sub">Admin/Owner memiliki akses penuh; Petugas Gudang dibatasi sesuai tugas (least privilege)</div></div>
    <?php if (!$showForm): ?><a href="pengguna.php?new=1" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Tambah Pengguna</a><?php endif; ?>
</div>

<?php if ($showForm): ?>
<div class="card" style="max-width:640px">
    <div class="card-header"><?= $f['id'] ? 'Ubah pengguna' : 'Pengguna baru' ?></div>
    <div class="card-body">
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <div class="col-12"><label class="form-label">Nama lengkap *</label><input class="form-control" name="nama_lengkap" maxlength="100" value="<?= e($f['nama_lengkap']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Username *</label><input class="form-control" name="username" maxlength="30" value="<?= e($f['username']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Kata sandi <?= $f['id'] ? '(kosongkan jika tidak diubah)' : '*' ?></label><input type="password" class="form-control" name="password" autocomplete="new-password" <?= $f['id'] ? '' : 'required' ?>></div>
            <div class="col-md-6"><label class="form-label">Peran</label>
                <select class="form-select" name="peran">
                    <option value="petugas" <?= $f['peran'] === 'petugas' ? 'selected' : '' ?>>Petugas Gudang / Staf Dapur</option>
                    <option value="admin" <?= $f['peran'] === 'admin' ? 'selected' : '' ?>>Admin / Owner</option>
                </select></div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="aktif" value="1" id="aktif" <?= $f['aktif'] === '1' ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Akun aktif</label></div></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-gold" type="submit">Simpan</button><a href="pengguna.php" class="btn btn-outline-secondary">Batal</a></div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th>Dibuat</th><th class="no-print"></th></tr></thead>
            <tbody>
            <?php foreach ($list as $r): ?>
                <tr>
                    <td><strong><?= e($r['nama_lengkap']) ?></strong></td>
                    <td><?= e($r['username']) ?></td>
                    <td><span class="badge text-bg-<?= $r['peran'] === 'admin' ? 'dark' : 'info' ?>"><?= $r['peran'] === 'admin' ? 'Admin / Owner' : 'Petugas Gudang' ?></span></td>
                    <td><?= $r['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td><?= e(tglwaktu($r['dibuat_pada'])) ?></td>
                    <td class="no-print"><a class="btn btn-sm btn-outline-primary" href="pengguna.php?edit=<?= (int)$r['id_pengguna'] ?>"><i class="bi bi-pencil"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
