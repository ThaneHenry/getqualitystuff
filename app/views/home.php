<?php
/** @var array $featuredBrands */
/** @var array $featuredStores */
/** @var array $featuredItems */
/** @var array $categories */
/** @var array $filters */
/** @var array $searchSuggestions */
/** @var array $forYouBrands */
$featuredEntries = [];
foreach ($featuredBrands as $brand) {
    $featuredEntries[] = [
        'type' => 'Brand',
        'href' => '/brands/' . $brand['slug'],
        'name' => $brand['name'],
        'description' => $brand['assessment_summary'] ?: ($brand['description'] ?: '...'),
        'image_url' => $brand['image_url'],
        'category_name' => $brand['category_name'],
        'company_location' => $brand['company_location'],
        'item_count' => (int) $brand['item_count'],
        'average_score' => $brand['average_score'],
        'brand_name' => '',
    ];
}
foreach ($featuredStores as $store) {
    $featuredEntries[] = [
        'type' => 'Store',
        'href' => '/brands/' . $store['slug'],
        'name' => $store['name'],
        'description' => $store['assessment_summary'] ?: ($store['description'] ?: '...'),
        'image_url' => $store['image_url'],
        'category_name' => $store['category_name'],
        'company_location' => $store['company_location'],
        'item_count' => (int) $store['item_count'],
        'average_score' => $store['average_score'],
        'brand_name' => '',
    ];
}
foreach ($featuredItems as $item) {
    $featuredEntries[] = [
        'type' => 'Item',
        'href' => '/items/' . $item['slug'],
        'name' => $item['name'],
        'description' => $item['assessment_summary'] ?: ($item['description'] ?: '...'),
        'image_url' => $item['image_url'],
        'category_name' => $item['category_name'],
        'company_location' => '',
        'item_count' => 0,
        'average_score' => $item['average_score'],
        'brand_name' => $item['brand_name'],
    ];
}
?>
<section class="hero-search">
    <div>
        <h1>Find <span class="hero-search__better">better</span> brands.</h1>
    </div>
    <form class="search-panel" method="get" action="/search" data-search-form>
        <div class="search-panel__main">
            <div class="search-panel__input-wrap">
                <input type="search" name="q" placeholder="Search brands, stores, values, locations, categories" value="<?= e($filters['q'] ?? '') ?>" autocomplete="off" aria-expanded="false" aria-controls="home-search-dropdown" data-primary-search>
                <button class="search-panel__icon-submit" type="submit" aria-label="Search">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m21 21-4.35-4.35"></path>
                        <circle cx="11" cy="11" r="7"></circle>
                    </svg>
                </button>
                <div class="search-panel__dropdown" id="home-search-dropdown" role="listbox" hidden></div>
            </div>
        </div>
        <script type="application/json" data-search-suggestions><?= json_encode($searchSuggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    </form>
</section>

<?php if ($forYouBrands): ?>
<section class="featured-section" aria-labelledby="for-you-heading">
    <div class="section-heading">
        <p class="eyebrow">Based on your preferences</p>
        <h2 id="for-you-heading">For you</h2>
    </div>
    <div class="featured-card-grid">
        <?php foreach ($forYouBrands as $brand): ?>
            <article class="featured-card">
                <a class="featured-card__link" href="/brands/<?= e($brand['slug']) ?>">
                    <div class="featured-panel__image">
                        <span><?= e(substr($brand['name'], 0, 1)) ?></span>
                        <?php if ($brand['image_url']): ?><img src="<?= e($brand['image_url']) ?>" alt=""><?php endif; ?>
                    </div>
                    <div class="featured-card__body">
                        <div class="card-meta">
                            <?php if ($brand['category_name']): ?><span><?= e(category_label($brand['category_name'])) ?></span><?php endif; ?>
                            <?php if ($brand['average_score'] !== null): ?><span><?= e(score_label((float) $brand['average_score'])) ?> score</span><?php endif; ?>
                        </div>
                        <h3><?= e($brand['name']) ?></h3>
                        <p><?= e($brand['assessment_summary'] ?: ($brand['description'] ?: '...')) ?></p>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($featuredEntries): ?>
<section class="featured-section" aria-labelledby="featured-heading">
    <div class="section-heading">
        <h2 id="featured-heading">Featured</h2>
    </div>
    <div class="featured-card-grid">
        <?php foreach ($featuredEntries as $entry): ?>
            <article class="featured-card">
                <a class="featured-card__link" href="<?= e($entry['href']) ?>">
                    <div class="featured-panel__image">
                        <span><?= e(substr($entry['name'], 0, 1)) ?></span>
                        <?php if ($entry['image_url']): ?>
                            <img src="<?= e($entry['image_url']) ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="featured-card__body">
                        <div class="card-meta">
                            <span class="type-tag"><?= e($entry['type']) ?></span>
                            <?php if ($entry['category_name']): ?><span><?= e(category_label($entry['category_name'])) ?></span><?php endif; ?>
                            <?php if ($entry['brand_name']): ?><span><?= e($entry['brand_name']) ?></span><?php endif; ?>
                            <?php if ($entry['company_location']): ?><?= flag_markup($entry['company_location']) ?><?php endif; ?>
                            <?php if ($entry['item_count'] > 0): ?><span><?= e($entry['item_count']) ?> items</span><?php endif; ?>
                            <?php if ($entry['average_score'] !== null): ?><span><?= e(score_label((float) $entry['average_score'])) ?> score</span><?php endif; ?>
                        </div>
                        <h3><?= e($entry['name']) ?></h3>
                        <p><?= e($entry['description']) ?></p>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
