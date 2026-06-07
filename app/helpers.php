<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function safe_redirect_path(mixed $path, string $fallback = '/account'): string
{
    if (
        !is_string($path)
        || !str_starts_with($path, '/')
        || str_starts_with($path, '//')
        || preg_match('/[\r\n]/', $path)
    ) {
        return $fallback;
    }

    return $path;
}

function current_request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return safe_redirect_path($uri, '/');
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return $path ?: '/';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid form token.');
    }
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }

    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function score_label(?float $score): string
{
    return $score === null ? 'No score' : number_format($score, 1);
}

function assessment_statuses(): array
{
    return [
        'listed' => 'Listed',
        'investigating' => 'Investigating',
        'assessed' => 'Assessed',
        'needs_update' => 'Needs update',
    ];
}

function assessment_status_label(?string $status): string
{
    return assessment_statuses()[$status ?? ''] ?? 'Listed';
}

function assessment_status_message(?string $status): string
{
    return match ($status) {
        'investigating' => 'Our investigative assessment is in progress.',
        'assessed' => 'Investigatively assessed using publicly available evidence.',
        'needs_update' => 'This assessment may be out of date and needs another review.',
        default => 'Listed for discovery; a full assessment has not been completed.',
    };
}

function icon_markup(string $name): string
{
    $paths = [
        'external' => '<path d="M14 5h5v5"></path><path d="M10 14 19 5"></path><path d="M19 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"></path>',
        'filter' => '<path d="M4 6h16"></path><path d="M7 12h10"></path><path d="M10 18h4"></path>',
        'login' => '<path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"></path>',
        'report' => '<path d="M5 21V5"></path><path d="M5 5h11l-1 4 1 4H5"></path>',
        'save' => '<path d="M6 4h12v16l-6-4-6 4z"></path>',
        'saved' => '<path d="M6 4h12v16l-6-4-6 4z" fill="currentColor"></path>',
        'suggest' => '<path d="M12 3v18"></path><path d="M3 12h18"></path>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }

    return '<svg class="button-icon" viewBox="0 0 24 24" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function editorial_lines(?string $value): array
{
    return array_values(array_filter(
        array_map('trim', preg_split('/\R/', (string) $value) ?: []),
        static fn (string $line): bool => $line !== ''
    ));
}

function public_categories(): array
{
    return array_values(array_filter(
        all_categories(),
        static fn (array $category): bool => !in_array(strtolower($category['name']), ['test', 'various', 'girl stuff'], true)
    ));
}

function category_label(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return preg_replace_callback('/[A-Za-z][A-Za-z0-9]*/', static function (array $matches): string {
        $word = $matches[0];
        if (strlen($word) <= 4 && strtoupper($word) === $word) {
            return $word;
        }

        return ucfirst(strtolower($word));
    }, $value) ?: $value;
}

function bool_from_input(mixed $value): int
{
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on'], true) ? 1 : 0;
    }

    return $value ? 1 : 0;
}

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?: '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : bin2hex(random_bytes(4));
}

function discover_og_metadata(?string $url): array
{
    $url = trim((string) $url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return ['description' => '', 'image' => ''];
    }

    $html = fetch_remote_html($url);
    if ($html === '') {
        return ['description' => '', 'image' => ''];
    }

    $image = extract_meta_content($html, 'property', 'og:image')
        ?: extract_meta_content($html, 'property', 'og:image:secure_url')
        ?: extract_meta_content($html, 'name', 'twitter:image')
        ?: extract_meta_content($html, 'itemprop', 'image')
        ?: extract_link_href($html, ['apple-touch-icon', 'apple-touch-icon-precomposed', 'icon', 'shortcut icon']);

    $description = extract_meta_content($html, 'property', 'og:description')
        ?: extract_meta_content($html, 'name', 'twitter:description')
        ?: extract_meta_content($html, 'name', 'description');

    return [
        'description' => normalize_meta_text($description),
        'image' => $image === '' ? '' : absolutize_url(html_entity_decode($image, ENT_QUOTES, 'UTF-8'), $url),
    ];
}

function fetch_remote_html(string $url): string
{
    $sslOptions = [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ];

    foreach (['/etc/ssl/cert.pem', '/opt/homebrew/etc/openssl@3/cert.pem', '/usr/local/etc/openssl@3/cert.pem'] as $certPath) {
        if (is_readable($certPath)) {
            $sslOptions['cafile'] = $certPath;
            break;
        }
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'follow_location' => 1,
            'max_redirects' => 3,
            'ignore_errors' => true,
            'header' => "User-Agent: Mozilla/5.0 (compatible; GetQualityStuff/1.0; +https://getqualitystuff.com)\r\nAccept: text/html,application/xhtml+xml\r\n",
        ],
        'ssl' => $sslOptions,
    ]);

    $html = @file_get_contents($url, false, $context, 0, 512000);
    if (is_string($html) && $html !== '') {
        return $html;
    }

    if (!function_exists('curl_init')) {
        return '';
    }

    $curl = curl_init($url);
    if ($curl === false) {
        return '';
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GetQualityStuff/1.0; +https://getqualitystuff.com)',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);
    $html = curl_exec($curl);

    return is_string($html) ? substr($html, 0, 512000) : '';
}

function discover_og_image(?string $url): string
{
    return discover_og_metadata($url)['image'];
}

function discover_og_description(?string $url): string
{
    return discover_og_metadata($url)['description'];
}

function extract_meta_content(string $html, string $attribute, string $value): string
{
    $pattern = '/<meta\b(?=[^>]*\b' . preg_quote($attribute, '/') . '\s*=\s*["\']' . preg_quote($value, '/') . '["\'])(?=[^>]*\bcontent\s*=\s*(["\'])(.*?)\1)[^>]*>/i';
    if (preg_match($pattern, $html, $matches)) {
        return trim($matches[2]);
    }

    return '';
}

function extract_link_href(string $html, array $rels): string
{
    if (!preg_match_all('/<link\b[^>]*>/i', $html, $matches)) {
        return '';
    }

    foreach ($matches[0] as $tag) {
        if (!preg_match('/\brel\s*=\s*(["\'])(.*?)\1/i', $tag, $relMatch)) {
            continue;
        }

        $tagRels = preg_split('/\s+/', strtolower(trim($relMatch[2]))) ?: [];
        foreach ($rels as $rel) {
            if (in_array(strtolower($rel), $tagRels, true) && preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $tag, $hrefMatch)) {
                return trim($hrefMatch[2]);
            }
        }
    }

    return '';
}

function normalize_meta_text(string $value): string
{
    $value = html_entity_decode(trim($value), ENT_QUOTES, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value) ?: '';
    return trim($value);
}

function absolutize_url(string $candidate, string $baseUrl): string
{
    $candidate = trim($candidate);
    if ($candidate === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $candidate)) {
        return $candidate;
    }

    if (str_starts_with($candidate, '//')) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $candidate;
    }

    $base = parse_url($baseUrl);
    if (!$base || empty($base['scheme']) || empty($base['host'])) {
        return '';
    }

    $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
    if (str_starts_with($candidate, '/')) {
        return $origin . $candidate;
    }

    $path = $base['path'] ?? '/';
    $directory = rtrim(substr($path, 0, strrpos($path, '/') ?: 0), '/');
    return $origin . ($directory ? '/' . $directory : '') . '/' . $candidate;
}

function flag_icon_code(?string $countryCode): string
{
    $countryCode = strtolower(trim((string) $countryCode));
    return preg_match('/^[a-z]{2}$/', $countryCode) ? $countryCode : '';
}

function country_name(?string $countryCode): string
{
    $code = strtoupper(trim((string) $countryCode));
    $countries = [
        'AT' => 'Austria',
        'AU' => 'Australia',
        'CA' => 'Canada',
        'CH' => 'Switzerland',
        'CN' => 'China',
        'CZ' => 'Czechia',
        'DE' => 'Germany',
        'DK' => 'Denmark',
        'ES' => 'Spain',
        'EU' => 'European Union',
        'FI' => 'Finland',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'IE' => 'Ireland',
        'IN' => 'India',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'LT' => 'Lithuania',
        'NL' => 'Netherlands',
        'NO' => 'Norway',
        'NZ' => 'New Zealand',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'SE' => 'Sweden',
        'TW' => 'Taiwan',
        'US' => 'United States',
        'VN' => 'Vietnam',
    ];

    return $countries[$code] ?? $code;
}

function flag_markup(?string $countryCode): string
{
    $flagCode = flag_icon_code($countryCode);
    if ($flagCode === '') {
        return '';
    }

    $name = country_name($countryCode);
    return '<span class="flag-chip fi fi-' . e($flagCode) . '" title="' . e($name) . '" aria-label="' . e($name) . '" role="img"></span>';
}

function render(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $appName = config()['app_name'];
    $capabilities = site_capabilities();
    $renderUser = current_user();
    $savedEntryKeys = $renderUser ? saved_entry_keys((int) $renderUser['id']) : [];
    $flash = flash();

    require __DIR__ . '/views/layout.php';
}
