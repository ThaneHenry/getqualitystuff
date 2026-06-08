<?php
/** @var array $brand */
/** @var array $scores */
/** @var array $items */
?>
<section class="detail-header">
    <div class="detail-image">
        <?php if ($brand['image_url']): ?>
            <img src="<?= e($brand['image_url']) ?>" alt="">
        <?php else: ?>
            <span><?= e(substr($brand['name'], 0, 1)) ?></span>
        <?php endif; ?>
    </div>
    <div>
        <p class="eyebrow"><?= e(category_label($brand['category_name'] ?: 'Brand')) ?></p>
        <h1><?= e($brand['name']) ?></h1>
        <p><?= e($brand['description'] ?: '...') ?></p>
        <div class="detail-actions">
            <?php if ($brand['url']): ?><a class="primary-link" href="<?= e($brand['url']) ?>" rel="noopener" target="_blank">Visit website <?= icon_markup('external') ?></a><?php endif; ?>
            <?php $entityType = 'brand'; $entityId = (int) $brand['id']; require __DIR__ . '/partials/save_button.php'; ?>
        </div>
    </div>
    <?php if ($brand['average_score'] !== null): ?>
        <div class="score-large">
            <span><?= e(score_label((float) $brand['average_score'])) ?></span>
            <small>overall</small>
        </div>
    <?php endif; ?>
</section>

<?php if ($brand['company_location'] || $brand['manufacturing_location'] || $brand['warranty']): ?>
    <section class="facts-grid">
        <?php if ($brand['company_location']): ?>
            <div><span>Company</span><strong><?= flag_markup($brand['company_location']) ?> <?= e(country_name($brand['company_location'])) ?></strong></div>
        <?php endif; ?>
        <?php if ($brand['manufacturing_location']): ?>
            <div><span>Manufacturing</span><strong><?= flag_markup($brand['manufacturing_location']) ?> <?= e(country_name($brand['manufacturing_location'])) ?></strong></div>
        <?php endif; ?>
        <?php if ($brand['warranty']): ?>
            <div><span>Warranty</span><strong><?= e($brand['warranty']) ?></strong></div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php
$hasAssessment = ($brand['assessment_status'] ?? 'listed') !== 'listed'
    || trim((string) ($brand['assessment_summary'] ?? '')) !== ''
    || trim((string) ($brand['assessment_strengths'] ?? '')) !== ''
    || trim((string) ($brand['assessment_caveats'] ?? '')) !== ''
    || !empty($brand['reviewed_at'])
    || !empty($sources);
?>
<?php if ($hasAssessment): ?>
    <?php $entity = $brand; require __DIR__ . '/partials/assessment.php'; ?>
<?php endif; ?>
<?php if ($awards): ?>
<section class="section-block brand-awards">
    <p class="eyebrow">GQS awards</p>
    <h2>Recognised strengths</h2>
    <div class="brand-awards__grid">
        <?php foreach ($awards as $award): ?>
            <a class="brand-award" href="/awards#<?= e($award['slug']) ?>">
                <img src="/assets/awards/placeholder.svg" alt="">
                <span><strong><?= e($award['name']) ?></strong><small><?= e($award['description']) ?></small></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/partials/scores.php'; ?>

<?php if ($items): ?>
<section class="section-block">
    <h2>Items from <?= e($brand['name']) ?></h2>
    <div class="directory-grid">
        <?php foreach ($items as $item): ?>
            <article class="listing-card">
                <a class="listing-card__link" href="/items/<?= e($item['slug']) ?>">
                    <div class="listing-card__image">
                        <span><?= e(substr($item['name'], 0, 1)) ?></span>
                        <?php if ($item['image_url']): ?><img src="<?= e($item['image_url']) ?>" alt=""><?php endif; ?>
                    </div>
                    <div class="listing-card__body">
                        <div class="card-meta">
                            <span class="type-tag type-tag--item">Item</span>
                            <?= listing_locations_markup($brand['company_location'], $brand['manufacturing_location'], 'Manufacturing location') ?>
                            <?php if ($item['category_name']): ?><span><?= e(category_label($item['category_name'])) ?></span><?php endif; ?>
                            <?php if ($item['average_score'] !== null): ?><span><?= e(score_label((float) $item['average_score'])) ?> score</span><?php endif; ?>
                        </div>
                        <h3><?= e($item['name']) ?></h3>
                        <p><?= e($item['description'] ?: '...') ?></p>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
        <?php if (!$items): ?><p class="muted">No items have been added for this brand yet.</p><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php $feedbackType = 'outdated_information'; $feedbackEntityType = 'brand'; $feedbackEntityId = (int) $brand['id']; require __DIR__ . '/partials/feedback_form.php'; ?>
