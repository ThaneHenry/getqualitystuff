<section class="auth-panel">
    <p class="eyebrow">Account</p>
    <h1>Log in</h1>
    <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="/login" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Email <input type="email" name="email" required value="<?= e($email ?? '') ?>"></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Log in</button>
    </form>
    <p class="auth-panel__footer">No account yet? <a href="/register">Create one</a>.</p>
</section>
