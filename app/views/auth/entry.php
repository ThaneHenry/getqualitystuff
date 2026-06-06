<?php
/** @var string $redirect */
$redirect = safe_redirect_path($redirect ?? null);
?>
<section class="auth-panel account-entry">
    <p class="eyebrow">Your account</p>
    <h1>Find better stuff, your way.</h1>
    <p class="account-entry__intro">Save brands and items, keep a shortlist, and personalize recommendations around what quality means to you.</p>
    <div class="account-entry__actions">
        <a class="primary-link" href="/register?redirect=<?= e(urlencode($redirect)) ?>">Create account</a>
        <a class="button button--quiet" href="/login?redirect=<?= e(urlencode($redirect)) ?>">Log in</a>
    </div>
    <p class="account-entry__fineprint">Browsing Get Quality Stuff does not require an account.</p>
</section>
