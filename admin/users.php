<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

const USERS_PER_PAGE = 20;

if (isPost()) {
    verify_csrf();
    $userId = postInt('id');
    $status = postString('status', 10) === 'banned' ? 'banned' : 'active';

    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    if ($username = $stmt->fetchColumn()) {
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $userId]);
        logAdmin($status === 'banned' ? 'ban_user' : 'unban_user', 'user', $userId, $username);
        flash('success', 'Customer "' . $username . '" is now ' . $status . '.');
    }
    redirect('admin/users.php');
}

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE u.username LIKE ? OR u.email LIKE ?';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $params = [$like, $like];
}

$count = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$count->execute($params);
$pages = max(1, (int) ceil((int) $count->fetchColumn() / USERS_PER_PAGE));
$page = min(pageParam(), $pages);

$stmt = $pdo->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count FROM users u $where
     ORDER BY u.id DESC LIMIT " . USERS_PER_PAGE . ' OFFSET ' . (($page - 1) * USERS_PER_PAGE)
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Customers';
$adminPage = 'users';
require __DIR__ . '/../includes/admin_header.php';
?>
<form method="get" class="d-flex gap-2 mb-3" style="max-width:420px">
    <input type="search" name="q" class="form-control" placeholder="Search by name or email" value="<?= e($search) ?>">
    <button class="btn btn-outline-secondary" type="submit">Search</button>
</form>
<div class="card shadow-sm"><div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>Customer</th><th>Email</th><th>Orders</th><th>Joined</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= (int) $u['order_count'] ?></td>
                    <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td><?= statusBadge($u['status']) ?></td>
                    <td class="text-end">
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <?php if ($u['status'] === 'active'): ?>
                                <input type="hidden" name="status" value="banned">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Suspend this customer?">Suspend</button>
                            <?php else: ?>
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<div class="mt-3"><?= renderPagination($page, $pages, array_filter(['q' => $search])) ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
