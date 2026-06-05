<?php
/** @var array $items */
?>
<section class="page-header">
    <div>
        <p class="eyebrow">Items</p>
        <h1>Item directory</h1>
    </div>
</section>

<section class="directory-results" aria-labelledby="items-heading">
    <div class="section-heading">
        <h2 id="items-heading"><?= e(count($items)) ?> <?= count($items) === 1 ? 'item' : 'items' ?></h2>
    </div>
    <div class="directory-grid">
        <?php foreach ($items as $item): ?>
            <article class="listing-card">
                <a href="/items/<?= e($item['slug']) ?>" class="listing-card__link" aria-label="<?= e($item['name']) ?>">
                    <div class="listing-card__image">
                        <?php if ($item['image_url']): ?>
                            <img src="<?= e($item['image_url']) ?>" alt="">
                        <?php else: ?>
                            <span><?= e(substr($item['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="listing-card__body">
                        <div class="card-meta">
                            <?php if ($item['category_name']): ?><span><?= e($item['category_name']) ?></span><?php endif; ?>
                            <span><?= e($item['brand_name']) ?></span>
                            <?php if ($item['average_score'] !== null): ?><span><?= e(score_label((float) $item['average_score'])) ?> score</span><?php endif; ?>
                        </div>
                        <h2><?= e($item['name']) ?></h2>
                        <p><?= e($item['description'] ?: 'Item details are being reviewed.') ?></p>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <article class="empty-state">
                <h2>No items yet</h2>
                <p>Items will appear here once they are added.</p>
            </article>
        <?php endif; ?>
    </div>
</section>
