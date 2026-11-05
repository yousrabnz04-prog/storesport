<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!isUser()) {
    if (wantsJson()) {
        jsonResponse(['ok' => false, 'message' => 'Please sign in to add items to your cart.', 'login' => url('login.php')], 401);
    }
    requireUser();
}

$userId = (int) $_SESSION['user_id'];

if (isPost()) {
    verify_csrf();
    $action = postString('action');
    $message = '';
    $ok = true;

    if ($action === 'add') {
        $productId = postInt('product_id');
        $qty = max(1, postInt('quantity'));

        $stmt = $pdo->prepare("SELECT id, name, stock FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product || (int) $product['stock'] < 1) {
            $ok = false;
            $message = 'This product is not available.';
        } else {
            $existing = $pdo->prepare('SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?');
            $existing->execute([$userId, $productId]);
            $row = $existing->fetch();
            $newQty = min((int) $product['stock'], $qty + ($row ? (int) $row['quantity'] : 0));

            if ($row) {
                $pdo->prepare('UPDATE cart SET quantity = ? WHERE id = ?')->execute([$newQty, $row['id']]);
            } else {
                $pdo->prepare('INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)')->execute([$userId, $productId, $newQty]);
            }
            $message = '"' . $product['name'] . '" was added to your cart.';
        }
    } elseif ($action === 'update') {
        $quantities = $_POST['quantity'] ?? [];
        if (is_array($quantities)) {
            foreach ($quantities as $cartId => $qty) {
                $cartId = (int) $cartId;
                $qty = (int) $qty;
                if ($qty <= 0) {
                    $pdo->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?')->execute([$cartId, $userId]);
                } else {
                    $pdo->prepare(
                        'UPDATE cart c JOIN products p ON p.id = c.product_id
                         SET c.quantity = LEAST(?, GREATEST(p.stock, 1)) WHERE c.id = ? AND c.user_id = ?'
                    )->execute([$qty, $cartId, $userId]);
                }
            }
        }
        $message = 'Your cart has been updated.';
    } elseif ($action === 'remove') {
        $pdo->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?')->execute([postInt('cart_id'), $userId]);
        $message = 'Item removed from your cart.';
    } else {
        $ok = false;
        $message = 'Unknown action.';
    }

    if (wantsJson()) {
        jsonResponse(['ok' => $ok, 'message' => $message, 'count' => cartCount()], $ok ? 200 : 422);
    }
    flash($ok ? 'success' : 'danger', $message);
    $back = $action === 'add' ? ($_SERVER['HTTP_REFERER'] ?? '') : '';
    if ($back !== '' && parse_url($back, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? null)) {
        header('Location: ' . $back);
        exit;
    }
    redirect('cart.php');
}

$items = cartItems($userId);
$total = 0.0;
$hasUnavailable = false;
foreach ($items as $item) {
    if ($item['status'] !== 'active' || (int) $item['stock'] < 1) {
        $hasUnavailable = true;
    } else {
        $total += $item['price'] * min($item['quantity'], $item['stock']);
    }
}

$pageTitle = 'Shopping cart';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <h1 class="h3 mb-4"><i class="fas fa-shopping-cart"></i> Shopping cart</h1>
    <div class="row g-4">
        <div class="col-lg-8">
            <?php if (!$items): ?>
                <div class="text-center py-5 bg-white rounded shadow-sm">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-3 d-block"></i>
                    <h2 class="h5">Your cart is empty</h2>
                    <p class="text-muted">Looks like you haven't added anything yet.</p>
                    <a href="<?= e(url('shop.php')) ?>" class="btn btn-primary">Continue shopping</a>
                </div>
            <?php else: ?>
                <form method="post" class="bg-white rounded shadow-sm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <?php foreach ($items as $item):
                        $available = $item['status'] === 'active' && (int) $item['stock'] > 0; ?>
                        <div class="cart-item row align-items-center g-2 mx-0">
                            <div class="col-3 col-md-2">
                                <img src="<?= e(productImage($item)) ?>" class="cart-img" alt="<?= e($item['name']) ?>">
                            </div>
                            <div class="col-9 col-md-4">
                                <a class="fw-semibold text-dark text-decoration-none" href="<?= e(url('product.php?slug=' . rawurlencode($item['slug']))) ?>"><?= e($item['name']) ?></a>
                                <?php if (!$available): ?><div class="small text-danger">No longer available</div><?php endif; ?>
                            </div>
                            <div class="col-4 col-md-2"><?= e(formatPrice($item['price'])) ?></div>
                            <div class="col-4 col-md-2">
                                <input type="number" name="quantity[<?= (int) $item['cart_id'] ?>]" value="<?= (int) $item['quantity'] ?>" min="0" max="<?= max(1, (int) $item['stock']) ?>" class="form-control form-control-sm" aria-label="Quantity for <?= e($item['name']) ?>">
                            </div>
                            <div class="col-4 col-md-2 text-end">
                                <span class="fw-bold"><?= e(formatPrice($item['price'] * $item['quantity'])) ?></span>
                                <button type="submit" form="remove-<?= (int) $item['cart_id'] ?>" class="btn btn-link text-danger p-0 ms-2" title="Remove" aria-label="Remove <?= e($item['name']) ?>" data-confirm="Remove this item from your cart?"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="p-3 border-top d-flex justify-content-between">
                        <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-sync-alt"></i> Update cart</button>
                    </div>
                </form>
                <?php foreach ($items as $item): ?>
                    <form method="post" id="remove-<?= (int) $item['cart_id'] ?>" class="d-none">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="summary-card card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Order summary</h2>
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span><?= e(formatPrice($total)) ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Shipping</span><span class="text-success">Free</span></div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3"><strong>Total</strong><strong class="fs-4"><?= e(formatPrice($total)) ?></strong></div>
                    <?php if ($items): ?>
                        <?php if ($hasUnavailable): ?><div class="alert alert-warning small">Some items are unavailable and will be skipped at checkout.</div><?php endif; ?>
                        <a href="<?= e(url('checkout.php')) ?>" class="btn btn-primary w-100"><i class="fas fa-lock"></i> Proceed to checkout</a>
                    <?php endif; ?>
                    <a href="<?= e(url('shop.php')) ?>" class="btn btn-outline-secondary w-100 mt-2">Continue shopping</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
