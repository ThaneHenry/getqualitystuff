<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$source = $argv[1] ?? 'https://buyitforlife.com/';
$output = $argv[2] ?? dirname(__DIR__) . '/data/buyitforlife.json';
$html = str_starts_with($source, 'http') ? file_get_contents($source) : file_get_contents($source);
if ($html === false) {
    throw new RuntimeException('Unable to read Buy It For Life catalog source.');
}

$decodedHtml = str_replace(['\\"', '\\\\'], ['"', '\\'], $html);
preg_match_all('~\{"id":"[^{}]+?\}~s', $decodedHtml, $matches);
$products = [];
foreach ($matches[0] as $encoded) {
    $product = json_decode($encoded, true);
    if (is_array($product) && isset($product['name'], $product['affiliateUrl'], $product['category'])) {
        $products[(string) $product['id']] = $product;
    }
}
if (count($products) !== 449) {
    throw new RuntimeException('Expected 449 products, found ' . count($products) . '.');
}

$brandOverrides = [
    'all-clad' => 'All-Clad',
    'american security' => 'American Security',
    'american standard' => 'American Standard',
    'blue yeti' => 'Blue',
    'briggs & riley' => 'Briggs & Riley',
    'chrome industries' => 'Chrome Industries',
    'crate and barrel' => 'Crate and Barrel',
    'de buyer' => 'De Buyer',
    'gi metal' => 'Gi Metal',
    'hanks belts' => 'Hanks Belts',
    'herman miller' => 'Herman Miller',
    'l.l bean' => 'L.L.Bean',
    'l.l.bean' => 'L.L.Bean',
    'le creuset' => 'Le Creuset',
    'nordic ware' => 'Nordic Ware',
    'peru pima' => 'Peru Pima',
    'rep ' => 'REP Fitness',
    'rogue ' => 'Rogue Fitness',
    'saddleback leather' => 'Saddleback Leather',
    'snap on' => 'Snap-on',
    'the bakers board' => 'The Bakers Board',
    'the good sheet' => 'The Good Sheet',
    'the ove glove' => 'Ove Glove',
    'the snowplow' => 'The Snowplow',
    'thermoworks' => 'ThermoWorks',
    'tom bihn' => 'Tom Bihn',
    'vlair 88p' => 'Viair',
];
$existingBrands = existing_brand_names();
$destinationNames = [
    'amazon.com' => 'Amazon',
    'costco.com' => 'Costco',
    'crateandbarrel.com' => 'Crate and Barrel',
    'homedepot.com' => 'Home Depot',
    'harborfreight.com' => 'Harbor Freight',
    'llbean.ca' => 'L.L.Bean',
    'llbean.com' => 'L.L.Bean',
    'patagonia.ca' => 'Patagonia',
    'patagonia.com' => 'Patagonia',
    'repfitness.com' => 'REP Fitness',
    'roguefitness.com' => 'Rogue Fitness',
    'shop.snapon.com' => 'Snap-on',
    'store.hermanmiller.com' => 'Herman Miller',
];

$catalog = [];
foreach (array_values($products) as $product) {
    $name = trim((string) $product['name']);
    $brand = infer_brand($name, $brandOverrides, $existingBrands);
    $url = canonical_purchase_url((string) $product['affiliateUrl']);
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?: $host;
    $listing = $destinationNames[$host] ?? (host_matches_brand($host, $brand) ? $brand : title_from_host($host));

    $catalog[] = [
        'source_id' => (string) $product['id'],
        'name' => $name,
        'brand' => $brand,
        'category' => trim((string) $product['category']),
        'description' => trim((string) ($product['notes'] ?? '')),
        'image_url' => trim((string) ($product['imageUrl'] ?? '')),
        'warranty' => trim((string) ($product['warranty'] ?? '')),
        'warranty_details' => trim((string) ($product['warrantyInfo'] ?? '')),
        'purchase_listing' => $listing,
        'purchase_url' => $url,
    ];
}

file_put_contents(
    $output,
    json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);
echo 'Wrote ' . count($catalog) . " products to {$output}\n";

function infer_brand(string $name, array $overrides, array $existingBrands): string
{
    $lower = strtolower($name);
    foreach ($overrides as $prefix => $brand) {
        if (str_starts_with($lower, $prefix)) {
            return $brand;
        }
    }

    foreach ($existingBrands as $existingBrand) {
        if (preg_match('/^' . preg_quote($existingBrand, '/') . '(?:\s|$)/i', $name)) {
            return $existingBrand;
        }
    }

    $first = preg_split('/\s+/', $name, 2)[0] ?? $name;
    return trim($first, " \t\n\r\0\x0B,");
}

function existing_brand_names(): array
{
    $path = dirname(__DIR__) . '/storage/getqualitystuff.sqlite';
    if (!is_file($path)) {
        return [];
    }
    $pdo = new PDO('sqlite:' . $path);
    $names = $pdo->query('SELECT name FROM brands ORDER BY length(name) DESC')->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_filter(array_map('strval', $names)));
}

function canonical_purchase_url(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return $url;
    }
    $host = strtolower((string) $parts['host']);
    if (!str_ends_with($host, 'amazon.com')) {
        return $url;
    }

    $path = (string) ($parts['path'] ?? '/');
    if (preg_match('~/dp/([A-Z0-9]{10})~i', $path, $match)) {
        return 'https://www.amazon.com/dp/' . strtoupper($match[1]);
    }
    if ($path === '/s') {
        parse_str((string) ($parts['query'] ?? ''), $query);
        return 'https://www.amazon.com/s?k=' . rawurlencode((string) ($query['k'] ?? ''));
    }
    return 'https://www.amazon.com' . $path;
}

function host_matches_brand(string $host, string $brand): bool
{
    $hostKey = preg_replace('/[^a-z0-9]/', '', explode('.', $host)[0]) ?? '';
    $brandKey = preg_replace('/[^a-z0-9]/', '', strtolower($brand)) ?? '';
    return $hostKey !== '' && ($hostKey === $brandKey || str_contains($hostKey, $brandKey) || str_contains($brandKey, $hostKey));
}

function title_from_host(string $host): string
{
    $label = explode('.', $host)[0] ?? $host;
    return ucwords(str_replace(['-', '_'], ' ', $label));
}
