<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireUser();

$orderId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('danger', 'Order not found.');
    redirect('orders.php');
}

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$justPlaced = isset($_GET['placed']);
$pageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <?php if ($justPlaced): ?>
        <div class="text-center mb-5">
            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
            <h1 class="h2">Thank you for your order!</h1>
            <p class="text-muted mb-0">Order <strong><?= e($order['order_number']) ?></strong></p>
        </div>
    <?php else: ?>
        <h1 class="h3 mb-4">Order <?= e($order['order_number']) ?></h1>
    <?php endif; ?>

    <div class="row justify-content-center"><div class="col-lg-8">
        <div class="card shadow-sm mb-4"><div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Placed on</strong><br><?= e(date('F j, Y H:i', strtotime($order['created_at']))) ?></p>
                    <p><strong>Status</strong><br><?= statusBadge($order['order_status']) ?> <?= statusBadge($order['payment_status']) ?></p>
                    <p class="mb-0"><strong>Payment</strong><br>Cash on delivery</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Shipping address</strong><br><?= nl2br(e($order['shipping_address'])) ?></p>
                    <p class="mb-0"><strong>Phone</strong><br><?= e($order['phone']) ?></p>
                </div>
            </div>
        </div></div>

        <div class="card shadow-sm"><div class="card-body">
            <h2 class="h5 mb-3">Items</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['product_name']) ?></td>
                                <td class="text-end"><?= (int) $item['quantity'] ?></td>
                                <td class="text-end"><?= e(formatPrice($item['product_price'])) ?></td>
                                <td class="text-end"><?= e(formatPrice($item['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= e(formatPrice($order['total_amount'])) ?></th></tr></tfoot>
                </table>
            </div>
        </div></div>

        <div class="text-center mt-4">
            <a href="<?= e(url('shop.php')) ?>" class="btn btn-primary">Continue shopping</a>
            <a href="<?= e(url('orders.php')) ?>" class="btn btn-outline-secondary">My orders</a>
        </div>
    </div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
