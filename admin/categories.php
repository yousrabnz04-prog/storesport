<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$errors = [];
$editing = null;

if (isPost()) {
    verify_csrf();
    $action = postString('action');

    if ($action === 'save') {
        $id   = postInt('id');
        $name = postString('name', 100);
        $icon = postString('icon', 60);
        $desc = postString('description', 1000);

        if ($name === '') {
            $errors[] = 'The category name is required.';
        }
        if ($icon !== '' && !preg_match('/^fa-[a-z0-9-]+$/', $icon)) {
            $errors[] = 'The icon must be a Font Awesome class such as "fa-dumbbell".';
        }
        $editing = ['id' => $id, 'name' => $name, 'icon' => $icon, 'description' => $desc];

        if (!$errors) {
            $slug = uniqueSlug('categories', $name, $id);
            if ($id > 0) {
                $pdo->prepare('UPDATE categories SET name = ?, slug = ?, icon = ?, description = ? WHERE id = ?')
                    ->execute([$name, $slug, $icon ?: null, $desc ?: null, $id]);
                logAdmin('update_category', 'category', $id, $name);
                flash('success', 'Category updated.');
            } else {
                $pdo->prepare('INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $slug, $icon ?: null, $desc ?: null]);
                logAdmin('create_category', 'category', (int) $pdo->lastInsertId(), $name);
                flash('success', 'Category created.');
            }
            redirect('admin/categories.php');
        }
    } elseif ($action === 'delete') {
        $id = postInt('id');
        $used = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $used->execute([$id]);
        if ((int) $used->fetchColumn() > 0) {
            flash('danger', 'This category still contains products. Move or delete them first.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            logAdmin('delete_category', 'category', $id);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
}

if ($editing === null && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$categories = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count FROM categories c ORDER BY c.name'
)->fetchAll();

$pageTitle = 'Categories';
$adminPage = 'categories';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm"><div class="card-body">
            <h2 class="h5 mb-3"><?= $editing && $editing['id'] ? 'Edit category' : 'New category' ?></h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= e($editing['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="icon" class="form-label">Icon <span class="text-muted small">(Font Awesome class)</span></label>
                    <input type="text" id="icon" name="icon" class="form-control" placeholder="fa-dumbbell" value="<?= e($editing['icon'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" class="form-control"><?= e($editing['description'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
                <?php if ($editing): ?><a href="<?= e(url('admin/categories.php')) ?>" class="btn btn-link">Cancel</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm"><div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><i class="fas <?= e($c['icon'] ?: 'fa-tag') ?> fa-fw me-1 text-muted"></i><?= e($c['name']) ?></td>
                            <td class="text-muted"><?= e($c['slug']) ?></td>
                            <td><?= (int) $c['product_count'] ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('admin/categories.php?edit=' . (int) $c['id'])) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot;?">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$categories): ?><tr><td colspan="4" class="text-center text-muted py-4">No categories yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
