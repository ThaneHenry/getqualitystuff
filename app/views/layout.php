<?php
/** @var string $template */
/** @var string $appName */
$appDomain = config()['app_domain'] ?? 'getqualitystuff.com';
$brandLogo = '<img class="brand-logo" src="/assets/get-logo.png" alt="' . e($appName) . '" width="335" height="102">';
$signedInUser = current_user();
$signedOutRedirect = safe_redirect_path($_GET['redirect'] ?? current_request_path(), '/');
$globalSearchSuggestions = search_suggestions();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? $appName) . ' · ' . $appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.3.2/css/flag-icons.min.css">
    <link rel="stylesheet" href="/assets/styles/tokens.css">
    <link rel="stylesheet" href="/assets/styles/base.css">
    <link rel="stylesheet" href="/assets/styles/components.css">
    <link rel="stylesheet" href="/assets/styles/pages.css">
    <script src="/assets/search.js" defer></script>
    <script src="/assets/ui.js" defer></script>
</head>
<body>
    <header class="site-header">
        <a class="brand-mark" href="/">
            <span class="brand-mark__symbol">
                <?= $brandLogo ?>
            </span>
        </a>
        <nav class="nav__utilities" aria-label="Primary">
            <button class="nav-search" type="button" data-global-search-open>
                <span>Search</span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m21 21-4.35-4.35"></path>
                    <circle cx="11" cy="11" r="7"></circle>
                </svg>
            </button>
            <div class="nav__browse">
                <details class="browse-menu">
                    <summary>Browse</summary>
                    <div class="browse-menu__panel">
                        <a href="/brands">Brands</a>
                        <a href="/stores">Stores</a>
                        <?php if ($capabilities['items']): ?><a href="/items">Items</a><?php endif; ?>
                    </div>
                </details>
                <div class="nav__links">
                    <a href="/brands">Brands</a>
                    <a href="/stores">Stores</a>
                    <?php if ($capabilities['items']): ?><a href="/items">Items</a><?php endif; ?>
                </div>
            </div>
            <a class="nav__news" href="/news">News</a>
            <div class="nav__auth">
                <?php if ($signedInUser): ?>
                    <details class="account-menu">
                        <summary aria-label="Open account menu">
                            <span class="account-menu__avatar"><?= e(strtoupper(substr($signedInUser['email'], 0, 1))) ?></span>
                            <span class="account-menu__label">Account</span>
                        </summary>
                        <div class="account-menu__panel">
                            <span class="account-menu__email"><?= e($signedInUser['email']) ?></span>
                            <a href="/account">Dashboard</a>
                            <a href="/account#saved">Saved</a>
                            <a href="/account#preferences">Preferences</a>
                            <?php if (is_admin()): ?><a href="/admin">Admin</a><?php endif; ?>
                            <form action="/logout" method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit" class="link-button">Log out</button>
                            </form>
                        </div>
                    </details>
                <?php else: ?>
                    <a class="button button--quiet nav__login" href="/login?redirect=<?= e(urlencode($signedOutRedirect)) ?>" data-auth-open><?= icon_markup('login') ?> Log in</a>
                <?php endif; ?>
            </div>
                <details class="mobile-menu">
                    <summary aria-label="Open menu">Menu</summary>
                    <nav class="mobile-menu__panel" aria-label="Mobile">
                        <a href="/brands">Brands</a>
                        <a href="/stores">Stores</a>
                        <?php if ($capabilities['items']): ?><a href="/items">Items</a><?php endif; ?>
                        <a href="/news">News</a>
                        <a href="/about">About</a>
                        <a href="/account"><?= $signedInUser ? 'Account' : 'Log in' ?></a>
                    </nav>
                </details>
        </nav>
    </header>

    <dialog class="global-search-dialog" data-global-search-dialog aria-labelledby="global-search-title">
        <div class="global-search-dialog__header">
            <h2 id="global-search-title">Search</h2>
            <button type="button" class="button--quiet" data-global-search-close>Close</button>
        </div>
        <form class="search-panel global-search-dialog__form" method="get" action="/search" data-search-form>
            <div class="search-panel__main">
                <div class="search-panel__input-wrap">
                    <input type="search" name="q" placeholder="Search brands, stores, items, values, locations" autocomplete="off" aria-expanded="false" aria-controls="global-search-dropdown">
                    <button class="search-panel__icon-submit" type="submit" aria-label="Search">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m21 21-4.35-4.35"></path>
                            <circle cx="11" cy="11" r="7"></circle>
                        </svg>
                    </button>
                    <div class="search-panel__dropdown" id="global-search-dropdown" role="listbox" hidden></div>
                </div>
            </div>
            <script type="application/json" data-search-suggestions><?= json_encode($globalSearchSuggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        </form>
    </dialog>

    <?php if (!$signedInUser): ?>
        <dialog class="auth-dialog" data-auth-dialog aria-labelledby="auth-dialog-title">
            <button class="auth-dialog__close" type="button" aria-label="Close login" data-auth-close>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18"></path>
                </svg>
            </button>
            <div class="auth-dialog__content">
                <img class="auth-dialog__logo" src="/assets/get-logo.png" alt="">
                <p class="eyebrow">Your account</p>
                <h2 id="auth-dialog-title">Welcome back</h2>
                <p class="auth-dialog__intro">Log in to save discoveries and shape your recommendations.</p>
                <?php $redirect = $signedOutRedirect; require __DIR__ . '/auth/google_button.php'; ?>
                <div class="auth-divider"><span>or use email</span></div>
                <form method="post" action="/login" class="form-stack">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="redirect" value="<?= e($signedOutRedirect) ?>">
                    <label>Email <input type="email" name="email" required autocomplete="email"></label>
                    <label>Password
                        <span class="password-field">
                            <input id="modal-login-password" type="password" name="password" required autocomplete="current-password">
                            <button type="button" class="field-button" data-password-toggle aria-controls="modal-login-password" aria-pressed="false">Show</button>
                        </span>
                    </label>
                    <button type="submit">Log in</button>
                </form>
<div class="auth-dialog__footer">
                     <a href="/forgot-password">Forgot password?</a>
                     <span>New here? <a href="/register?redirect=<?= e(urlencode($signedOutRedirect)) ?>">Create an account</a></span>
                 </div>
             </div>
        </dialog>
    <?php endif; ?>

    <main>
        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>

        <?php require __DIR__ . '/' . $template . '.php'; ?>
    </main>

    <footer class="site-footer">
        <div class="site-footer__brand">
            <a class="brand-mark brand-mark--footer" href="/">
                <span class="brand-mark__symbol">
                    <?= $brandLogo ?>
                </span>
            </a>
            <p>Better brands, easier to find at <?= e($appDomain) ?>.</p>
        </div>
        <nav class="site-footer__links" aria-label="Footer">
            <span class="site-footer__link-group">
                <a href="/brands">Brands</a>
                <a href="/stores">Stores</a>
                <?php if ($capabilities['items']): ?><a href="/items">Items</a><?php endif; ?>
            </span>
            <span class="site-footer__link-group">
                <a href="/news">News</a>
                <a href="/awards">Awards</a>
            </span>
            <span class="site-footer__link-group">
                <a href="/about">About</a>
            </span>
            <span class="site-footer__link-group">
                <a href="/privacy">Privacy</a>
                <a href="/tos">Terms</a>
            </span>
            <span class="site-footer__link-group">
                <?php if (is_admin()): ?><a href="/admin">Admin</a><?php endif; ?>
                <?php if ($signedInUser): ?><a href="/account">Account</a><?php else: ?><a href="/login?redirect=<?= e(urlencode($signedOutRedirect)) ?>" data-auth-open>Log in</a><?php endif; ?>
            </span>
        </nav>
        <p class="site-footer__fineprint">&copy; <?= e(date('Y')) ?> Broforge</p>
    </footer>
</body>
</html>
