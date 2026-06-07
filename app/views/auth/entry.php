<?php
/** @var string $redirect */
$redirect = safe_redirect_path($redirect ?? null);
?>
<section class="auth-panel auth-gateway">
    <img class="auth-gateway__logo" src="/assets/gqs-logo-vert.png" alt="">
    <p class="eyebrow">Your account</p>
    <h1>Save the good stuff.</h1>
    <p class="account-entry__intro">Keep a shortlist and shape recommendations around what quality means to you.</p>
    <?php require __DIR__ . '/google_button.php'; ?>
    <div class="auth-divider"><span>or use email</span></div>
    <div class="account-entry__actions">
        <a class="button button--quiet" href="/login?redirect=<?= e(urlencode($redirect)) ?>">Log in</a>
        <a class="button button--quiet" href="/register?redirect=<?= e(urlencode($redirect)) ?>">Create account</a>
    </div>
    <p class="account-entry__fineprint">We only use account access to sign you in. Browsing does not require an account.</p>
</section>
