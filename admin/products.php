<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

const ADMIN_PER_PAGE = 15;
$categories = getAllCategories();
$errors = [];
$form = null;

if (isPost()) {
    verify_csrf();
    $action = postString('action');

    if ($action === 'delete') {
        $id = postInt('id');
        $stmt = $pdo->prepare('SELECT name, image FROM products WHERE id = ?');
        $stmt->execute([$id]);
        if ($product = $stmt->fetch()) {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            deleteUploadedFile(PRODUCTS_DIR, $product['image']);
            logAdmin('delete_product', 'product', $id, $product['name']);
            flash('success', 'Product deleted.');
        }
        redirect('admin/products.php');
    }

    if ($action === 'save') {
        $id = postInt('id');
        $form = [
            'id'                => $id,
            'name'              => postString('name', 255),
            'category_id'       => postInt('category_id'),
            'short_description' => postString('short_description', 500),
            'description'       => postString('description', 5000),
            'price'             => postString('price', 20),
            'compare_price'     => postString('compare_price', 20),
            'stock'             => postString('stock', 10),
            'featured'          => isset($_POST['featured']) ? 1 : 0,
            'status'            => postString('status', 10) === 'inactive' ? 'inactive' : 'active',
            'image'             => null,
        ];

        if ($form['name'] === '') {
            $errors[] = 'The product name is required.';
        }
        $catCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $catCheck->execute([$form['category_id']]);
        if ((int) $catCheck->fetchColumn() === 0) {
            $errors[] = 'Please choose a valid category.';
        }
        if (!is_numeric($form['price']) || (float) $form['price'] < 0) {
            $errors[] = 'The price must be a positive number.';
        }
        if ($form['compare_price'] !== '' && (!is_numeric($form['compare_price']) || (float) $form['compare_price'] < 0)) {
            $errors[] = 'The compare-at price must be a positive number.';
        }
        if (!ctype_digit($form['stock'])) {
            $errors[] = 'The stock must be a whole number (0 or more).';
        }
        if ($form['description'] === '') {
            $errors[] = 'The description is required.';
        }

        $oldImage = null;
        if ($id > 0) {
            $old = $pdo->prepare('SELECT image FROM products WHERE id = ?');
            $old->execute([$id]);
            $oldImage = $old->fetchColumn() ?: null;
            $form['image'] = $oldImage;
        }

        $newImage = null;
        if (!$errors && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = saveUpload($_FILES['image'], PRODUCTS_DIR, IMAGE_MIMES);
            if ($upload['ok']) {
                $newImage = $upload['filename'];
            } else {
                $errors[] = $upload['error'];
            }
        }

        if (!$errors) {
            $slug = uniqueSlug('products', $form['name'], $id);
            $image = $newImage ?? $oldImage;
            $values = [
                $form['name'], $slug, $form['category_id'], $form['description'], $form['short_description'] ?: null,
                (float) $form['price'], $form['compare_price'] !== '' ? (float) $form['compare_price'] : null,
                (int) $form['stock'], $image, $form['featured'], $form['status'],
            ];
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE products SET name=?, slug=?, category_id=?, description=?, short_description=?, price=?, compare_price=?, stock=?, image=?, featured=?, status=? WHERE id=?'
                )->execute([...$values, $id]);
                if ($newImage !== null) {
                    deleteUploadedFile(PRODUCTS_DIR, $oldImage);
                }
                logAdmin('update_product', 'product', $id, $form['name']);
                flash('success', 'Product updated.');
            } else {
                $pdo->prepare(
                    'INSERT INTO products (name, slug, category_id, description, short_description, price, compare_price, stock, image, featured, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                )->execute($values);
                logAdmin('create_product', 'product', (int) $pdo->lastInsertId(), $form['name']);
                flash('success', 'Product created.');
            }
            redirect('admin/products.php');
        }
    }
}

$showForm = $form !== null || isset($_GET['new']) || isset($_GET['edit']);
if ($showForm && $form === null) {
    if (isset($_GET['edit'])) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([(int) $_GET['edit']]);
        $form = $stmt->fetch() ?: null;
        if ($form === null) {
            flash('danger', 'Product not found.');
            redirect('admin/products.php');
        }
    } else {
        $form = ['id' => 0, 'name' => '', 'category_id' => 0, 'short_description' => '', 'description' => '', 'price' => '', 'compare_price' => '', 'stock' => '0', 'featured' => 0, 'status' => 'active', 'image' => null];
    }
}

$pageTitle = $showForm ? ($form['id'] ? 'Edit product' : 'New product') : 'Products';
$adminPage = 'products';

if (!$showForm) {
    $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $where = '1=1';
    $params = [];
    if ($search !== '') {
        $where = 'p.name LIKE ?';
        $params[] = '%' . addcslashes($search, '%_\\') . '%';
    }
    $count = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
    $page = min(pageParam(), $pages);
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
         WHERE $where ORDER BY p.id DESC LIMIT " . ADMIN_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PER_PAGE)
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll();
}

require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($showForm): ?>
    <?php if (!$categories): ?>
        <div class="alert alert-warning">Create a <a href="<?= e(url('admin/categories.php')) ?>">category</a> first.</div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="card shadow-sm"><div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="row g-3">
            <div class="col-md-8">
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($form['name']) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-select" required>
                    <option value="">Choose...</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"<?= (int) $form['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="price" class="form-label">Price ($)</label>
                <input type="number" step="0.01" min="0" id="price" name="price" class="form-control" value="<?= e($form['price']) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="compare_price" class="form-label">Compare-at price ($)</label>
                <input type="number" step="0.01" min="0" id="compare_price" name="compare_price" class="form-control" value="<?= e($form['compare_price']) ?>">
            </div>
            <div class="col-md-4">
                <label for="stock" class="form-label">Stock</label>
                <input type="number" min="0" id="stock" name="stock" class="form-control" value="<?= e($form['stock']) ?>" required>
            </div>
            <div class="col-12">
                <label for="short_description" class="form-label">Short description</label>
                <input type="text" id="short_description" name="short_description" class="form-control" maxlength="500" value="<?= e($form['short_description']) ?>">
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="5" class="form-control" required><?= e($form['description']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label for="image_input" class="form-label">Image</label>
                <input type="file" id="image_input" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                <div class="form-text">JPG, PNG, WebP or GIF, up to 5 MB.</div>
            </div>
            <div class="col-md-6">
                <img id="image_preview" src="<?= e(productImage($form)) ?>" alt="Product image" class="rounded border" style="max-height:140px">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active"<?= $form['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                    <option value="inactive"<?= $form['status'] === 'inactive' ? ' selected' : '' ?>>Inactive (hidden)</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="featured" name="featured" value="1"<?= !empty($form['featured']) ? ' checked' : '' ?>>
                    <label for="featured" class="form-check-label">Featured on the home page</label>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save product</button>
            <a href="<?= e(url('admin/products.php')) ?>" class="btn btn-link">Cancel</a>
        </div>
    </div></form>

<?php else: ?>
    <div class="d-flex justify-content-between mb-3 gap-2">
        <form method="get" class="d-flex gap-2">
            <input type="search" name="q" class="form-control" placeholder="Search products" value="<?= e($search) ?>">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </form>
        <a href="<?= e(url('admin/products.php?new=1')) ?>" class="btn btn-primary"><i class="fas fa-plus"></i> New product</a>
    </div>
    <div class="card shadow-sm"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><img src="<?= e(productImage($p)) ?>" alt="" class="admin-thumb"></td>
                        <td><?= e($p['name']) ?><?= $p['featured'] ? ' <i class="fas fa-star text-warning" title="Featured"></i>' : '' ?></td>
                        <td><?= e($p['category_name']) ?></td>
                        <td><?= e(formatPrice($p['price'])) ?></td>
                        <td><?= (int) $p['stock'] ?></td>
                        <td><?= statusBadge($p['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('admin/products.php?edit=' . (int) $p['id'])) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete &quot;<?= e($p['name']) ?>&quot;? This cannot be undone.">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?><tr><td colspan="7" class="text-center text-muted py-4">No products found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
    <div class="mt-3"><?= renderPagination($page, $pages, array_filter(['q' => $search])) ?></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
