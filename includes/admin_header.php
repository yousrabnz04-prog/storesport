<?php
/**
 * Admin layout - opening part.
 * Set $pageTitle and $adminPage (nav key) before including this file.
 */
$pageTitle   = $pageTitle ?? 'Admin';
$adminPage   = $adminPage ?? '';
$unreadCount = unreadCount();
$navItems = [
    'dashboard'  => ['admin/index.php', 'fa-gauge', 'Dashboard'],
    'products'   => ['admin/products.php', 'fa-box', 'Products'],
    'categories' => ['admin/categories.php', 'fa-tags', 'Categories'],
    'orders'     => ['admin/orders.php', 'fa-receipt', 'Orders'],
    'users'      => ['admin/users.php', 'fa-users', 'Customers'],
    'messages'   => ['admin/messages.php', 'fa-envelope', 'Messages'],
];
if (canManageAdmins()) {
    $navItems['admins'] = ['admin/admins.php', 'fa-user-shield', 'Administrators'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?> Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="admin-body" data-base-url="<?= e(BASE_PATH) ?>" data-csrf="<?= e(csrf_token()) ?>">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 admin-sidebar p-0">
            <div class="p-3 border-bottom border-secondary">
                <a href="<?= e(url('admin/')) ?>" class="text-white text-decoration-none fw-bold"><i class="fas fa-dumbbell me-1"></i> <?= e(SITE_NAME) ?></a>
                <div class="small text-white-50">Admin panel</div>
            </div>
            <nav class="nav flex-column">
                <?php foreach ($navItems as $key => [$href, $icon, $label]): ?>
                    <a class="nav-link<?= $adminPage === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>">
                        <i class="fas <?= e($icon) ?> fa-fw me-2"></i><?= e($label) ?>
                        <?php if ($key === 'messages' && $unreadCount > 0): ?><span class="badge bg-danger rounded-pill ms-1"><?= $unreadCount ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <a class="nav-link" href="<?= e(url()) ?>"><i class="fas fa-store fa-fw me-2"></i>View store</a>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="px-3 py-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-light btn-sm w-100">Sign out</button>
                </form>
            </nav>
        </aside>
        <main class="col-md-9 col-lg-10 p-4">
            <h1 class="h3 mb-4"><?= e($pageTitle) ?></h1>
            <?php foreach (takeFlashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($f['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
