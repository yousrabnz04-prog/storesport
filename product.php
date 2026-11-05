<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p
     JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.status = 'active'"
);
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container my-5 text-center"><h1 class="h3">Product not found</h1><p class="text-muted">This product does not exist or is no longer available.</p><a href="' . e(url('shop.php')) . '" class="btn btn-primary">Back to the shop</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$related = $pdo->prepare(
    "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.category_id = ? AND p.id <> ? AND p.status = 'active' ORDER BY p.created_at DESC LIMIT 4"
);
$related->execute([$product['category_id'], $product['id']]);
$relatedProducts = $related->fetchAll();

$stock = (int) $product['stock'];
$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(url('shop.php')) ?>">Shop</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('shop.php?category=' . rawurlencode($product['category_slug']))) ?>"><?= e($product['category_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-md-6">
            <img src="<?= e(productImage($product)) ?>" alt="<?= e($product['name']) ?>" class="img-fluid rounded shadow-sm w-100">
        </div>
        <div class="col-md-6">
            <h1 class="h2"><?= e($product['name']) ?></h1>
            <div class="mb-3">
                <span class="price fs-3"><?= e(formatPrice($product['price'])) ?></span>
                <?php if (!empty($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                    <span class="text-muted text-decoration-line-through ms-2"><?= e(formatPrice($product['compare_price'])) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($product['short_description'])): ?><p class="lead fs-6"><?= e($product['short_description']) ?></p><?php endif; ?>
            <p class="mb-4"><?= nl2br(e($product['description'])) ?></p>

            <?php if ($stock > 0): ?>
                <p class="text-success mb-3"><i class="fas fa-check-circle"></i> <?= $stock <= 5 ? 'Only ' . $stock . ' left in stock' : 'In stock' ?></p>
                <form method="post" action="<?= e(url('cart.php')) ?>" class="js-add-to-cart d-flex gap-2" style="max-width:320px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <input type="number" name="quantity" value="1" min="1" max="<?= $stock ?>" class="form-control" style="width:90px" aria-label="Quantity">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-cart-plus"></i> Add to cart</button>
                </form>
            <?php else: ?>
                <p class="text-danger"><i class="fas fa-times-circle"></i> Out of stock</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($relatedProducts): ?>
        <h2 class="h4 mt-5 mb-3">You may also like</h2>
        <div class="row">
            <?php foreach ($relatedProducts as $p) { require __DIR__ . '/includes/product-card.php'; } ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
