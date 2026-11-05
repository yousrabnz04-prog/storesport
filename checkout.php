<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireUser();

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT phone, address FROM users WHERE id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$items = array_values(array_filter(
    cartItems($userId),
    fn($i) => $i['status'] === 'active' && (int) $i['stock'] > 0
));
if (!$items) {
    flash('info', 'Your cart is empty.');
    redirect('cart.php');
}

$errors = [];
$form = [
    'address' => (string) ($profile['address'] ?? ''),
    'phone'   => (string) ($profile['phone'] ?? ''),
    'notes'   => '',
];

if (isPost()) {
    verify_csrf();
    $form['address'] = postString('address', 1000);
    $form['phone']   = postString('phone', 30);
    $form['notes']   = postString('notes', 1000);

    if (mb_strlen($form['address']) < 10) {
        $errors[] = 'Please enter a complete shipping address.';
    }
    if (!preg_match('/^[0-9+().\s-]{6,30}$/', $form['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the cart's products so stock cannot be oversold by concurrent orders.
            $cart = $pdo->prepare(
                "SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.price, p.stock
                 FROM cart c JOIN products p ON p.id = c.product_id
                 WHERE c.user_id = ? AND p.status = 'active' FOR UPDATE"
            );
            $cart->execute([$userId]);
            $lines = $cart->fetchAll();

            $total = 0.0;
            foreach ($lines as $line) {
                if ((int) $line['quantity'] > (int) $line['stock']) {
                    throw new RuntimeException('Not enough stock for "' . $line['name'] . '" (only ' . (int) $line['stock'] . ' left). Please update your cart.');
                }
                $total += $line['price'] * $line['quantity'];
            }
            if (!$lines) {
                throw new RuntimeException('Your cart is empty.');
            }

            $orderNumber = 'SS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $pdo->prepare(
                'INSERT INTO orders (order_number, user_id, total_amount, shipping_address, phone, notes) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$orderNumber, $userId, $total, $form['address'], $form['phone'], $form['notes'] ?: null]);
            $orderId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $decrement = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($lines as $line) {
                $insertItem->execute([$orderId, $line['product_id'], $line['name'], $line['price'], $line['quantity'], $line['price'] * $line['quantity']]);
                $decrement->execute([$line['quantity'], $line['product_id']]);
            }
            $pdo->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$userId]);

            $pdo->commit();
            redirect('order-confirmation.php?id=' . $orderId . '&placed=1');
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout failed: ' . $e->getMessage());
            $errors[] = 'We could not place your order. Please try again.';
        }
    }
}

$total = 0.0;
foreach ($items as $i) {
    $total += $i['price'] * min($i['quantity'], $i['stock']);
}

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <h1 class="h3 mb-4">Checkout</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <form method="post" class="card shadow-sm"><div class="card-body">
                <?= csrf_field() ?>
                <h2 class="h5 mb-3">Delivery details</h2>
                <div class="mb-3">
                    <label for="address" class="form-label">Shipping address</label>
                    <textarea id="address" name="address" rows="3" class="form-control" required><?= e($form['address']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($form['phone']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="notes" class="form-label">Order notes <span class="text-muted small">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="2" class="form-control"><?= e($form['notes']) ?></textarea>
                </div>
                <h2 class="h5 mb-2">Payment</h2>
                <p class="mb-4"><i class="fas fa-money-bill-wave text-success"></i> Cash on delivery - pay when your order arrives.</p>
                <button type="submit" class="btn btn-primary w-100 btn-lg">Place order</button>
            </div></form>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm summary-card"><div class="card-body">
                <h2 class="h5 mb-3">Your order</h2>
                <?php foreach ($items as $i): ?>
                    <div class="d-flex justify-content-between small mb-2">
                        <span><?= e($i['name']) ?> &times; <?= (int) min($i['quantity'], $i['stock']) ?></span>
                        <span><?= e(formatPrice($i['price'] * min($i['quantity'], $i['stock']))) ?></span>
                    </div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between"><strong>Total</strong><strong><?= e(formatPrice($total)) ?></strong></div>
            </div></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
