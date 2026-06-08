<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/repository.php';

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$force = in_array('--force', $argv, true);
$limit = null;
foreach (array_slice($argv, 1) as $argument) {
    if (ctype_digit($argument)) {
        $limit = (int) $argument;
    }
}

$sql = "SELECT id, name, image_url FROM items WHERE image_url != '' ORDER BY id";
if ($limit !== null && $limit > 0) {
    $sql .= ' LIMIT ' . $limit;
}

$processed = 0;
$skipped = 0;
$failed = 0;
foreach (db()->query($sql)->fetchAll() as $item) {
    $itemId = (int) $item['id'];
    if (!$force && item_image_files_complete($itemId)) {
        $skipped++;
        continue;
    }
    try {
        process_item_image($itemId, (string) $item['image_url'], $force);
        echo "Processed: {$item['name']}\n";
        $processed++;
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed: {$item['name']} ({$e->getMessage()})\n");
        $failed++;
    }
}

echo "Done. {$processed} processed, {$skipped} skipped, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
