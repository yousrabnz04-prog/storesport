<?php
/**
 * Shared helper functions.
 */

const ORDER_STATUSES   = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
const PAYMENT_STATUSES = ['pending', 'paid', 'failed'];
const IMAGE_MIMES      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
const ATTACHMENT_MIMES = IMAGE_MIMES + ['application/pdf' => 'pdf', 'text/plain' => 'txt'];

// ==================== OUTPUT / REQUEST ====================

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function postString(string $key, int $max = 255): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
}

function postInt(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function wantsJson(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function pageParam(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

// ==================== FLASH MESSAGES ====================

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function takeFlashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ==================== CSRF ====================

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        if (wantsJson()) {
            jsonResponse(['ok' => false, 'message' => 'Session expired. Please reload the page.'], 419);
        }
        http_response_code(419);
        exit('Invalid or expired form token. Go back, reload the page and try again.');
    }
}

// ==================== AUTHENTICATION ====================

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_type']);
}

function isUser(): bool
{
    return isLoggedIn() && $_SESSION['user_type'] === 'user';
}

function isAdmin(): bool
{
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

/** Fresh admin record from the database (permissions are never trusted from the session). */
function currentAdmin(): ?array
{
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    if (!isAdmin()) {
        return $admin = null;
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $admin = ($stmt->fetch() ?: null);
}

function isOwner(): bool
{
    $admin = currentAdmin();
    return $admin !== null && $admin['role'] === 'owner';
}

function canManageAdmins(): bool
{
    $admin = currentAdmin();
    return $admin !== null && ($admin['role'] === 'owner' || (int) $admin['can_manage_admins'] === 1);
}

function logoutSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function requireUser(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
    if (!isUser()) {
        redirect('admin/');
    }
}

function requireAdmin(): void
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    if (!isAdmin()) {
        redirect('index.php');
    }
    if (currentAdmin() === null) { // account removed while logged in
        logoutSession();
        header('Location: ' . url('login.php'));
        exit;
    }
}

function requireAdminManager(): void
{
    requireAdmin();
    if (!canManageAdmins()) {
        flash('danger', 'You do not have permission to manage administrators.');
        redirect('admin/');
    }
}

/** Return a safe local redirect target stored before login, or the default page. */
function consumeRedirectTarget(string $default): string
{
    $target = $_SESSION['redirect_after_login'] ?? '';
    unset($_SESSION['redirect_after_login']);
    $prefix = BASE_PATH . '/';
    if (is_string($target) && $target !== '' && str_starts_with($target, $prefix)
        && !str_starts_with($target, '//') && !str_contains($target, '\\')) {
        return $target;
    }
    return url($default);
}

// ==================== FORMATTING ====================

function formatPrice($price): string
{
    return '$' . number_format((float) $price, 2);
}

function statusBadge(string $status): string
{
    $map = [
        'pending' => 'warning text-dark', 'processing' => 'info text-dark', 'shipped' => 'primary',
        'delivered' => 'success', 'cancelled' => 'secondary', 'paid' => 'success', 'failed' => 'danger',
        'active' => 'success', 'banned' => 'danger', 'inactive' => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function slugify(string $text): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($text))), '-');
    return $slug !== '' ? $slug : 'item';
}

function uniqueSlug(string $table, string $name, int $ignoreId = 0): string
{
    if (!in_array($table, ['products', 'categories'], true)) {
        throw new InvalidArgumentException('Unsupported table.');
    }
    global $pdo;
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND id <> ?");
    while (true) {
        $stmt->execute([$slug, $ignoreId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

function renderPagination(int $page, int $pages, array $query = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $base = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $item = function (int $p, string $label, bool $active = false, bool $disabled = false) use ($base, $query) {
        $href = $base . '?' . http_build_query($query + ['page' => $p]);
        $class = 'page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '');
        return '<li class="' . $class . '"><a class="page-link" href="' . e($href) . '">' . $label . '</a></li>';
    };
    $html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center">';
    $html .= $item(max(1, $page - 1), 'Previous', false, $page <= 1);
    for ($i = 1; $i <= $pages; $i++) {
        $html .= $item($i, (string) $i, $i === $page);
    }
    $html .= $item(min($pages, $page + 1), 'Next', false, $page >= $pages);
    return $html . '</ul></nav>';
}

// ==================== FILE UPLOADS ====================

/**
 * Validate (real MIME type, size) and store an uploaded file under a random name.
 *
 * @param array $allowedMimes map of MIME type => extension
 */
function saveUpload(array $file, string $dir, array $allowedMimes, int $maxBytes = 5242880): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The file could not be uploaded.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'The file is too large (maximum ' . round($maxBytes / 1048576) . ' MB).'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowedMimes[$mime])) {
        return ['ok' => false, 'error' => 'This file type is not allowed.'];
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'The image is invalid.'];
    }
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'The upload folder is not writable.'];
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        return ['ok' => false, 'error' => 'The file could not be saved.'];
    }
    return ['ok' => true, 'filename' => $filename];
}

function deleteUploadedFile(string $dir, ?string $filename): void
{
    if ($filename) {
        $path = $dir . '/' . basename($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

// ==================== CATALOGUE ====================

function productImage(array $product): string
{
    if (!empty($product['image']) && is_file(PRODUCTS_DIR . '/' . basename($product['image']))) {
        return url('uploads/products/' . rawurlencode(basename($product['image'])));
    }
    return url('assets/img/placeholder.svg');
}

function getAllCategories(): array
{
    global $pdo;
    return $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
}

// ==================== CART ====================

function cartCount(): int
{
    if (!isUser()) {
        return 0;
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}

function cartItems(int $userId): array
{
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.slug, p.price, p.stock, p.image, p.status
         FROM cart c JOIN products p ON p.id = c.product_id
         WHERE c.user_id = ? ORDER BY c.id ASC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ==================== ORDERS ====================

/** Cancel an order and put its items back in stock. Returns false if it was already cancelled. */
function cancelOrderAndRestock(int $orderId): bool
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT order_status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $status = $stmt->fetchColumn();
        if ($status === false || $status === 'cancelled') {
            $pdo->rollBack();
            return false;
        }
        $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
        $items->execute([$orderId]);
        $restock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        foreach ($items->fetchAll() as $item) {
            $restock->execute([$item['quantity'], $item['product_id']]);
        }
        $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?")->execute([$orderId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ==================== MESSAGING ====================

function unreadCount(): int
{
    if (!isLoggedIn()) {
        return 0;
    }
    global $pdo;
    if (isAdmin()) {
        return (int) $pdo->query("SELECT COUNT(*) FROM messages WHERE sender_type = 'user' AND is_read = 0")->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND sender_type = 'admin' AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}

/** Store a message in a customer's support thread (optionally with one attachment). */
function postThreadMessage(int $userId, string $senderType, ?int $adminId, string $text, ?array $file): array
{
    global $pdo;
    $filePath = null;
    $fileName = null;
    if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = saveUpload($file, MESSAGES_DIR, ATTACHMENT_MIMES);
        if (!$upload['ok']) {
            return ['ok' => false, 'error' => $upload['error']];
        }
        $filePath = $upload['filename'];
        $fileName = mb_substr(basename((string) $file['name']), 0, 200);
    }
    if ($text === '' && $filePath === null) {
        return ['ok' => false, 'error' => 'Please write a message or attach a file.'];
    }
    $stmt = $pdo->prepare(
        'INSERT INTO messages (user_id, sender_type, admin_id, message, file_path, file_name) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $senderType, $adminId, $text, $filePath, $fileName]);
    return ['ok' => true];
}

// ==================== ADMIN AUDIT LOG ====================

function logAdmin(string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $_SESSION['user_id'] ?? null, $action, $targetType, $targetId,
        $details !== null ? mb_substr($details, 0, 500) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
