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
    if (!is_string($html) || $html === '') {
        return ['description' => '', 'image' => ''];
    }

    $image = extract_meta_content($html, 'property', 'og:image')
        ?: extract_meta_content($html, 'property', 'og:image:secure_url')
        ?: extract_meta_content($html, 'name', 'twitter:image');

    $description = extract_meta_content($html, 'property', 'og:description')
        ?: extract_meta_content($html, 'name', 'twitter:description')
        ?: extract_meta_content($html, 'name', 'description');

    return [
        'description' => normalize_meta_text($description),
        'image' => $image === '' ? '' : absolutize_url(html_entity_decode($image, ENT_QUOTES, 'UTF-8'), $url),
    ];
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
    $flash = flash();

    require __DIR__ . '/views/layout.php';
}
