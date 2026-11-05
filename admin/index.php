<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$stats = [
    'customers' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'products'  => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'orders'    => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'revenue'   => (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status <> 'cancelled'")->fetchColumn(),
    'pending'   => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn(),
    'low_stock' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock <= 5")->fetchColumn(),
];

$recent = $pdo->query(
    'SELECT o.id, o.order_number, o.total_amount, o.order_status, o.created_at, u.username
     FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 8'
)->fetchAll();

$lowStock = $pdo->query("SELECT id, name, stock FROM products WHERE status = 'active' AND stock <= 5 ORDER BY stock ASC LIMIT 6")->fetchAll();

$pageTitle = 'Dashboard';
$adminPage = 'dashboard';
require __DIR__ . '/../includes/admin_header.php';

$cards = [
    ['Customers', $stats['customers'], 'fa-users', 'primary'],
    ['Products', $stats['products'], 'fa-box', 'success'],
    ['Orders', $stats['orders'], 'fa-receipt', 'info'],
    ['Revenue', formatPrice($stats['revenue']), 'fa-dollar-sign', 'warning'],
];
?>
<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-xl-3">
            <div class="stats-card">
                <div class="text-muted small"><?= e($label) ?></div>
                <div class="fs-3 fw-bold"><?= e($value) ?></div>
                <i class="fas <?= e($icon) ?> stats-icon text-<?= e($color) ?>"></i>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Recent orders</strong>
                <a href="<?= e(url('admin/orders.php')) ?>" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $o): ?>
                            <tr>
                                <td><a href="<?= e(url('admin/orders.php?id=' . (int) $o['id'])) ?>"><?= e($o['order_number']) ?></a></td>
                                <td><?= e($o['username']) ?></td>
                                <td><?= e(formatPrice($o['total_amount'])) ?></td>
                                <td><?= statusBadge($o['order_status']) ?></td>
                                <td><?= e(date('M j, H:i', strtotime($o['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <strong>To do</strong>
                <ul class="list-unstyled mb-0 mt-2">
                    <li><a href="<?= e(url('admin/orders.php?status=pending')) ?>"><?= $stats['pending'] ?> pending order<?= $stats['pending'] === 1 ? '' : 's' ?></a></li>
                    <li><a href="<?= e(url('admin/messages.php')) ?>"><?= unreadCount() ?> unread message<?= unreadCount() === 1 ? '' : 's' ?></a></li>
                </ul>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Low stock</strong></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($lowStock as $p): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="<?= e(url('admin/products.php?edit=' . (int) $p['id'])) ?>"><?= e($p['name']) ?></a>
                        <span class="badge bg-<?= (int) $p['stock'] === 0 ? 'danger' : 'warning text-dark' ?>"><?= (int) $p['stock'] ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$lowStock): ?><li class="list-group-item text-muted">All products are well stocked.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
