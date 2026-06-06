<?php
/** @var array $item */
/** @var array $scores */
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
        <p><?= e($item['description']) ?></p>
        <div class="detail-actions">
            <?php if ($item['url']): ?><a class="primary-link" href="<?= e($item['url']) ?>" rel="noopener" target="_blank">View item</a><?php endif; ?>
            <?php $entityType = 'item'; $entityId = (int) $item['id']; require __DIR__ . '/partials/save_button.php'; ?>
        </div>
    </div>
    <div class="score-large">
        <span><?= e(score_label($item['average_score'] !== null ? (float) $item['average_score'] : null)) ?></span>
        <small>overall</small>
    </div>
</section>

<?php require __DIR__ . '/partials/scores.php'; ?>
