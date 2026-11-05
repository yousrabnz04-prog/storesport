<?php
/** Product card. Expects $p (product row joined with category_name). */
$productUrl = url('product.php?slug=' . rawurlencode($p['slug']));
?>
<div class="col-6 col-md-4 col-lg-3">
    <div class="card product-card h-100">
        <a href="<?= e($productUrl) ?>">
            <img src="<?= e(productImage($p)) ?>" class="card-img-top product-img" alt="<?= e($p['name']) ?>" loading="lazy">
        </a>
        <div class="card-body d-flex flex-column">
            <?php if (!empty($p['category_name'])): ?><small class="text-muted"><?= e($p['category_name']) ?></small><?php endif; ?>
            <h6 class="card-title mt-1"><a href="<?= e($productUrl) ?>" class="text-dark text-decoration-none"><?= e($p['name']) ?></a></h6>
            <div class="mt-auto">
                <span class="price"><?= e(formatPrice($p['price'])) ?></span>
                <?php if (!empty($p['compare_price']) && $p['compare_price'] > $p['price']): ?>
                    <small class="text-muted text-decoration-line-through ms-1"><?= e(formatPrice($p['compare_price'])) ?></small>
                <?php endif; ?>
                <?php if ((int) $p['stock'] > 0): ?>
                    <form method="post" action="<?= e(url('cart.php')) ?>" class="js-add-to-cart mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-cart-plus"></i> Add to cart</button>
                    </form>
                <?php else: ?>
                    <div class="mt-2"><span class="badge bg-secondary">Out of stock</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
