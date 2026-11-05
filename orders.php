<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireUser();

$userId = (int) $_SESSION['user_id'];

if (isPost() && postString('action') === 'cancel') {
    verify_csrf();
    $orderId = postInt('order_id');
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND order_status = 'pending'");
    $stmt->execute([$orderId, $userId]);
    if ($stmt->fetch() && cancelOrderAndRestock($orderId)) {
        flash('success', 'Your order has been cancelled.');
    } else {
        flash('danger', 'This order can no longer be cancelled.');
    }
    redirect('orders.php');
}

$stmt = $pdo->prepare(
    'SELECT o.*, (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC'
);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$pageTitle = 'My orders';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <h1 class="h3 mb-4">My orders</h1>
    <?php if (!$orders): ?>
        <div class="text-center py-5 bg-white rounded shadow-sm">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <h2 class="h5">No orders yet</h2>
            <a href="<?= e(url('shop.php')) ?>" class="btn btn-primary mt-2">Start shopping</a>
        </div>
    <?php else: ?>
        <div class="table-responsive bg-white rounded shadow-sm">
            <table class="table align-middle mb-0">
                <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><a href="<?= e(url('order-confirmation.php?id=' . (int) $o['id'])) ?>"><?= e($o['order_number']) ?></a></td>
                            <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
                            <td><?= (int) $o['item_count'] ?></td>
                            <td><?= e(formatPrice($o['total_amount'])) ?></td>
                            <td><?= statusBadge($o['order_status']) ?></td>
                            <td class="text-end">
                                <?php if ($o['order_status'] === 'pending'): ?>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Cancel this order?">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
