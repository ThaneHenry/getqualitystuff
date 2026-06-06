<section class="auth-panel">
    <p class="eyebrow">Account recovery</p>
    <h1>Choose a new password</h1>
    <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <?php if (!empty($token)): ?>
        <form method="post" action="/reset-password" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>New password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label>Confirm password <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
            <button type="submit">Update password</button>
        </form>
    <?php endif; ?>
    <p class="auth-panel__footer"><a href="/forgot-password">Request another link</a></p>
</section>
