<?php
/** @var string $template */
/** @var string $appName */
$appDomain = config()['app_domain'] ?? 'getqualitystuff.com';
$brandLogo = '<img class="brand-logo" src="/assets/gqs-logo-horizontal.png" alt="' . e($appName) . '" width="1013" height="320">';
$signedInUser = current_user();
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
</head>
<body>
    <header class="site-header">
        <a class="brand-mark" href="/">
            <span class="brand-mark__symbol">
                <?= $brandLogo ?>
            </span>
        </a>
        <nav class="nav">
            <a href="/brands">Brands</a>
            <a href="/stores">Stores</a>
            <a href="/items">Items</a>
            <a href="/news">News</a>
            <?php if (is_admin()): ?>
                <a href="/admin">Admin</a>
            <?php endif; ?>
            <?php if ($signedInUser): ?>
                <a href="/account">Account</a>
                <form action="/logout" method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="link-button">Log out</button>
                </form>
            <?php else: ?>
                <a href="/login">Log in</a>
            <?php endif; ?>
        </nav>
    </header>

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
            <a href="/brands">Brands</a>
            <a href="/stores">Stores</a>
            <a href="/items">Items</a>
            <a href="/news">News</a>
            <?php if (is_admin()): ?><a href="/admin">Admin</a><?php endif; ?>
            <?php if ($signedInUser): ?><a href="/account">Account</a><?php else: ?><a href="/login">Log in</a><?php endif; ?>
        </nav>
        <p class="site-footer__fineprint">&copy; <?= e(date('Y')) ?> <?= e($appName) ?>. <?= e($appDomain) ?>.</p>
    </footer>
</body>
</html>
