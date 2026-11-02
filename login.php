<?php
require_once __DIR__ . '/includes/auth.php';

if (user()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';
$now = time();
$locked = isset($_SESSION['lock_until']) && $_SESSION['lock_until'] > $now;

if (is_post()) {
    csrf_check();
    $username = p('username');
    if ($locked) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . ($_SESSION['lock_until'] - $now) . ' detik.';
    } elseif (attempt_login($username, isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '')) {
        unset($_SESSION['fail'], $_SESSION['lock_until']);
        redirect('dashboard.php');
    } else {
        $_SESSION['fail'] = (isset($_SESSION['fail']) ? $_SESSION['fail'] : 0) + 1;
        $error = 'Username atau kata sandi salah.';
        if ($_SESSION['fail'] >= 5) {
            $_SESSION['lock_until'] = $now + 60;
            $_SESSION['fail'] = 0;
            $error = 'Terlalu banyak percobaan gagal. Akun dikunci 60 detik.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-ico"><i class="bi bi-box-seam-fill"></i></div>
        <h1 class="h4 fw-bold mb-1" style="color:var(--navy)">Sistem Stok Gudang</h1>
        <p class="text-muted small mb-4">Kedai Nita's Rembang &middot; masuk untuk melanjutkan</p>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="alert alert-warning py-2 small">Sesi berakhir karena tidak ada aktivitas. Silakan masuk kembali.</div>
        <?php elseif (isset($_GET['bye'])): ?>
            <div class="alert alert-success py-2 small">Anda telah keluar.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Kata sandi</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-gold w-100 py-2">Masuk</button>
        </form>
    </div>
</div>
</body>
</html>
