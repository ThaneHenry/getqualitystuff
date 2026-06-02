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
        <p class="eyebrow"><?= e($brand['category_name'] ?: 'Brand') ?></p>
        <h1><?= e($brand['name']) ?></h1>
        <p><?= e($brand['description']) ?></p>
        <?php if ($brand['url']): ?><a class="primary-link" href="<?= e($brand['url']) ?>" rel="noopener" target="_blank">Visit website</a><?php endif; ?>
    </div>
    <?php if ($brand['average_score'] !== null): ?>
        <div class="score-large">
            <span><?= e(score_label((float) $brand['average_score'])) ?></span>
            <small>overall</small>
        </div>
    <?php endif; ?>
</section>

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
    <?php if ($brand['notes'] && $brand['notes'] !== $brand['description']): ?>
        <div><span>Notes</span><strong><?= e($brand['notes']) ?></strong></div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/scores.php'; ?>

<section class="section-block">
    <h2>Items from <?= e($brand['name']) ?></h2>
    <div class="directory-grid directory-grid--compact">
        <?php foreach ($items as $item): ?>
            <article class="listing-card">
                <a class="listing-card__body" href="/items/<?= e($item['slug']) ?>">
                    <div class="card-meta">
                        <?php if ($item['category_name']): ?><span><?= e($item['category_name']) ?></span><?php endif; ?>
                        <span><?= e(score_label($item['average_score'] !== null ? (float) $item['average_score'] : null)) ?></span>
                    </div>
                    <h3><?= e($item['name']) ?></h3>
                    <p><?= e($item['description']) ?></p>
                </a>
            </article>
        <?php endforeach; ?>
        <?php if (!$items): ?><p class="muted">No items have been added for this brand yet.</p><?php endif; ?>
    </div>
</section>
