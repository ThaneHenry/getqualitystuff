<section class="admin-header">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Import CSV</h1>
        <p class="muted">Place your file in the project and enter its path, for example <code>data/initial.csv</code>.</p>
    </div>
</section>

<?php if (!empty($result)): ?>
    <section class="admin-panel">
        <h2>Import result</h2>
        <p><?= e($result['imported']) ?> imported, <?= e($result['skipped']) ?> skipped.</p>
        <?php if ($result['notes']): ?>
            <ul>
                <?php foreach (array_slice($result['notes'], 0, 20) as $note): ?><li><?= e($note) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

<form method="post" class="editor-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>CSV path <input name="csv_path" value="data/initial.csv" required></label>
    <button type="submit">Run import</button>
</form>
