<?php
/** @var array $featuredBrands */
/** @var array|null $latestNews */
/** @var array $categories */
/** @var array $filters */
?>
<section class="hero-search">
    <div>
        <h1>Find better brands.</h1>
    </div>
    <form class="search-panel" method="get" action="/brands">
        <div class="search-panel__main">
            <input type="search" name="q" placeholder="Search brands, values, locations, categories" value="<?= e($filters['q'] ?? '') ?>" autofocus>
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

<?php if ($featuredBrands): ?>
    <section class="featured-section" aria-labelledby="featured-heading">
        <div class="section-heading">
            <h2 id="featured-heading">Featured brands</h2>
        </div>
        <div class="featured-card-grid">
            <?php foreach ($featuredBrands as $brand): ?>
                <article class="featured-card">
                    <a class="featured-card__link" href="/brands/<?= e($brand['slug']) ?>">
                        <div class="featured-panel__image">
                            <?php if ($brand['image_url']): ?>
                                <img src="<?= e($brand['image_url']) ?>" alt="">
                            <?php else: ?>
                                <span><?= e(substr($brand['name'], 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="featured-card__body">
                            <div class="card-meta">
                                <?php if ($brand['category_name']): ?><span><?= e($brand['category_name']) ?></span><?php endif; ?>
                                <?php if ($brand['company_location']): ?>
                                    <?= flag_markup($brand['company_location']) ?>
                                <?php endif; ?>
                                <?php if ((int) $brand['item_count'] > 0): ?><span><?= e((int) $brand['item_count']) ?> items</span><?php endif; ?>
                                <?php if ($brand['average_score'] !== null): ?><span><?= e(score_label((float) $brand['average_score'])) ?> score</span><?php endif; ?>
                            </div>
                            <h3><?= e($brand['name']) ?></h3>
                            <p><?= e($brand['description'] ?: 'Brand details are being reviewed.') ?></p>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($latestNews): ?>
<section class="news-preview" aria-labelledby="news-preview-heading">
    <div class="section-heading">
        <h2 id="news-preview-heading">News</h2>
    </div>
    <article class="news-card">
        <div>
            <time datetime="<?= e($latestNews['published_at']) ?>"><?= e(date('j M Y', strtotime($latestNews['published_at']))) ?></time>
            <h3><?= e($latestNews['title']) ?></h3>
            <p><?= e($latestNews['excerpt']) ?></p>
        </div>
        <a class="primary-link" href="/news">Read more</a>
    </article>
</section>
<?php endif; ?>
