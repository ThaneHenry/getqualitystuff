<?php
/** @var array|null $brand */
/** @var array $scores */
/** @var string $assessmentSources */
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
    <label>Listing logo URL
        <input type="url" name="image_url" value="<?= e($brand['image_url'] ?? '') ?>">
        <small class="muted">Use a square logo, ideally at least 512 x 512 px. It will fill the listing thumbnail.</small>
    </label>
    <?php if ($isEdit && !empty($brand['url'])): ?>
        <div class="field-action">
            <button type="submit" formaction="/admin/brands/<?= e((string) $brand['id']) ?>/get-logo" formmethod="post" formnovalidate>Get logo</button>
            <span class="muted">Uses the saved website URL. Replace it if the result is not a suitable square logo.</span>
        </div>
    <?php endif; ?>
    <label>Company location <input name="company_location" maxlength="80" value="<?= e($brand['company_location'] ?? '') ?>"></label>
    <label>Manufacturing location <input name="manufacturing_location" maxlength="80" value="<?= e($brand['manufacturing_location'] ?? '') ?>"></label>
    <label>Store delivery locations <input name="delivery_locations" maxlength="80" value="<?= e($brand['delivery_locations'] ?? '') ?>"></label>
    <label>Warranty <input name="warranty" maxlength="160" value="<?= e($brand['warranty'] ?? '') ?>"></label>
    <label>Notes <textarea name="notes" rows="3"><?= e($brand['notes'] ?? '') ?></textarea></label>
    <fieldset class="assessment-editor">
        <legend>Public investigative assessment</legend>
        <label>Status <select name="assessment_status"><?php foreach (assessment_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= ($brand['assessment_status'] ?? 'listed') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Assessment summary <textarea name="assessment_summary" rows="4"><?= e($brand['assessment_summary'] ?? '') ?></textarea></label>
        <label>Reasons to consider, one per line <textarea name="assessment_strengths" rows="4"><?= e($brand['assessment_strengths'] ?? '') ?></textarea></label>
        <label>Caveats, one per line <textarea name="assessment_caveats" rows="4"><?= e($brand['assessment_caveats'] ?? '') ?></textarea></label>
        <label>Sources, one per line as Label | URL <textarea name="assessment_sources" rows="4"><?= e($assessmentSources ?? '') ?></textarea></label>
        <label>Reviewed date <input type="date" name="reviewed_at" value="<?= e(isset($brand['reviewed_at']) ? substr((string) $brand['reviewed_at'], 0, 10) : '') ?>"></label>
    </fieldset>
    <fieldset class="assessment-editor">
        <legend>GQS awards</legend>
        <p class="muted">Assign only when the published evidence supports the award criteria.</p>
        <div class="award-choice-grid">
            <?php foreach ($awards as $award): ?>
                <label>
                    <input type="checkbox" name="award_ids[]" value="<?= e((string) $award['id']) ?>" <?= in_array((int) $award['id'], $brandAwardIds, true) ? 'checked' : '' ?>>
                    <span><strong><?= e($award['name']) ?></strong><small><?= e($award['description']) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
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
