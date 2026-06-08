<?php
/** @var array|null $item */
/** @var array $brands */
/** @var array $scores */
/** @var string $assessmentSources */
/** @var string $purchaseLinks */
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
    <label>Warranty <input name="warranty" maxlength="160" value="<?= e($item['warranty'] ?? '') ?>"></label>
    <label>Warranty details <textarea name="warranty_details" rows="4"><?= e($item['warranty_details'] ?? '') ?></textarea></label>
    <label>Purchase links
        <textarea name="purchase_links" rows="4" placeholder="Amazon | https://www.amazon.com/..."><?= e($purchaseLinks ?? '') ?></textarea>
        <small class="muted">One per line as Listing | URL. The listing must already exist.</small>
    </label>
    <label>Listing logo or image URL
        <input type="url" name="image_url" value="<?= e($item['image_url'] ?? '') ?>">
        <small class="muted">The original is stored privately and local detail and thumbnail images are generated automatically.</small>
    </label>
    <?php if ($isEdit && !empty($item['url'])): ?>
        <div class="field-action">
            <button type="submit" formaction="/admin/items/<?= e((string) $item['id']) ?>/get-logo" formmethod="post" formnovalidate>Get logo</button>
            <span class="muted">Uses the saved item URL. Replace it if the result is not suitable for a square thumbnail.</span>
        </div>
    <?php endif; ?>
    <fieldset class="assessment-editor">
        <legend>Public investigative assessment</legend>
        <label>Status <select name="assessment_status"><?php foreach (assessment_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= ($item['assessment_status'] ?? 'listed') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Assessment summary <textarea name="assessment_summary" rows="4"><?= e($item['assessment_summary'] ?? '') ?></textarea></label>
        <label>Reasons to consider, one per line <textarea name="assessment_strengths" rows="4"><?= e($item['assessment_strengths'] ?? '') ?></textarea></label>
        <label>Caveats, one per line <textarea name="assessment_caveats" rows="4"><?= e($item['assessment_caveats'] ?? '') ?></textarea></label>
        <label>Sources, one per line as Label | URL <textarea name="assessment_sources" rows="4"><?= e($assessmentSources ?? '') ?></textarea></label>
        <label>Reviewed date <input type="date" name="reviewed_at" value="<?= e(isset($item['reviewed_at']) ? substr((string) $item['reviewed_at'], 0, 10) : '') ?>"></label>
    </fieldset>
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
