<?php
require_once __DIR__ . '/includes/bootstrap.php';

const PER_PAGE = 12;
$sorts = [
    'newest'     => ['Newest', 'p.created_at DESC, p.id DESC'],
    'price_asc'  => ['Price: low to high', 'p.price ASC, p.id ASC'],
    'price_desc' => ['Price: high to low', 'p.price DESC, p.id DESC'],
    'name'       => ['Name (A-Z)', 'p.name ASC'],
];

$categories   = getAllCategories();
$categorySlug = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
$search       = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$sortKey      = is_string($_GET['sort'] ?? null) && isset($sorts[$_GET['sort']]) ? $_GET['sort'] : 'newest';

$where  = ["p.status = 'active'"];
$params = [];
$currentCategory = null;

if ($categorySlug !== '') {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $currentCategory = $cat;
        }
    }
    if ($currentCategory) {
        $where[]  = 'p.category_id = ?';
        $params[] = $currentCategory['id'];
    }
}
if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.short_description LIKE ?)';
    $like     = '%' . addcslashes($search, '%_\\') . '%';
    $params[] = $like;
    $params[] = $like;
}
$whereSql = implode(' AND ', $where);

$count = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / PER_PAGE));
$page  = min(pageParam(), $pages);
$offset = ($page - 1) * PER_PAGE;

$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
     WHERE $whereSql ORDER BY {$sorts[$sortKey][1]} LIMIT " . PER_PAGE . " OFFSET " . (int) $offset
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$query = array_filter(['category' => $currentCategory ? $categorySlug : '', 'q' => $search, 'sort' => $sortKey !== 'newest' ? $sortKey : '']);

$pageTitle = $currentCategory ? $currentCategory['name'] : 'Shop';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
    <div class="row">
        <aside class="col-lg-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Categories</h2>
                    <div class="list-group list-group-flush">
                        <a href="<?= e(url('shop.php' . ($search !== '' ? '?q=' . rawurlencode($search) : ''))) ?>" class="list-group-item list-group-item-action<?= $currentCategory ? '' : ' active' ?>">All products</a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="<?= e(url('shop.php?' . http_build_query(array_filter(['category' => $cat['slug'], 'q' => $search])))) ?>"
                               class="list-group-item list-group-item-action<?= $currentCategory && $currentCategory['id'] === $cat['id'] ? ' active' : '' ?>"><?= e($cat['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </aside>

        <section class="col-lg-9">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h1 class="h4 mb-0"><?= e($pageTitle) ?></h1>
                    <small class="text-muted"><?= $total ?> product<?= $total === 1 ? '' : 's' ?><?= $search !== '' ? ' matching "' . e($search) . '"' : '' ?></small>
                </div>
                <form method="get" class="d-flex gap-2">
                    <?php if ($currentCategory): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
                    <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                    <select name="sort" class="form-select form-select-sm" aria-label="Sort products" data-autosubmit>
                        <?php foreach ($sorts as $key => [$label]): ?>
                            <option value="<?= e($key) ?>"<?= $key === $sortKey ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-sm btn-outline-secondary">Sort</button></noscript>
                </form>
            </div>

            <?php if ($products): ?>
                <div class="row">
                    <?php foreach ($products as $p) { require __DIR__ . '/includes/product-card.php'; } ?>
                </div>
                <?= renderPagination($page, $pages, $query) ?>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded shadow-sm">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h2 class="h5">No products found</h2>
                    <p class="text-muted">Try a different category or search term.</p>
                    <a href="<?= e(url('shop.php')) ?>" class="btn btn-primary">View all products</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
