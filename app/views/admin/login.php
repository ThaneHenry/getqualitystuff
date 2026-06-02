<section class="auth-panel">
    <p class="eyebrow">Admin</p>
    <h1>Log in</h1>
    <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="/admin/login" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Email <input type="email" name="email" required value="<?= e(config()['admin_email']) ?>"></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Log in</button>
    </form>
</section>
