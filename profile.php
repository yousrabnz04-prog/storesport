<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireUser();

$userId = (int) $_SESSION['user_id'];
$errors = [];

if (isPost()) {
    verify_csrf();
    $action = postString('action');

    if ($action === 'profile') {
        $phone   = postString('phone', 30);
        $address = postString('address', 1000);
        if ($phone !== '' && !preg_match('/^[0-9+().\s-]{6,30}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        } else {
            $pdo->prepare('UPDATE users SET phone = ?, address = ? WHERE id = ?')
                ->execute([$phone ?: null, $address ?: null, $userId]);
            flash('success', 'Your profile has been updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'password') {
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new     = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        $hash = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $hash->execute([$userId]);
        if (!password_verify($current, (string) $hash->fetchColumn())) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'The new password must be at least 8 characters long.';
        } elseif ($new !== $confirm) {
            $errors[] = 'The new passwords do not match.';
        } else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            redirect('profile.php');
        }
    }
}

$stmt = $pdo->prepare('SELECT username, email, phone, address, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$pageTitle = 'My profile';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <h1 class="h3 mb-4">My profile</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm"><div class="card-body">
                <h2 class="h5 mb-3">Account details</h2>
                <p class="mb-1"><strong>Username:</strong> <?= e($user['username']) ?></p>
                <p class="mb-3"><strong>Email:</strong> <?= e($user['email']) ?></p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($user['phone']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Default shipping address</label>
                        <textarea id="address" name="address" rows="3" class="form-control"><?= e($user['address']) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm"><div class="card-body">
                <h2 class="h5 mb-3">Change password</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input type="password" id="password" name="new_password" class="form-control" minlength="8" required autocomplete="new-password">
                        <div class="progress mt-2" style="height:5px"><div id="passwordStrength" class="progress-bar" style="width:0"></div></div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm new password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password">
                        <div id="passwordMatch" class="form-text"></div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Update password</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
