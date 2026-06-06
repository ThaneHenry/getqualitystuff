<section class="auth-panel">
    <p class="eyebrow">Account recovery</p>
    <h1>Reset password</h1>
    <p class="muted">Enter your email and we will send you a reset link.</p>
    <form method="post" action="/forgot-password" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Email <input type="email" name="email" required autocomplete="email"></label>
        <button type="submit">Send reset link</button>
    </form>
    <p class="auth-panel__footer"><a href="/login">Back to login</a></p>
</section>
