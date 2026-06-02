<?php

declare(strict_types=1);

require_once __DIR__ . '/repository.php';

function import_csv(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new InvalidArgumentException('CSV file is missing or unreadable.');
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('Unable to open CSV file.');
    }

    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if ($headers === false) {
        fclose($handle);
        throw new InvalidArgumentException('CSV file is empty.');
    }

    $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
    $format = csv_format($headers);
    $required = $format === 'brand_export'
        ? ['rn', 'url', 'category', 'company location', 'manufacturing location', 'warranty', 'notes']
        : ['type', 'name', 'brand_name', 'category', 'description', 'url'];
    $missing = array_diff($required, $headers);
    if ($missing) {
        fclose($handle);
        throw new InvalidArgumentException('CSV is missing required columns: ' . implode(', ', $missing));
    }

    $imported = 0;
    $skipped = 0;
    $notes = [];

    db()->beginTransaction();
    try {
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            if ($data === false) {
                $skipped++;
                $notes[] = 'Skipped malformed row.';
                continue;
            }

            try {
                import_csv_row(normalize_csv_row($data, $format));
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
                $notes[] = trim(($data['name'] ?? 'Unnamed') . ': ' . $e->getMessage());
            }
        }

        $log = db()->prepare(
            'INSERT INTO import_logs (filename, imported_count, skipped_count, notes)
             VALUES (:filename, :imported_count, :skipped_count, :notes)'
        );
        $log->execute([
            'filename' => basename($path),
            'imported_count' => $imported,
            'skipped_count' => $skipped,
            'notes' => implode("\n", array_slice($notes, 0, 30)),
        ]);

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        fclose($handle);
        throw $e;
    }

    fclose($handle);

    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'notes' => $notes,
    ];
}

function csv_format(array $headers): string
{
    if (in_array('rn', $headers, true) && in_array('company location', $headers, true)) {
        return 'brand_export';
    }

    return 'getqualitystuff_template';
}

function normalize_csv_row(array $data, string $format): array
{
    if ($format !== 'brand_export') {
        return $data;
    }

    $notes = trim((string) ($data['notes'] ?? ''));
    $rawUrl = trim((string) ($data['url'] ?? ''));
    $url = normalize_url($rawUrl);
    if ($rawUrl !== '' && $url === '') {
        $notes = trim($notes . "\nOriginal URL field: " . $rawUrl);
    }

    return [
        'type' => 'brand',
        'name' => $data['rn'] ?? '',
        'brand_name' => '',
        'category' => $data['category'] ?? '',
        'description' => '',
        'url' => $url,
        'image_url' => '',
        'featured' => 0,
        'company_location' => $data['company location'] ?? '',
        'manufacturing_location' => $data['manufacturing location'] ?? '',
        'warranty' => $data['warranty'] ?? '',
        'notes' => $notes,
    ];
}

function normalize_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(\/.*)?$/i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    return $url;
}

function import_csv_row(array $data): void
{
    $type = strtolower(trim((string) ($data['type'] ?? '')));
    $name = trim((string) ($data['name'] ?? ''));
    if (!in_array($type, ['brand', 'item'], true)) {
        throw new InvalidArgumentException('type must be brand or item.');
    }
    if ($name === '') {
        throw new InvalidArgumentException('name is required.');
    }

    if ($type === 'brand') {
        $brandId = upsert_brand_from_csv($data);
        save_scores('brand', $brandId, csv_scores($data));
        return;
    }

    $brandName = trim((string) ($data['brand_name'] ?? ''));
    if ($brandName === '') {
        throw new InvalidArgumentException('brand_name is required for item rows.');
    }

    $brandId = ensure_brand_by_name($brandName);
    $itemId = upsert_item_from_csv($data, $brandId);
    save_scores('item', $itemId, csv_scores($data));
}

function upsert_brand_from_csv(array $data): int
{
    $name = trim((string) $data['name']);
    $stmt = db()->prepare('SELECT id FROM brands WHERE lower(name) = lower(:name) LIMIT 1');
    $stmt->execute(['name' => $name]);
    $existing = $stmt->fetchColumn();

    return save_brand([
        'name' => $name,
        'category' => $data['category'] ?? '',
        'description' => $data['description'] ?? '',
        'url' => $data['url'] ?? '',
        'image_url' => $data['image_url'] ?? '',
        'company_location' => $data['company_location'] ?? '',
        'manufacturing_location' => $data['manufacturing_location'] ?? '',
        'warranty' => $data['warranty'] ?? '',
        'notes' => $data['notes'] ?? '',
        'featured' => $data['featured'] ?? 0,
    ], $existing ? (int) $existing : null);
}

function ensure_brand_by_name(string $name): int
{
    $stmt = db()->prepare('SELECT id FROM brands WHERE lower(name) = lower(:name) LIMIT 1');
    $stmt->execute(['name' => $name]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    return save_brand([
        'name' => $name,
        'category' => '',
        'description' => '',
        'url' => '',
        'image_url' => '',
        'company_location' => '',
        'manufacturing_location' => '',
        'warranty' => '',
        'notes' => '',
        'featured' => 0,
    ]);
}

function upsert_item_from_csv(array $data, int $brandId): int
{
    $name = trim((string) $data['name']);
    $stmt = db()->prepare('SELECT id FROM items WHERE brand_id = :brand_id AND name = :name LIMIT 1');
    $stmt->execute(['brand_id' => $brandId, 'name' => $name]);
    $existing = $stmt->fetchColumn();

    return save_item([
        'brand_id' => $brandId,
        'name' => $name,
        'category' => $data['category'] ?? '',
        'description' => $data['description'] ?? '',
        'url' => $data['url'] ?? '',
        'image_url' => $data['image_url'] ?? '',
        'featured' => $data['featured'] ?? 0,
    ], $existing ? (int) $existing : null);
}

function csv_scores(array $data): array
{
    $scores = [];
    foreach (all_criteria() as $criterion) {
        $column = $criterion['slug'] . '_score';
        $value = trim((string) ($data[$column] ?? ''));
        if ($value === '') {
            $scores[$criterion['slug']] = '';
            continue;
        }
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 5) {
            throw new InvalidArgumentException("{$column} must be between 0 and 5.");
        }
        $scores[$criterion['slug']] = (float) $value;
    }

    return $scores;
}
