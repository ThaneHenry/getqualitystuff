<?php
/** @var string $filterPath */
/** @var array $categories */
/** @var array $filters */
$activeMode = $filters['mode'] ?? 'all';
$activeCategory = $filters['category'] ?? '';
$filterUrl = static function (array $changes) use ($filterPath, $activeMode, $activeCategory): string {
    $params = array_filter([
        'mode' => $changes['mode'] ?? $activeMode,
        'category' => $changes['category'] ?? $activeCategory,
    ], static fn (string $value): bool => $value !== '' && $value !== 'all');
    return $filterPath . ($params ? '?' . http_build_query($params) : '');
};
?>
<nav class="directory-filters" aria-label="Directory filters">
    <div class="filter-chips filter-chips--modes">
        <?php foreach (['all' => 'All', 'featured' => 'Featured', 'score' => 'Highest score', 'newest' => 'Newest'] as $value => $label): ?>
            <a href="<?= e($filterUrl(['mode' => $value])) ?>" class="<?= $activeMode === $value ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="filter-chips filter-chips--categories">
        <a href="<?= e($filterUrl(['category' => ''])) ?>" class="<?= $activeCategory === '' ? 'is-active' : '' ?>">All categories</a>
        <?php foreach ($categories as $category): ?>
            <a href="<?= e($filterUrl(['category' => $category['slug']])) ?>" class="<?= $activeCategory === $category['slug'] ? 'is-active' : '' ?>"><?= e(category_label($category['name'])) ?></a>
        <?php endforeach; ?>
    </div>
</nav>
