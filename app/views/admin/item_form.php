<?php
/** @var array|null $item */
/** @var array $brands */
/** @var array $scores */
$isEdit = !empty($item);
?>
<section class="admin-header">
    <div>
        <p class="eyebrow">Admin</p>
        <h1><?= $isEdit ? 'Edit item' : 'New item' ?></h1>
    </div>
</section>

<form method="post" class="editor-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Brand
        <select name="brand_id" required>
            <?php foreach ($brands as $brand): ?>
                <option value="<?= e((string) $brand['id']) ?>" <?= (int) ($item['brand_id'] ?? 0) === (int) $brand['id'] ? 'selected' : '' ?>>
                    <?= e($brand['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Name <input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
    <label>Category <input name="category" value="<?= e($categoryName ?? '') ?>"></label>
    <label>Description <textarea name="description" rows="5"><?= e($item['description'] ?? '') ?></textarea></label>
    <label>Item URL <input type="url" name="url" value="<?= e($item['url'] ?? '') ?>"></label>
    <label>Image URL <input type="url" name="image_url" value="<?= e($item['image_url'] ?? '') ?>"></label>
    <label class="checkbox-label"><input type="checkbox" name="featured" value="1" <?= !empty($item['featured']) ? 'checked' : '' ?>> Featured</label>
    <label class="checkbox-label"><input type="checkbox" name="popular" value="1" <?= !empty($item['popular']) ? 'checked' : '' ?>> Popular</label>

    <?php require __DIR__ . '/score_fields.php'; ?>

    <div class="form-actions">
        <button type="submit">Save item</button>
        <a class="button button--quiet" href="/admin">Cancel</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <form method="post" action="/admin/items/<?= e((string) $item['id']) ?>/delete" class="delete-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit">Delete item</button>
    </form>
<?php endif; ?>
