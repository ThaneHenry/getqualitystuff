<section class="auth-panel">
    <p class="eyebrow">Account</p>
    <h1>Welcome back</h1>
    <p class="auth-panel__intro">Sign in to see your saved discoveries and preferences.</p>
    <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <?php require __DIR__ . '/google_button.php'; ?>
    <div class="auth-divider"><span>or use email</span></div>
    <form method="post" action="/login" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect ?? '/account') ?>">
        <label>Email <input type="email" name="email" required value="<?= e($email ?? '') ?>" autocomplete="email"></label>
        <label>Password
            <span class="password-field">
                <input id="login-password" type="password" name="password" required autocomplete="current-password">
                <button type="button" class="field-button" data-password-toggle aria-controls="login-password" aria-pressed="false">Show</button>
            </span>
        </label>
        <button type="submit">Log in</button>
    </form>
    <p class="auth-panel__footer"><a href="/forgot-password">Forgot password?</a></p>
    <p class="auth-panel__footer">New to Get Quality Stuff? <a href="/register?redirect=<?= e(urlencode($redirect ?? '/account')) ?>">Create an account</a>.</p>
</section>
