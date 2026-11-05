<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$old = ['username' => '', 'email' => '', 'phone' => ''];

if (isPost()) {
    verify_csrf();
    $old['username'] = postString('username', 30);
    $old['email']    = strtolower(postString('email'));
    $old['phone']    = postString('phone', 30);
    $password        = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm         = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $old['username'])) {
        $errors[] = 'Username must be 3-30 characters (letters, numbers, dots, dashes, underscores).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+().\s-]{6,30}$/', $old['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$old['email'], $old['username']]);
        $taken = (int) $stmt->fetchColumn() > 0;
        if (!$taken) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email = ?');
            $stmt->execute([$old['email']]);
            $taken = (int) $stmt->fetchColumn() > 0;
        }
        if ($taken) {
            $errors[] = 'This email or username is already registered.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password, phone) VALUES (?, ?, ?, ?)');
        $stmt->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['phone'] ?: null]);

        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $pdo->lastInsertId();
        $_SESSION['user_type'] = 'user';
        $_SESSION['username']  = $old['username'];
        flash('success', 'Welcome to ' . SITE_NAME . '! Your account has been created.');
        redirect('index.php');
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4 text-center">Create your account</h1>
                    <?php if ($errors): ?>
                        <div class="alert alert-danger mb-3"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?= e($old['username']) ?>" required autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone <span class="text-muted small">(optional)</span></label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($old['phone']) ?>" autocomplete="tel">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
                            <div class="progress mt-2" style="height:5px"><div id="passwordStrength" class="progress-bar" style="width:0"></div></div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password">
                            <div id="passwordMatch" class="form-text"></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Create account</button>
                    </form>
                    <p class="text-center small mt-3 mb-0">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
