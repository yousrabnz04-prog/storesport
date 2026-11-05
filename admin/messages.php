<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$adminId  = (int) $_SESSION['user_id'];
$threadId = (int) ($_GET['user_id'] ?? 0);
$error = '';

if (isPost()) {
    verify_csrf();
    $threadId = postInt('user_id');
    $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
    $exists->execute([$threadId]);
    if ((int) $exists->fetchColumn() === 0) {
        flash('danger', 'Customer not found.');
        redirect('admin/messages.php');
    }
    $result = postThreadMessage($threadId, 'admin', $adminId, postString('message', 2000), $_FILES['attachment'] ?? null);
    if ($result['ok']) {
        redirect('admin/messages.php?user_id=' . $threadId);
    }
    $error = $result['error'];
}

$threads = $pdo->query(
    "SELECT u.id, u.username, u.email, MAX(m.created_at) AS last_time,
            SUM(m.sender_type = 'user' AND m.is_read = 0) AS unread
     FROM messages m JOIN users u ON u.id = m.user_id
     GROUP BY u.id, u.username, u.email ORDER BY MAX(m.id) DESC"
)->fetchAll();

$customer = null;
$thread = [];
$self = 'admin';
if ($threadId > 0) {
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $stmt->execute([$threadId]);
    $customer = $stmt->fetch();
    if ($customer) {
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND sender_type = 'user' AND is_read = 0")->execute([$threadId]);
        $stmt = $pdo->prepare('SELECT * FROM messages WHERE user_id = ? ORDER BY id ASC');
        $stmt->execute([$threadId]);
        $thread = $stmt->fetchAll();
    }
}

$pageTitle = 'Messages';
$adminPage = 'messages';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="list-group shadow-sm">
            <?php foreach ($threads as $t): ?>
                <a href="<?= e(url('admin/messages.php?user_id=' . (int) $t['id'])) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?= $threadId === (int) $t['id'] ? ' active' : '' ?>">
                    <span>
                        <strong><?= e($t['username']) ?></strong><br>
                        <small class="<?= $threadId === (int) $t['id'] ? '' : 'text-muted' ?>"><?= e(date('M j, H:i', strtotime($t['last_time']))) ?></small>
                    </span>
                    <?php if ((int) $t['unread'] > 0): ?><span class="badge bg-danger rounded-pill"><?= (int) $t['unread'] ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if (!$threads): ?><div class="list-group-item text-muted">No conversations yet.</div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if ($customer): ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong><?= e($customer['username']) ?></strong> <span class="text-muted small"><?= e($customer['email']) ?></span></div>
                <?php $threadUserId = $customer['id']; require __DIR__ . '/../includes/chat.php'; ?>
                <form method="post" enctype="multipart/form-data" class="p-3 border-top">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $customer['id'] ?>">
                    <div class="input-group mb-2">
                        <input type="text" name="message" class="form-control" placeholder="Write a reply..." maxlength="2000" autocomplete="off">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                    </div>
                    <input type="file" name="attachment" class="form-control form-control-sm" accept="image/*,.pdf,.txt" aria-label="Attachment">
                </form>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-5">Select a conversation to read and reply.</div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
