<?php
/** @var array $item */
/** @var array $scores */
/** @var array $purchaseLinks */
?>
<section class="detail-header">
    <div class="detail-image">
        <?php if ($item['image_url']): ?>
            <img src="<?= e($item['image_url']) ?>" alt="">
        <?php else: ?>
            <span><?= e(substr($item['name'], 0, 1)) ?></span>
        <?php endif; ?>
    </div>
    <div>
        <p class="eyebrow"><?= e(category_label($item['category_name'] ?: 'Item')) ?></p>
        <h1><?= e($item['name']) ?></h1>
        <p class="byline">by <a href="/brands/<?= e($item['brand_slug']) ?>"><?= e($item['brand_name']) ?></a></p>
        <p><?= e($item['description'] ?: '...') ?></p>
        <div class="detail-actions">
            <?php $entityType = 'item'; $entityId = (int) $item['id']; require __DIR__ . '/partials/save_button.php'; ?>
        </div>
    </div>
    <?php if ($item['average_score'] !== null): ?><div class="score-large">
        <span><?= e(score_label($item['average_score'] !== null ? (float) $item['average_score'] : null)) ?></span>
        <small>overall</small>
    </div><?php endif; ?>
</section>

<?php
$hasWarranty = trim((string) ($item['warranty_details'] ?? '')) !== ''
    || !in_array(strtolower(trim((string) ($item['warranty'] ?? ''))), ['', 'none'], true);
?>
<?php if ($hasWarranty): ?>
    <section class="facts-grid item-warranty">
        <div>
            <span>Warranty</span>
            <strong><?= e($item['warranty'] ?: 'Details available') ?></strong>
            <?php if ($item['warranty_details']): ?><p><?= e($item['warranty_details']) ?></p><?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($purchaseLinks || $item['url']): ?>
    <section class="buy-item">
        <h2>Buy item</h2>
        <div class="detail-actions">
            <?php if ($purchaseLinks): ?>
                <?php foreach ($purchaseLinks as $link): ?>
                    <a class="primary-link" href="<?= e($link['url']) ?>" rel="noopener nofollow sponsored" target="_blank"><?= e($link['listing_name']) ?> <?= icon_markup('external') ?></a>
                <?php endforeach; ?>
            <?php else: ?>
                <a class="primary-link" href="<?= e($item['url']) ?>" rel="noopener" target="_blank"><?= e($item['brand_name']) ?> <?= icon_markup('external') ?></a>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php
$hasAssessment = ($item['assessment_status'] ?? 'listed') !== 'listed'
    || trim((string) ($item['assessment_summary'] ?? '')) !== ''
    || trim((string) ($item['assessment_strengths'] ?? '')) !== ''
    || trim((string) ($item['assessment_caveats'] ?? '')) !== ''
    || !empty($item['reviewed_at'])
    || !empty($sources);
?>
<?php if ($hasAssessment): ?>
    <?php $entity = $item; require __DIR__ . '/partials/assessment.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/scores.php'; ?>
<?php $feedbackType = 'outdated_information'; $feedbackEntityType = 'item'; $feedbackEntityId = (int) $item['id']; require __DIR__ . '/partials/feedback_form.php'; ?>
