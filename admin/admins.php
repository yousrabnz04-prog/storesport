<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdminManager();

$me = currentAdmin();
$errors = [];
$old = ['username' => '', 'email' => ''];

if (isPost()) {
    verify_csrf();
    $action = postString('action');

    if ($action === 'add') {
        $old['username'] = postString('username', 50);
        $old['email']    = strtolower(postString('email'));
        $password        = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $canManage       = isset($_POST['can_manage_admins']) ? 1 : 0;

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $old['username'])) {
            $errors[] = 'Username must be 3-50 characters (letters, numbers, dots, dashes, underscores).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 10) {
            $errors[] = 'Administrator passwords must be at least 10 characters long.';
        }
        if (!$errors) {
            $dup = $pdo->prepare('SELECT (SELECT COUNT(*) FROM admins WHERE email = ? OR username = ?) + (SELECT COUNT(*) FROM users WHERE email = ?)');
            $dup->execute([$old['email'], $old['username'], $old['email']]);
            if ((int) $dup->fetchColumn() > 0) {
                $errors[] = 'This email or username is already in use.';
            }
        }
        if (!$errors) {
            $pdo->prepare("INSERT INTO admins (username, email, password, role, can_manage_admins, created_by) VALUES (?, ?, ?, 'admin', ?, ?)")
                ->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $canManage, $me['id']]);
            logAdmin('add_admin', 'admin', (int) $pdo->lastInsertId(), $old['username']);
            flash('success', 'Administrator added.');
            redirect('admin/admins.php');
        }
    } elseif ($action === 'delete') {
        $id = postInt('id');
        $stmt = $pdo->prepare('SELECT username, role FROM admins WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('danger', 'Administrator not found.');
        } elseif ($id === (int) $me['id']) {
            flash('danger', 'You cannot delete your own account.');
        } elseif ($target['role'] === 'owner') {
            flash('danger', 'The owner account cannot be deleted. Transfer ownership first.');
        } else {
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            logAdmin('delete_admin', 'admin', $id, $target['username']);
            flash('success', 'Administrator deleted.');
        }
        redirect('admin/admins.php');
    } elseif ($action === 'transfer' && isOwner()) {
        $newOwner = postInt('new_owner_id');
        $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = ? AND role = 'admin'");
        $stmt->execute([$newOwner]);
        $target = $stmt->fetchColumn();
        if (!$target) {
            flash('danger', 'Choose a valid administrator to become the new owner.');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE admins SET role = 'admin', can_manage_admins = 1 WHERE id = ?")->execute([$me['id']]);
            $pdo->prepare("UPDATE admins SET role = 'owner', can_manage_admins = 1 WHERE id = ?")->execute([$newOwner]);
            $pdo->commit();
            logAdmin('transfer_ownership', 'admin', $newOwner, 'Ownership transferred to ' . $target);
            flash('success', 'Ownership transferred to ' . $target . '.');
        }
        redirect('admin/admins.php');
    }
}

$admins = $pdo->query(
    'SELECT a.*, c.username AS creator FROM admins a LEFT JOIN admins c ON c.id = a.created_by ORDER BY a.role = "owner" DESC, a.id ASC'
)->fetchAll();

$logs = $pdo->query(
    'SELECT l.*, a.username FROM admin_logs l LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC LIMIT 15'
)->fetchAll();

$pageTitle = 'Administrators';
$adminPage = 'admins';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4"><div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Administrator</th><th>Role</th><th>Last sign-in</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($admins as $a): ?>
                        <tr>
                            <td><?= e($a['username']) ?><br><small class="text-muted"><?= e($a['email']) ?></small></td>
                            <td>
                                <?php if ($a['role'] === 'owner'): ?><span class="badge owner-badge">Owner</span>
                                <?php else: ?><span class="badge bg-secondary">Admin</span><?= (int) $a['can_manage_admins'] ? ' <span class="badge bg-info text-dark">Manages admins</span>' : '' ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $a['last_login'] ? e(date('M j, Y H:i', strtotime($a['last_login']))) : '<span class="text-muted">Never</span>' ?></td>
                            <td class="text-end">
                                <?php if ($a['role'] !== 'owner' && (int) $a['id'] !== (int) $me['id']): ?>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete administrator &quot;<?= e($a['username']) ?>&quot;?">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>

        <div class="card shadow-sm"><div class="card-header bg-white"><strong>Recent activity</strong></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($logs as $l): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><strong><?= e($l['username'] ?? 'Removed admin') ?></strong> - <?= e(str_replace('_', ' ', $l['action'])) ?><?= $l['details'] ? ': ' . e($l['details']) : '' ?></span>
                        <span class="text-muted ms-2 text-nowrap"><?= e(date('M j, H:i', strtotime($l['created_at']))) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$logs): ?><li class="list-group-item text-muted">No activity recorded yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm mb-4"><div class="card-body">
            <h2 class="h5 mb-3">Add an administrator</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?= e($old['username']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password <span class="text-muted small">(min. 10 characters)</span></label>
                    <input type="password" id="password" name="password" class="form-control" minlength="10" required autocomplete="new-password">
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="can_manage_admins" name="can_manage_admins" value="1">
                    <label for="can_manage_admins" class="form-check-label">Can manage administrators</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">Add administrator</button>
            </form>
        </div></div>

        <?php if (isOwner()): ?>
            <?php $candidates = array_filter($admins, fn($a) => $a['role'] === 'admin'); ?>
            <?php if ($candidates): ?>
                <div class="card shadow-sm border-warning"><div class="card-body">
                    <h2 class="h5 mb-2">Transfer ownership</h2>
                    <p class="small text-muted">You will become a regular administrator with the "manage administrators" permission.</p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="transfer">
                        <select name="new_owner_id" class="form-select mb-3" required aria-label="New owner">
                            <option value="">Choose an administrator...</option>
                            <?php foreach ($candidates as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['username']) ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-warning w-100" data-confirm="Transfer ownership? This cannot be undone by you.">Transfer</button>
                    </form>
                </div></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
