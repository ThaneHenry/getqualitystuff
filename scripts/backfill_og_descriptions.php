<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/repository.php';

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

db();

$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : 25;
$stmt = db()->prepare(
    "SELECT id, name, url
     FROM brands
     WHERE (description = '' OR description = notes) AND url != ''
     ORDER BY featured DESC, name ASC
     LIMIT :limit"
);
$stmt->bindValue('limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$brands = $stmt->fetchAll();

$updated = 0;
$missed = 0;
$update = db()->prepare('UPDATE brands SET description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id');

foreach ($brands as $brand) {
    $description = discover_og_description($brand['url']);
    if ($description === '') {
        $missed++;
        echo "No description: {$brand['name']}\n";
        continue;
    }

    $update->execute(['description' => $description, 'id' => $brand['id']]);
    $updated++;
    echo "Updated: {$brand['name']}\n";
}

echo "Done. {$updated} updated, {$missed} without an OG description.\n";
