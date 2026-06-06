<?php
/** @var array $results */
/** @var array $categories */
/** @var array $filters */
$isSearchPage = $isSearchPage ?? false;
$hasSearch = $isSearchPage && trim((string) ($filters['q'] ?? '')) !== '';
$resultCountLabel = $hasSearch
    ? (count($results) === 1 ? 'result' : 'results')
    : (count($results) === 1 ? 'brand' : 'brands');
?>
<section class="page-header">
    <div>
        <p class="eyebrow"><?= $hasSearch ? 'Results' : 'Brands' ?></p>
        <h1><?= $hasSearch ? 'Matching results' : 'Brand directory' ?></h1>
    </div>
    <?php if ($isSearchPage): ?>
    <form class="search-panel search-panel--compact" method="get" action="/search">
        <div class="search-panel__main">
            <div class="search-panel__input-wrap">
                <input type="search" name="q" placeholder="Search brands, stores, values, locations, categories" value="<?= e($filters['q'] ?? '') ?>">
                <button class="search-panel__icon-submit" type="submit" aria-label="Search">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m21 21-4.35-4.35"></path>
                        <circle cx="11" cy="11" r="7"></circle>
                    </svg>
                </button>
            </div>
        </div>
    </form>
    <?php else: ?>
        <?php $filterPath = '/brands'; require __DIR__ . '/partials/directory_filters.php'; ?>
    <?php endif; ?>
</section>

<section class="directory-results" aria-labelledby="results-heading">
    <div class="section-heading">
        <h2 id="results-heading"><?= e(count($results)) ?> <?= e($resultCountLabel) ?></h2>
    </div>
    <div class="directory-grid">
    <?php if (!$results): ?>
        <article class="empty-state">
            <h2><?= $hasSearch ? 'No results found' : 'No brands found' ?></h2>
            <p>Try a broader search.</p>
        </article>
    <?php endif; ?>

    <?php foreach ($results as $result): ?>
        <?php $href = '/brands/' . $result['slug']; ?>
        <article class="listing-card">
            <a href="<?= e($href) ?>" class="listing-card__link" aria-label="<?= e($result['name']) ?>">
                <div class="listing-card__image">
                    <?php if ($result['image_url']): ?>
                        <img src="<?= e($result['image_url']) ?>" alt="">
                    <?php else: ?>
                        <span><?= e(substr($result['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="listing-card__body">
                    <div class="card-meta">
                        <?php if ($result['category_name']): ?><span><?= e(category_label($result['category_name'])) ?></span><?php endif; ?>
                        <?php if ($result['company_location']): ?><?= flag_markup($result['company_location']) ?><?php endif; ?>
                        <?php if ((int) $result['item_count'] > 0): ?>
                            <span><?= e((int) $result['item_count']) ?> <?= (int) $result['item_count'] === 1 ? 'item' : 'items' ?></span>
                        <?php endif; ?>
                        <?php if ($result['average_score'] !== null): ?><span><?= e(score_label((float) $result['average_score'])) ?> score</span><?php endif; ?>
                    </div>
                    <h2><?= e($result['name']) ?></h2>
                    <p><?= e($result['description'] ?: 'Brand details are being reviewed.') ?></p>
                </div>
            </a>
        </article>
    <?php endforeach; ?>
    </div>
</section>
