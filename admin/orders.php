<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

const ORDERS_PER_PAGE = 20;

if (isPost()) {
    verify_csrf();
    $orderId = postInt('order_id');
    $status  = postString('order_status', 20);
    $payment = postString('payment_status', 20);

    $stmt = $pdo->prepare('SELECT order_status, payment_status FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $current = $stmt->fetch();

    if (!$current || !in_array($status, ORDER_STATUSES, true) || !in_array($payment, PAYMENT_STATUSES, true)) {
        flash('danger', 'Invalid order update.');
    } elseif ($current['order_status'] === 'cancelled' && $status !== 'cancelled') {
        flash('danger', 'A cancelled order cannot be reopened. Ask the customer to place a new order.');
    } else {
        if ($status === 'cancelled') {
            cancelOrderAndRestock($orderId); // also puts the items back in stock
        } else {
            $pdo->prepare('UPDATE orders SET order_status = ? WHERE id = ?')->execute([$status, $orderId]);
        }
        $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute([$payment, $orderId]);
        logAdmin('update_order', 'order', $orderId, "status=$status, payment=$payment");
        flash('success', 'Order updated.');
    }
    redirect('admin/orders.php?id=' . $orderId);
}

$orderId = (int) ($_GET['id'] ?? 0);

if ($orderId > 0) {
    $stmt = $pdo->prepare('SELECT o.*, u.username, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        flash('danger', 'Order not found.');
        redirect('admin/orders.php');
    }
    $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();
    $pageTitle = 'Order ' . $order['order_number'];
} else {
    $statusFilter = is_string($_GET['status'] ?? null) && in_array($_GET['status'], ORDER_STATUSES, true) ? $_GET['status'] : '';
    $where = $statusFilter !== '' ? 'WHERE o.order_status = ?' : '';
    $params = $statusFilter !== '' ? [$statusFilter] : [];

    $count = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
    $count->execute($params);
    $pages = max(1, (int) ceil((int) $count->fetchColumn() / ORDERS_PER_PAGE));
    $page = min(pageParam(), $pages);

    $stmt = $pdo->prepare(
        "SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id $where
         ORDER BY o.id DESC LIMIT " . ORDERS_PER_PAGE . ' OFFSET ' . (($page - 1) * ORDERS_PER_PAGE)
    );
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    $pageTitle = 'Orders';
}

$adminPage = 'orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($orderId > 0): ?>
    <a href="<?= e(url('admin/orders.php')) ?>" class="btn btn-link px-0 mb-3">&larr; All orders</a>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4"><div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Customer</strong><br><?= e($order['username']) ?><br><span class="text-muted"><?= e($order['email']) ?></span></p>
                        <p class="mb-0"><strong>Placed on</strong><br><?= e(date('F j, Y H:i', strtotime($order['created_at']))) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Shipping address</strong><br><?= nl2br(e($order['shipping_address'])) ?></p>
                        <p class="mb-0"><strong>Phone</strong><br><?= e($order['phone']) ?></p>
                    </div>
                </div>
                <?php if (!empty($order['notes'])): ?><hr><p class="mb-0"><strong>Customer notes</strong><br><?= nl2br(e($order['notes'])) ?></p><?php endif; ?>
            </div></div>
            <div class="card shadow-sm"><div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $i): ?>
                            <tr><td><?= e($i['product_name']) ?></td><td class="text-end"><?= (int) $i['quantity'] ?></td><td class="text-end"><?= e(formatPrice($i['product_price'])) ?></td><td class="text-end"><?= e(formatPrice($i['total'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= e(formatPrice($order['total_amount'])) ?></th></tr></tfoot>
                </table>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm"><div class="card-body">
                <h2 class="h5 mb-3">Update order</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <div class="mb-3">
                        <label for="order_status" class="form-label">Order status</label>
                        <select id="order_status" name="order_status" class="form-select">
                            <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $order['order_status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="payment_status" class="form-label">Payment status</label>
                        <select id="payment_status" name="payment_status" class="form-select">
                            <?php foreach (PAYMENT_STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $order['payment_status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                    <div class="form-text">Setting an order to "Cancelled" puts its items back in stock.</div>
                </form>
            </div></div>
        </div>
    </div>
<?php else: ?>
    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="<?= e(url('admin/orders.php')) ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-dark' : 'btn-outline-dark' ?>">All</a>
        <?php foreach (ORDER_STATUSES as $s): ?>
            <a href="<?= e(url('admin/orders.php?status=' . $s)) ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-dark' : 'btn-outline-dark' ?>"><?= e(ucfirst($s)) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="card shadow-sm"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="<?= e(url('admin/orders.php?id=' . (int) $o['id'])) ?>"><?= e($o['order_number']) ?></a></td>
                        <td><?= e($o['username']) ?></td>
                        <td><?= e(formatPrice($o['total_amount'])) ?></td>
                        <td><?= statusBadge($o['order_status']) ?></td>
                        <td><?= statusBadge($o['payment_status']) ?></td>
                        <td><?= e(date('M j, Y H:i', strtotime($o['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?><tr><td colspan="6" class="text-center text-muted py-4">No orders found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
    <div class="mt-3"><?= renderPagination($page, $pages, array_filter(['status' => $statusFilter])) ?></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
