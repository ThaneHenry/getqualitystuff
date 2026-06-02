<?php
/** @var array $results */
/** @var array $categories */
/** @var array $filters */
$hasSearch = trim((string) ($filters['q'] ?? '')) !== '' || trim((string) ($filters['category'] ?? '')) !== '';
?>
<section class="page-header">
    <div>
        <p class="eyebrow"><?= $hasSearch ? 'Results' : 'Brands' ?></p>
        <h1><?= $hasSearch ? 'Matching brands' : 'Brand directory' ?></h1>
    </div>
    <form class="search-panel search-panel--compact" method="get" action="/brands">
        <div class="search-panel__main">
            <input type="search" name="q" placeholder="Search brands, values, locations, categories" value="<?= e($filters['q'] ?? '') ?>">
            <button type="submit">Search</button>
        </div>
        <div class="search-panel__filters">
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= ($filters['category'] ?? '') === $category['slug'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="sort">
                <?php foreach (['featured' => 'Featured first', 'score' => 'Highest score', 'newest' => 'Newest'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['sort'] ?? 'featured') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</section>

<section class="directory-results" aria-labelledby="results-heading">
    <div class="section-heading">
        <h2 id="results-heading"><?= e(count($results)) ?> <?= count($results) === 1 ? 'brand' : 'brands' ?></h2>
    </div>
    <div class="directory-grid">
    <?php if (!$results): ?>
        <article class="empty-state">
            <h2>No brands found</h2>
            <p>Try a broader search or clear the category filter.</p>
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
                        <?php if ($result['category_name']): ?><span><?= e($result['category_name']) ?></span><?php endif; ?>
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
