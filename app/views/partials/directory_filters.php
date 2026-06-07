<?php
/** @var string $filterPath */
/** @var array $filters */
/** @var array $filterOptions */
$activeFilters = array_filter([
    'category' => $filters['category'] ?? '',
    'status' => $filters['status'] ?? '',
    'company' => $filters['company'] ?? '',
    'manufacturing' => $filters['manufacturing'] ?? '',
    'warranty' => $filters['warranty'] ?? '',
], static fn (string $value): bool => $value !== '');
?>
<form class="directory-filter-form" method="get" action="<?= e($filterPath) ?>">
    <div class="directory-filter-form__bar">
        <label>Sort
            <select name="mode">
                <?php foreach (['all' => 'Recommended', 'featured' => 'Featured', 'newest' => 'Newest'] + (($capabilities['scores'] ?? false) ? ['score' => 'Highest score'] : []) as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['mode'] ?? 'all') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button--quiet filter-toggle" type="button" data-filter-open><?= icon_markup('filter') ?> Filters<?= $activeFilters ? ' (' . count($activeFilters) . ')' : '' ?></button>
        <?php if ($activeFilters): ?><a class="clear-filters" href="<?= e($filterPath) ?>">Clear all</a><?php endif; ?>
    </div>
    <dialog class="filter-dialog" data-filter-dialog>
        <div class="filter-dialog__header"><h2>Filters</h2><button class="button--quiet" type="button" data-filter-close>Close</button></div>
        <div class="filter-grid">
            <label>Category <select name="category"><option value="">All categories</option><?php foreach ($filterOptions['categories'] as $category): ?><option value="<?= e($category['slug']) ?>" <?= ($filters['category'] ?? '') === $category['slug'] ? 'selected' : '' ?>><?= e(category_label($category['name'])) ?></option><?php endforeach; ?></select></label>
            <label>Assessment <select name="status"><option value="">Any status</option><?php foreach ($filterOptions['statuses'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Company country <select name="company"><option value="">Any country</option><?php foreach ($filterOptions['company_locations'] as $country): ?><option value="<?= e($country) ?>" <?= ($filters['company'] ?? '') === $country ? 'selected' : '' ?>><?= e(country_name($country)) ?></option><?php endforeach; ?></select></label>
            <label>Manufacturing country <select name="manufacturing"><option value="">Any country</option><?php foreach ($filterOptions['manufacturing_locations'] as $country): ?><option value="<?= e($country) ?>" <?= ($filters['manufacturing'] ?? '') === $country ? 'selected' : '' ?>><?= e(country_name($country)) ?></option><?php endforeach; ?></select></label>
            <label>Warranty <select name="warranty"><option value="">Any</option><option value="yes" <?= ($filters['warranty'] ?? '') === 'yes' ? 'selected' : '' ?>>Warranty information available</option></select></label>
        </div>
        <div class="filter-dialog__actions"><button type="submit">Apply filters</button><a class="button button--quiet" href="<?= e($filterPath) ?>">Clear all</a></div>
    </dialog>
</form>
