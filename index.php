<?php
require_once __DIR__ . '/includes/bootstrap.php';

$categories = getAllCategories();

$featured = $pdo->query(
    "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active' AND p.featured = 1 ORDER BY p.created_at DESC LIMIT 4"
)->fetchAll();

$latest = $pdo->query(
    "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active' ORDER BY p.created_at DESC, p.id DESC LIMIT 8"
)->fetchAll();

$pageTitle = '';
require __DIR__ . '/includes/header.php';
?>
<section class="hero text-white text-center py-5">
    <div class="container py-4">
        <h1 class="display-5 fw-bold"><?= e(SITE_NAME) ?></h1>
        <?php if (SITE_TAGLINE !== ''): ?><p class="lead mb-4"><?= e(SITE_TAGLINE) ?></p><?php endif; ?>
        <a href="<?= e(url('shop.php')) ?>" class="btn btn-light btn-lg">Shop now</a>
    </div>
</section>

<div class="container my-5">
    <?php if ($categories): ?>
        <h2 class="h4 mb-3">Shop by category</h2>
        <div class="row g-3 mb-5">
            <?php foreach ($categories as $cat): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="<?= e(url('shop.php?category=' . rawurlencode($cat['slug']))) ?>" class="card category-tile text-center text-decoration-none h-100">
                        <div class="card-body">
                            <i class="fas <?= e($cat['icon'] ?: 'fa-tag') ?> fa-2x mb-2"></i>
                            <div class="fw-semibold text-dark"><?= e($cat['name']) ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($featured): ?>
        <h2 class="h4 mb-3">Featured products</h2>
        <div class="row mb-4">
            <?php foreach ($featured as $p) { require __DIR__ . '/includes/product-card.php'; } ?>
        </div>
    <?php endif; ?>

    <h2 class="h4 mb-3">New arrivals</h2>
    <?php if ($latest): ?>
        <div class="row">
            <?php foreach ($latest as $p) { require __DIR__ . '/includes/product-card.php'; } ?>
        </div>
    <?php else: ?>
        <p class="text-muted">No products have been added yet. Check back soon!</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
