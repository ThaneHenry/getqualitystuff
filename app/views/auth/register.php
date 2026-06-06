<section class="auth-panel">
    <p class="eyebrow">Account</p>
    <h1>Create account</h1>
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="/register" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect ?? '/account') ?>">
        <label>Email <input type="email" name="email" required value="<?= e($email ?? '') ?>" autocomplete="email"></label>
        <label>Password
            <span class="password-field">
                <input id="register-password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <button type="button" class="field-button" data-password-toggle aria-controls="register-password" aria-pressed="false">Show</button>
            </span>
        </label>
        <label>Confirm password <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
        <button type="submit">Create account</button>
    </form>
    <p class="auth-panel__footer">Already have an account? <a href="/login?redirect=<?= e(urlencode($redirect ?? '/account')) ?>">Log in</a>.</p>
</section>
