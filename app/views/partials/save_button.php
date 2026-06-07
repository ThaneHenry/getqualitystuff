<?php
/** @var string $entityType */
/** @var int $entityId */
/** @var bool $isSaved */
$saveUser = current_user();
$returnPath = current_request_path();
?>
<?php if ($saveUser): ?>
<form action="/account/save" method="post" class="save-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
    <input type="hidden" name="entity_id" value="<?= e($entityId) ?>">
    <input type="hidden" name="saved" value="<?= $isSaved ? '0' : '1' ?>">
    <input type="hidden" name="redirect" value="<?= e($returnPath) ?>">
    <button type="submit" class="button--quiet"><?= icon_markup($isSaved ? 'saved' : 'save') ?> <?= $isSaved ? 'Saved' : 'Save' ?></button>
</form>
<?php else: ?>
<a class="button button--quiet" href="/account?redirect=<?= e(urlencode($returnPath)) ?>"><?= icon_markup('save') ?> Save</a>
<?php endif; ?>
