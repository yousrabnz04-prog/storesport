<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isLoggedIn() || (isAdmin() && currentAdmin() === null)) {
    jsonResponse(['ok' => false, 'messages' => []], 401);
}

$after = max(0, (int) ($_GET['after'] ?? 0));

if (isAdmin()) {
    $threadUser = (int) ($_GET['user_id'] ?? 0);
    $viewer = 'admin';
    $other = 'user';
} else {
    $threadUser = (int) $_SESSION['user_id'];
    $viewer = 'user';
    $other = 'admin';
}

$stmt = $pdo->prepare('SELECT id, sender_type, message, file_path, file_name, created_at FROM messages WHERE user_id = ? AND id > ? ORDER BY id ASC LIMIT 100');
$stmt->execute([$threadUser, $after]);
$rows = $stmt->fetchAll();

if ($rows) {
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE user_id = ? AND sender_type = ? AND is_read = 0')->execute([$threadUser, $other]);
}

$messages = array_map(fn($m) => [
    'id'      => (int) $m['id'],
    'mine'    => $m['sender_type'] === $viewer,
    'message' => $m['message'],
    'file'    => !empty($m['file_path']) ? ['name' => $m['file_name'] ?: 'Attachment', 'url' => url('download.php?id=' . (int) $m['id'])] : null,
    'time'    => date('M j, H:i', strtotime($m['created_at'])),
], $rows);

jsonResponse(['ok' => true, 'messages' => $messages]);
