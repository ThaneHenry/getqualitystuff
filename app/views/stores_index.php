<?php
/** @var array $stores */
/** @var array $categories */
/** @var array $filters */
/** @var array $filterOptions */
/** @var array $pagination */
/** @var string $paginationPath */
?>
<div class="page-layout">
<section class="page-header">
    <div>
        <p class="eyebrow">Stores</p>
        <h1>Store directory</h1>
    </div>
    <?php $filterPath = '/stores'; require __DIR__ . '/partials/directory_filters.php'; ?>
</section>

<section class="directory-results" aria-labelledby="stores-heading">
    <div class="section-heading">
        <h2 id="stores-heading"><?= e($pagination['total']) ?> <?= (int) $pagination['total'] === 1 ? 'store' : 'stores' ?></h2>
    </div>
    <div class="directory-grid">
        <?php foreach ($stores as $store): ?>
            <article class="listing-card">
                <a href="/brands/<?= e($store['slug']) ?>" class="listing-card__link" aria-label="<?= e($store['name']) ?>">
                    <div class="listing-card__image">
                        <span><?= e(substr($store['name'], 0, 1)) ?></span>
                        <?php if ($store['image_url']): ?>
                            <img src="<?= e($store['image_url']) ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="listing-card__body">
                        <div class="card-meta">
                            <?= listing_locations_markup($store['company_location'], $store['delivery_locations'], 'Delivery locations') ?>
                            <?php if ($store['category_name']): ?><span><?= e(category_label($store['category_name'])) ?></span><?php endif; ?>
                            <?php if ((int) $store['item_count'] > 0): ?>
                                <span><?= e((int) $store['item_count']) ?> <?= (int) $store['item_count'] === 1 ? 'item' : 'items' ?></span>
                            <?php endif; ?>
                            <span class="assessment-status assessment-status--<?= e($store['assessment_status'] ?? 'listed') ?>"><?= e(assessment_status_label($store['assessment_status'] ?? 'listed')) ?></span>
                        </div>
                        <h2><?= e($store['name']) ?></h2>
                        <p><?= e($store['assessment_summary'] ?: ($store['description'] ?: '...')) ?></p>
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
    <?php $paginationItemLabel = 'store'; require __DIR__ . '/partials/pagination.php'; ?>
    </section>
</div>
