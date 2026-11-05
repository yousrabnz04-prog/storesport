<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Chat attachments are private: only the customer of the thread and administrators may download them.
if (!isLoggedIn() || (isAdmin() && currentAdmin() === null)) {
    http_response_code(403);
    exit('Forbidden');
}

$stmt = $pdo->prepare('SELECT user_id, file_path, file_name FROM messages WHERE id = ? AND file_path IS NOT NULL');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$msg = $stmt->fetch();

if (!$msg || (!isAdmin() && (int) $msg['user_id'] !== (int) $_SESSION['user_id'])) {
    http_response_code(404);
    exit('File not found');
}

$path = MESSAGES_DIR . '/' . basename($msg['file_path']);
if (!is_file($path)) {
    http_response_code(404);
    exit('File not found');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
$inline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true);
$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($msg['file_name'] ?: basename($path)));

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, max-age=3600');
readfile($path);
