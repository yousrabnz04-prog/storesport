</main>

<footer class="site-footer bg-dark text-white-50 pt-5 pb-3 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-5 mb-4">
                <h5 class="text-white mb-3"><i class="fas fa-dumbbell me-1"></i> <?= e(SITE_NAME) ?></h5>
                <?php if (SITE_TAGLINE !== ''): ?><p class="small"><?= e(SITE_TAGLINE) ?></p><?php endif; ?>
            </div>
            <div class="col-md-3 mb-4">
                <h6 class="text-white mb-3">Shop</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?= e(url()) ?>" class="text-white-50 text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="<?= e(url('shop.php')) ?>" class="text-white-50 text-decoration-none">All products</a></li>
                    <?php if (isUser()): ?>
                        <li class="mb-2"><a href="<?= e(url('messages.php')) ?>" class="text-white-50 text-decoration-none">Contact support</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php if (CONTACT_EMAIL !== '' || CONTACT_PHONE !== '' || CONTACT_ADDRESS !== ''): ?>
                <div class="col-md-4 mb-4">
                    <h6 class="text-white mb-3">Contact</h6>
                    <ul class="list-unstyled small">
                        <?php if (CONTACT_EMAIL !== ''): ?><li class="mb-2"><i class="fas fa-envelope me-2"></i><?= e(CONTACT_EMAIL) ?></li><?php endif; ?>
                        <?php if (CONTACT_PHONE !== ''): ?><li class="mb-2"><i class="fas fa-phone me-2"></i><?= e(CONTACT_PHONE) ?></li><?php endif; ?>
                        <?php if (CONTACT_ADDRESS !== ''): ?><li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i><?= e(CONTACT_ADDRESS) ?></li><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <hr class="border-secondary">
        <p class="text-center small mb-0">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>
