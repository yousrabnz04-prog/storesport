<?php
/**
 * Storefront layout - opening part.
 * Set $pageTitle before including this file.
 */
$pageTitle   = $pageTitle ?? '';
$fullTitle   = $pageTitle !== '' ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME . ($pageTitle === '' && SITE_TAGLINE !== '' ? ' - ' . SITE_TAGLINE : '');
$cartCount   = cartCount();
$unreadCount = unreadCount();
$current     = basename($_SERVER['SCRIPT_NAME']);
$active      = fn(string $file): string => $current === $file ? ' active' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100" data-base-url="<?= e(BASE_PATH) ?>" data-csrf="<?= e(csrf_token()) ?>">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url()) ?>"><i class="fas fa-dumbbell me-1"></i> <?= e(SITE_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link<?= $active('index.php') ?>" href="<?= e(url()) ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link<?= $active('shop.php') ?>" href="<?= e(url('shop.php')) ?>">Shop</a></li>
                <?php if (isUser()): ?>
                    <li class="nav-item"><a class="nav-link<?= $active('orders.php') ?>" href="<?= e(url('orders.php')) ?>">My orders</a></li>
                    <li class="nav-item">
                        <a class="nav-link<?= $active('messages.php') ?>" href="<?= e(url('messages.php')) ?>">
                            Support
                            <?php if ($unreadCount > 0): ?><span class="badge bg-danger rounded-pill"><?= $unreadCount ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <form class="d-flex me-lg-3 my-2 my-lg-0" action="<?= e(url('shop.php')) ?>" method="get" role="search">
                <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Search products" aria-label="Search products" value="<?= e(is_string($_GET['q'] ?? null) ? $_GET['q'] : '') ?>">
                <button class="btn btn-outline-light btn-sm" type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
            </form>

            <ul class="navbar-nav align-items-lg-center">
                <?php if (isUser()): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active('cart.php') ?>" href="<?= e(url('cart.php')) ?>">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <span class="badge bg-danger rounded-pill js-cart-count<?= $cartCount > 0 ? '' : ' d-none' ?>"><?= $cartCount ?></span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('admin/')) ?>"><i class="fas fa-shield-alt"></i> Admin panel</a>
                    </li>
                <?php endif; ?>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?= e($_SESSION['username'] ?? 'Account') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (isUser()): ?>
                                <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>">My profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <form method="post" action="<?= e(url('logout.php')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item text-danger">Sign out</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link<?= $active('login.php') ?>" href="<?= e(url('login.php')) ?>">Sign in</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-light btn-sm" href="<?= e(url('register.php')) ?>">Create account</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1">
<?php $flashes = takeFlashes(); if ($flashes): ?>
    <div class="container mt-3">
        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
