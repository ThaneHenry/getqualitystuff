<?php
/** @var array|null $brand */
/** @var array $scores */
$isEdit = !empty($brand);
?>
<section class="admin-header">
    <div>
        <p class="eyebrow">Admin</p>
        <h1><?= $isEdit ? 'Edit brand' : 'New brand' ?></h1>
    </div>
</section>

<form method="post" class="editor-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Name <input name="name" required value="<?= e($brand['name'] ?? '') ?>"></label>
    <label>Category <input name="category" value="<?= e($categoryName ?? '') ?>"></label>
    <label>Description <textarea name="description" rows="5"><?= e($brand['description'] ?? '') ?></textarea></label>
    <label>Website URL <input type="url" name="url" value="<?= e($brand['url'] ?? '') ?>"></label>
    <label>Image URL <input type="url" name="image_url" value="<?= e($brand['image_url'] ?? '') ?>"></label>
    <label>Company location <input name="company_location" maxlength="80" value="<?= e($brand['company_location'] ?? '') ?>"></label>
    <label>Manufacturing location <input name="manufacturing_location" maxlength="80" value="<?= e($brand['manufacturing_location'] ?? '') ?>"></label>
    <label>Warranty <input name="warranty" maxlength="160" value="<?= e($brand['warranty'] ?? '') ?>"></label>
    <label>Notes <textarea name="notes" rows="3"><?= e($brand['notes'] ?? '') ?></textarea></label>
    <label class="checkbox-label"><input type="checkbox" name="featured" value="1" <?= !empty($brand['featured']) ? 'checked' : '' ?>> Featured</label>
    <label class="checkbox-label"><input type="checkbox" name="popular" value="1" <?= !empty($brand['popular']) ? 'checked' : '' ?>> Popular</label>

    <?php require __DIR__ . '/score_fields.php'; ?>

    <div class="form-actions">
        <button type="submit">Save brand</button>
        <a class="button button--quiet" href="/admin">Cancel</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <form method="post" action="/admin/brands/<?= e((string) $brand['id']) ?>/delete" class="delete-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit">Delete brand</button>
    </form>
<?php endif; ?>
