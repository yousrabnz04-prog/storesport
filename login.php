<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/' : 'index.php');
}

$error = '';
$email = '';

if (isPost()) {
    verify_csrf();
    $email    = strtolower(postString('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // Basic brute-force slow-down (per session). Add server-level rate limiting in production.
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'since' => time()];
    if (time() - $attempts['since'] > 300) {
        $attempts = ['count' => 0, 'since' => time()];
    }

    if ($attempts['count'] >= 5) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } else {
        $account = null;
        $type    = null;

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password'])) {
            $account = $row;
            $type = 'user';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password'])) {
                $account = $row;
                $type = 'admin';
            }
        }

        if ($account === null) {
            $attempts['count']++;
            $_SESSION['login_attempts'] = $attempts;
            $error = 'Invalid email or password.';
        } elseif ($type === 'user' && $account['status'] === 'banned') {
            $error = 'This account has been suspended. Please contact support.';
        } else {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts']);
            $_SESSION['user_id']   = (int) $account['id'];
            $_SESSION['user_type'] = $type;
            $_SESSION['username']  = $account['username'];

            if ($type === 'admin') {
                $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$account['id']]);
                header('Location: ' . url('admin/'));
            } else {
                header('Location: ' . consumeRedirectTarget('index.php'));
            }
            exit;
        }
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4 text-center">Sign in</h1>
                    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign in</button>
                    </form>
                    <p class="text-center small mt-3 mb-0">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
