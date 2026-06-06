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
        <label>Email <input type="email" name="email" required value="<?= e($email ?? '') ?>"></label>
        <label>Password <input type="password" name="password" required minlength="8"></label>
        <label>Confirm password <input type="password" name="password_confirmation" required minlength="8"></label>
        <button type="submit">Create account</button>
    </form>
    <p class="auth-panel__footer">Already have an account? <a href="/login">Log in</a>.</p>
</section>
