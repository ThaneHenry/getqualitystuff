<?php
/** @var array $pagination */
/** @var string $paginationPath */
/** @var string $paginationItemLabel */
$total = (int) ($pagination['total'] ?? 0);
$currentPage = (int) ($pagination['current_page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$firstItem = (int) ($pagination['first_item'] ?? 0);
$lastItem = (int) ($pagination['last_item'] ?? 0);
$pluralLabel = $total === 1 ? $paginationItemLabel : $paginationItemLabel . 's';
$paginationQuery = array_intersect_key($_GET, array_flip(['q', 'category', 'mode', 'status', 'company', 'manufacturing', 'warranty', 'page']));
?>
<?php if ($total > 0): ?>
    <nav class="pagination" aria-label="<?= e(ucfirst($pluralLabel)) ?> pagination">
        <p class="pagination__summary">Showing <?= e($firstItem) ?>-<?= e($lastItem) ?> of <?= e($total) ?> <?= e($pluralLabel) ?></p>
        <?php if ($totalPages > 1): ?>
            <div class="pagination__controls">
                <?php $firstDisabled = $currentPage <= 1; ?>
                <a class="pagination__link pagination__link--icon<?= $firstDisabled ? ' is-disabled' : '' ?>" href="<?= e(pagination_url($paginationPath, $paginationQuery, 1)) ?>" aria-label="First page" <?= $firstDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?>>&laquo;</a>
                <a class="pagination__link pagination__link--icon<?= $firstDisabled ? ' is-disabled' : '' ?>" href="<?= e(pagination_url($paginationPath, $paginationQuery, max(1, $currentPage - 1))) ?>" aria-label="Previous page" <?= $firstDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?>>&lsaquo;</a>

                <?php foreach (pagination_page_items($currentPage, $totalPages) as $item): ?>
                    <?php if ($item === 'ellipsis'): ?>
                        <span class="pagination__ellipsis" aria-hidden="true">...</span>
                    <?php else: ?>
                        <?php $page = (int) $item; ?>
                        <a class="pagination__link<?= $page === $currentPage ? ' is-current' : '' ?>" href="<?= e(pagination_url($paginationPath, $paginationQuery, $page)) ?>" <?= $page === $currentPage ? 'aria-current="page"' : 'aria-label="Page ' . e($page) . '"' ?>><?= e($page) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php $lastDisabled = $currentPage >= $totalPages; ?>
                <a class="pagination__link pagination__link--icon<?= $lastDisabled ? ' is-disabled' : '' ?>" href="<?= e(pagination_url($paginationPath, $paginationQuery, min($totalPages, $currentPage + 1))) ?>" aria-label="Next page" <?= $lastDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?>>&rsaquo;</a>
                <a class="pagination__link pagination__link--icon<?= $lastDisabled ? ' is-disabled' : '' ?>" href="<?= e(pagination_url($paginationPath, $paginationQuery, $totalPages)) ?>" aria-label="Last page" <?= $lastDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?>>&raquo;</a>
            </div>
        <?php endif; ?>
    </nav>
<?php endif; ?>
