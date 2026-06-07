<?php
/** @var string $template */
/** @var string $appName */
$appDomain = config()['app_domain'] ?? 'getqualitystuff.com';
$brandLogoHorizontal = '<img class="brand-logo" src="/assets/gqs-logo-hor.png" alt="' . e($appName) . '" width="500" height="133">';
$brandLogoResponsive = '<picture class="brand-logo-picture"><source media="(max-width: 680px)" srcset="/assets/gqs-logo-vert.png"><img class="brand-logo" src="/assets/gqs-logo-hor.png" alt="' . e($appName) . '" width="500" height="133"></picture>';
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
    <link rel="stylesheet" href="/assets/styles.css">
    <script src="/assets/search.js" defer></script>
    <script src="/assets/ui.js" defer></script>
</head>
<body>
    <header class="site-header">
        <a class="brand-mark" href="/">
            <span class="brand-mark__symbol">
                <?= $brandLogoResponsive ?>
            </span>
        </a>
        <nav class="nav__browse" aria-label="Browse">
            <details class="browse-menu">
                <summary>Browse</summary>
                <div class="browse-menu__panel">
                    <a href="/brands">Brands</a>
                    <a href="/stores">Stores</a>
                    <a href="/items">Items</a>
                </div>
            </details>
            <div class="nav__links">
                <a href="/brands">Brands</a>
                <a href="/stores">Stores</a>
                <a href="/items">Items</a>
            </div>
        </nav>
        <nav class="nav__utilities" aria-label="Primary">
                <button class="nav-search" type="button" data-global-search-open>
                    <span>Search</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m21 21-4.35-4.35"></path>
                        <circle cx="11" cy="11" r="7"></circle>
                    </svg>
                </button>
                <a class="nav__news" href="/news">News</a>
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
                    <a class="button button--quiet nav__login" href="/account?redirect=<?= e(urlencode($signedOutRedirect)) ?>">Join or log in</a>
                <?php endif; ?>
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
                    <?= $brandLogoHorizontal ?>
                </span>
            </a>
            <p>Better brands, easier to find at <?= e($appDomain) ?>.</p>
        </div>
        <nav class="site-footer__links" aria-label="Footer">
            <a href="/brands">Brands</a>
            <a href="/stores">Stores</a>
            <a href="/items">Items</a>
            <a href="/news">News</a>
            <a href="/about">About</a>
            <?php if (is_admin()): ?><a href="/admin">Admin</a><?php endif; ?>
            <?php if ($signedInUser): ?><a href="/account">Account</a><?php else: ?><a href="/account">Join or log in</a><?php endif; ?>
        </nav>
        <p class="site-footer__fineprint">&copy; <?= e(date('Y')) ?> <?= e($appName) ?>. <?= e($appDomain) ?>.</p>
    </footer>
</body>
</html>
