<section class="page-header">
    <p class="eyebrow">Account</p>
    <h1>Your account</h1>
</section>

<section class="section-block account-summary">
    <h2>Profile</h2>
    <dl>
        <div>
            <dt>Email</dt>
            <dd><?= e($user['email']) ?></dd>
        </div>
        <div>
            <dt>Role</dt>
            <dd><?= e(ucfirst((string) $user['role'])) ?></dd>
        </div>
    </dl>
    <form action="/logout" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="button--quiet">Log out</button>
    </form>
</section>
