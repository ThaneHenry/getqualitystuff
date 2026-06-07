<?php
/** @var array $user */
/** @var array $savedEntries */
/** @var array $recentEntries */
/** @var array $preferences */
/** @var array $categories */
/** @var array $criteria */
?>
<section class="page-header account-header">
    <div>
        <p class="eyebrow">Account</p>
        <h1>Your dashboard</h1>
        <p class="muted"><?= e($user['email']) ?></p>
    </div>
</section>

<?php if (!$user['email_verified_at']): ?>
<section class="notice">
    <div>
        <strong>Verify your email</strong>
        <p>Verification protects your saved picks and account recovery.</p>
    </div>
    <form action="/account/resend-verification" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="button--quiet">Resend link</button>
    </form>
</section>
<?php endif; ?>

<section class="account-section" id="saved">
    <div class="section-heading">
        <p class="eyebrow">Your collection</p>
        <h2>Saved</h2>
    </div>
    <div class="account-entry-grid">
        <?php foreach ($savedEntries as $entry): ?>
            <article class="account-entry">
                <a href="/<?= e($entry['entity_type'] === 'brand' ? 'brands' : 'items') ?>/<?= e($entry['slug']) ?>">
                    <div class="account-entry__image">
                        <span><?= e(substr($entry['name'], 0, 1)) ?></span>
                        <?php if ($entry['image_url']): ?><img src="<?= e($entry['image_url']) ?>" alt=""><?php endif; ?>
                    </div>
                    <div>
                        <span class="muted"><?= e(ucfirst($entry['entity_type'])) ?><?= $entry['brand_name'] ? ' by ' . e($entry['brand_name']) : '' ?></span>
                        <h3><?= e($entry['name']) ?></h3>
                    </div>
                </a>
                <form action="/account/save" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="entity_type" value="<?= e($entry['entity_type']) ?>">
                    <input type="hidden" name="entity_id" value="<?= e($entry['entity_id']) ?>">
                    <input type="hidden" name="saved" value="0">
                    <input type="hidden" name="redirect" value="/account#saved">
                    <button type="submit" class="link-button">Remove</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if (!$savedEntries): ?><p class="empty-inline">Save brands and items to build your shortlist.</p><?php endif; ?>
    </div>
</section>

<?php if ($recentEntries): ?>
<section class="account-section">
    <div class="section-heading"><h2>Recently viewed</h2></div>
    <div class="account-entry-grid account-entry-grid--compact">
        <?php foreach ($recentEntries as $entry): ?>
            <a class="recent-link" href="/<?= e($entry['entity_type'] === 'brand' ? 'brands' : 'items') ?>/<?= e($entry['slug']) ?>">
                <span><?= e(ucfirst($entry['entity_type'])) ?></span>
                <strong><?= e($entry['name']) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="account-section" id="preferences">
    <div class="section-heading">
        <p class="eyebrow">Personalization</p>
        <h2>What quality means to you</h2>
    </div>
    <form action="/account/preferences" method="post" class="preference-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <fieldset>
            <legend>Categories</legend>
            <div class="choice-grid">
                <?php foreach ($categories as $category): ?>
                    <label><input type="checkbox" name="category_ids[]" value="<?= e($category['id']) ?>" <?= in_array((int) $category['id'], $preferences['category_ids'], true) ? 'checked' : '' ?>> <?= e(category_label($category['name'])) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <fieldset>
            <legend>Priorities</legend>
            <div class="choice-grid">
                <?php foreach ($criteria as $criterion): ?>
                    <label><input type="checkbox" name="criterion_slugs[]" value="<?= e($criterion['slug']) ?>" <?= in_array($criterion['slug'], $preferences['criterion_slugs'], true) ? 'checked' : '' ?>> <?= e($criterion['name']) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <button type="submit">Save preferences</button>
    </form>
</section>

<section class="account-section account-profile">
    <h2>Profile</h2>
    <dl>
        <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
        <div><dt>Status</dt><dd><?= $user['email_verified_at'] ? 'Verified' : 'Verification pending' ?></dd></div>
        <div><dt>Role</dt><dd><?= e(ucfirst((string) $user['role'])) ?></dd></div>
    </dl>
</section>
