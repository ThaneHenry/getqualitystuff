<?php
/** @var array $brands */
/** @var array $items */
/** @var array $logs */
/** @var array $feedbackEntries */
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

<section class="admin-panel" id="feedback">
    <h2>Public feedback</h2>
    <?php foreach ($feedbackEntries as $entry): ?>
        <article class="feedback-admin-entry">
            <div><strong><?= e($entry['type'] === 'suggest_brand' ? 'Brand suggestion' : 'Outdated information') ?></strong><?php if ($entry['entity_name']): ?> · <?= e($entry['entity_name']) ?><?php endif; ?><p><?= nl2br(e($entry['message'])) ?></p><small><?= e($entry['contact_email'] ?: 'No contact email') ?> · <?= e($entry['created_at']) ?></small></div>
            <form method="post" action="/admin/feedback/<?= e((string) $entry['id']) ?>/status">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <select name="status"><?php foreach (['new' => 'New', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $entry['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                <button type="submit">Update</button>
            </form>
        </article>
    <?php endforeach; ?>
    <?php if (!$feedbackEntries): ?><p class="muted">No public feedback yet.</p><?php endif; ?>
</section>

<section class="admin-columns">
    <div class="admin-panel">
        <h2>Brands</h2>
        <?php foreach ($brands as $brand): ?>
            <div class="admin-row">
                <span><?= e($brand['name']) ?><?php if (!empty($brand['popular'])): ?> <small>Popular</small><?php endif; ?></span>
                <a href="/admin/brands/<?= e((string) $brand['id']) ?>/edit">Edit</a>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="admin-panel">
        <h2>Items</h2>
        <?php foreach ($items as $item): ?>
            <div class="admin-row">
                <span><?= e($item['name']) ?> <small><?= e($item['brand_name']) ?><?= !empty($item['popular']) ? ' · Popular' : '' ?></small></span>
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
