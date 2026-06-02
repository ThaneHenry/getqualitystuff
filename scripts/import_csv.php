<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/importer.php';

if (PHP_SAPI !== 'cli') {
    exit("This importer must be run from the command line.\n");
}

$path = $argv[1] ?? null;
if (!$path) {
    exit("Usage: php scripts/import_csv.php data/initial.csv\n");
}

$fullPath = str_starts_with($path, '/') ? $path : dirname(__DIR__) . '/' . $path;

try {
    db();
    $result = import_csv($fullPath);
    echo "Imported: {$result['imported']}\n";
    echo "Skipped: {$result['skipped']}\n";
    foreach (array_slice($result['notes'], 0, 20) as $note) {
        echo "- {$note}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Import failed: {$e->getMessage()}\n");
    exit(1);
}
