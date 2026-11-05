<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireUser();

$userId = (int) $_SESSION['user_id'];
$error = '';

if (isPost()) {
    verify_csrf();
    $result = postThreadMessage($userId, 'user', null, postString('message', 2000), $_FILES['attachment'] ?? null);
    if ($result['ok']) {
        redirect('messages.php');
    }
    $error = $result['error'];
}

// Everything the shop sent has now been seen by the customer.
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND sender_type = 'admin' AND is_read = 0")->execute([$userId]);

$stmt = $pdo->prepare('SELECT * FROM messages WHERE user_id = ? ORDER BY id ASC');
$stmt->execute([$userId]);
$thread = $stmt->fetchAll();
$self = 'user';

$pageTitle = 'Support';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width:800px">
    <h1 class="h3 mb-1">Support</h1>
    <p class="text-muted">Ask us anything about your orders or our products. We will reply here.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card shadow-sm">
        <?php require __DIR__ . '/includes/chat.php'; ?>
        <form method="post" enctype="multipart/form-data" class="p-3 border-top">
            <?= csrf_field() ?>
            <div class="input-group mb-2">
                <input type="text" name="message" class="form-control" placeholder="Type your message..." maxlength="2000" autocomplete="off">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
            </div>
            <input type="file" name="attachment" class="form-control form-control-sm" accept="image/*,.pdf,.txt" aria-label="Attachment">
            <div class="form-text">Optional attachment: image, PDF or text file, up to 5 MB.</div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
