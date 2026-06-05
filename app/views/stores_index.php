<?php
/** @var array $stores */
?>
<section class="page-header">
    <div>
        <p class="eyebrow">Stores</p>
        <h1>Store directory</h1>
    </div>
</section>

<section class="directory-results" aria-labelledby="stores-heading">
    <div class="section-heading">
        <h2 id="stores-heading"><?= e(count($stores)) ?> <?= count($stores) === 1 ? 'store' : 'stores' ?></h2>
    </div>
    <div class="directory-grid">
        <?php foreach ($stores as $store): ?>
            <article class="listing-card">
                <a href="/brands/<?= e($store['slug']) ?>" class="listing-card__link" aria-label="<?= e($store['name']) ?>">
                    <div class="listing-card__image">
                        <?php if ($store['image_url']): ?>
                            <img src="<?= e($store['image_url']) ?>" alt="">
                        <?php else: ?>
                            <span><?= e(substr($store['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="listing-card__body">
                        <div class="card-meta">
                            <?php if ($store['category_name']): ?><span><?= e(category_label($store['category_name'])) ?></span><?php endif; ?>
                            <?php if ($store['company_location']): ?><?= flag_markup($store['company_location']) ?><?php endif; ?>
                            <?php if ((int) $store['item_count'] > 0): ?>
                                <span><?= e((int) $store['item_count']) ?> <?= (int) $store['item_count'] === 1 ? 'item' : 'items' ?></span>
                            <?php endif; ?>
                            <?php if ($store['average_score'] !== null): ?><span><?= e(score_label((float) $store['average_score'])) ?> score</span><?php endif; ?>
                        </div>
                        <h2><?= e($store['name']) ?></h2>
                        <p><?= e($store['description'] ?: 'Store details are being reviewed.') ?></p>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
        <?php if (!$stores): ?>
            <article class="empty-state">
                <h2>No stores yet</h2>
                <p>Stores will appear here once they are added.</p>
            </article>
        <?php endif; ?>
    </div>
</section>
