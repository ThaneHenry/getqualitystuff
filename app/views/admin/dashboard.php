<?php
/** @var array $brands */
/** @var array $items */
/** @var array $logs */
?>
<section class="admin-header">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Manage Get Quality Stuff</h1>
    </div>
    <div class="admin-actions">
        <a class="button" href="/admin/brands/new">New brand</a>
        <a class="button" href="/admin/items/new">New item</a>
        <a class="button button--quiet" href="/admin/import">Import CSV</a>
    </div>
</section>

<section class="admin-columns">
    <div class="admin-panel">
        <h2>Brands</h2>
        <?php foreach ($brands as $brand): ?>
            <div class="admin-row">
                <span><?= e($brand['name']) ?></span>
                <a href="/admin/brands/<?= e((string) $brand['id']) ?>/edit">Edit</a>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="admin-panel">
        <h2>Items</h2>
        <?php foreach ($items as $item): ?>
            <div class="admin-row">
                <span><?= e($item['name']) ?> <small><?= e($item['brand_name']) ?></small></span>
                <a href="/admin/items/<?= e((string) $item['id']) ?>/edit">Edit</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-panel">
    <h2>Recent imports</h2>
    <?php foreach ($logs as $log): ?>
        <div class="admin-row">
            <span><?= e($log['filename']) ?></span>
            <small><?= e($log['imported_count'] . ' imported, ' . $log['skipped_count'] . ' skipped') ?></small>
        </div>
    <?php endforeach; ?>
    <?php if (!$logs): ?><p class="muted">No imports have been run yet.</p><?php endif; ?>
</section>
