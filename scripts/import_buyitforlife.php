<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/importer.php';

if (PHP_SAPI !== 'cli') {
    exit("This importer must be run from the command line.\n");
}

$path = $argv[1] ?? dirname(__DIR__) . '/data/buyitforlife.json';
$catalog = json_decode((string) file_get_contents($path), true);
if (!is_array($catalog) || count($catalog) !== 449) {
    throw new RuntimeException('Buy It For Life dataset must contain exactly 449 products.');
}

$imported = 0;
db()->beginTransaction();
try {
    foreach ($catalog as $row) {
        $brandId = ensure_listing((string) $row['brand'], (string) $row['category'], false);
        $listingId = ensure_listing(
            (string) $row['purchase_listing'],
            (string) $row['category'],
            (string) $row['purchase_listing'] !== (string) $row['brand']
        );
        $itemId = upsert_item_from_csv([
            'name' => $row['name'],
            'category' => $row['category'],
            'description' => $row['description'],
            'url' => $row['purchase_url'],
            'image_url' => $row['image_url'],
            'warranty' => $row['warranty'] ?? '',
            'warranty_details' => $row['warranty_details'] ?? '',
        ], $brandId);

        db()->prepare('DELETE FROM item_purchase_links WHERE item_id = :item_id')->execute(['item_id' => $itemId]);
        $stmt = db()->prepare(
            'INSERT INTO item_purchase_links (item_id, listing_id, url)
             VALUES (:item_id, :listing_id, :url)
             ON CONFLICT(item_id, listing_id) DO UPDATE SET url = excluded.url, updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute(['item_id' => $itemId, 'listing_id' => $listingId, 'url' => $row['purchase_url']]);
        $imported++;
    }
    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    throw $e;
}

echo "Imported: {$imported}\n";

function ensure_listing(string $name, string $category, bool $store): int
{
    $stmt = db()->prepare('SELECT id FROM brands WHERE lower(name) = lower(:name) LIMIT 1');
    $stmt->execute(['name' => $name]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    return save_brand([
        'name' => $name,
        'category' => $store ? 'marketplace' : $category,
        'description' => $store ? 'Store listing used for item purchase links.' : '',
        'url' => '',
        'image_url' => '',
        'featured' => 0,
        'popular' => 0,
    ]);
}
