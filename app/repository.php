<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function all_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
}

function all_criteria(): array
{
    return db()->query('SELECT * FROM score_criteria ORDER BY id')->fetchAll();
}

function find_or_create_category(?string $name): ?int
{
    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $slug = unique_slug('categories', slugify($name));
    $insert = db()->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');
    $insert->execute(['name' => $name, 'slug' => $slug]);
    return (int) db()->lastInsertId();
}

function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    $slug = $base;
    $i = 2;

    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = :slug";
        $params = ['slug' => $slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $base . '-' . $i;
        $i++;
    }
}

function entity_scores(string $type, int $entityId): array
{
    $stmt = db()->prepare(
        'SELECT sc.slug, sc.name, s.score
         FROM score_criteria sc
         LEFT JOIN scores s ON s.criterion_id = sc.id AND s.entity_type = :type AND s.entity_id = :entity_id
         ORDER BY sc.id'
    );
    $stmt->execute(['type' => $type, 'entity_id' => $entityId]);
    return $stmt->fetchAll();
}

function average_score(string $type, int $entityId): ?float
{
    $stmt = db()->prepare('SELECT AVG(score) FROM scores WHERE entity_type = :type AND entity_id = :entity_id');
    $stmt->execute(['type' => $type, 'entity_id' => $entityId]);
    $score = $stmt->fetchColumn();
    return $score === null ? null : (float) $score;
}

function save_scores(string $type, int $entityId, array $scores): void
{
    $criteria = all_criteria();
    $delete = db()->prepare('DELETE FROM scores WHERE entity_type = :type AND entity_id = :entity_id AND criterion_id = :criterion_id');
    $upsert = db()->prepare(
        'INSERT INTO scores (entity_type, entity_id, criterion_id, score)
         VALUES (:type, :entity_id, :criterion_id, :score)
         ON CONFLICT(entity_type, entity_id, criterion_id) DO UPDATE SET score = excluded.score'
    );

    foreach ($criteria as $criterion) {
        $raw = $scores[$criterion['slug']] ?? '';
        if ($raw === '' || $raw === null) {
            $delete->execute([
                'type' => $type,
                'entity_id' => $entityId,
                'criterion_id' => $criterion['id'],
            ]);
            continue;
        }

        $score = max(0, min(5, (float) $raw));
        $upsert->execute([
            'type' => $type,
            'entity_id' => $entityId,
            'criterion_id' => $criterion['id'],
            'score' => $score,
        ]);
    }
}

function list_brands(?string $search = null): array
{
    $params = [];
    $where = '';
    if ($search !== null && trim($search) !== '') {
        $where = 'WHERE b.name LIKE :search OR b.description LIKE :search OR c.name LIKE :search';
        $params['search'] = '%' . trim($search) . '%';
    }

    $stmt = db()->prepare(
        "SELECT b.*, c.name AS category_name, AVG(s.score) AS average_score
         FROM brands b
         LEFT JOIN categories c ON c.id = b.category_id
         LEFT JOIN scores s ON s.entity_type = 'brand' AND s.entity_id = b.id
         {$where}
         GROUP BY b.id
         ORDER BY b.featured DESC, b.name"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function list_items(?string $search = null): array
{
    $params = [];
    $where = '';
    if ($search !== null && trim($search) !== '') {
        $where = 'WHERE i.name LIKE :search OR i.description LIKE :search OR b.name LIKE :search OR c.name LIKE :search';
        $params['search'] = '%' . trim($search) . '%';
    }

    $stmt = db()->prepare(
        "SELECT i.*, b.name AS brand_name, b.slug AS brand_slug, c.name AS category_name, AVG(s.score) AS average_score
         FROM items i
         INNER JOIN brands b ON b.id = i.brand_id
         LEFT JOIN categories c ON c.id = i.category_id
         LEFT JOIN scores s ON s.entity_type = 'item' AND s.entity_id = i.id
         {$where}
         GROUP BY i.id
         ORDER BY i.featured DESC, i.name"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function store_like_where_sql(): string
{
    return "(
        lower(COALESCE(c.name, '')) IN ('marketplace', 'store', 'stores', 'retailer', 'retail')
        OR lower(b.name) LIKE '%store%'
        OR lower(b.description) LIKE '%store%'
        OR lower(b.description) LIKE '%stocks%'
        OR lower(b.description) LIKE '%stockist%'
        OR lower(b.description) LIKE '%retailer%'
        OR lower(b.description) LIKE '%marketplace%'
        OR lower(b.notes) LIKE '%store%'
        OR lower(b.notes) LIKE '%stocks%'
        OR lower(b.notes) LIKE '%stockist%'
        OR lower(b.notes) LIKE '%retailer%'
        OR lower(b.notes) LIKE '%marketplace%'
    )";
}

function list_stores(?int $limit = null): array
{
    $limitSql = $limit === null ? '' : ' LIMIT :limit';
    $stmt = db()->prepare(
        "SELECT
            b.id,
            b.name,
            b.slug,
            b.description,
            b.url,
            b.image_url,
            b.company_location,
            b.featured,
            c.name AS category_name,
            AVG(s.score) AS average_score,
            COUNT(DISTINCT i.id) AS item_count
        FROM brands b
        LEFT JOIN categories c ON c.id = b.category_id
        LEFT JOIN scores s ON s.entity_type = 'brand' AND s.entity_id = b.id
        LEFT JOIN items i ON i.brand_id = b.id
        WHERE " . store_like_where_sql() . "
        GROUP BY b.id
        ORDER BY b.featured DESC, average_score IS NULL ASC, average_score DESC, b.name ASC
        {$limitSql}"
    );
    if ($limit !== null) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function directory_results(array $filters): array
{
    $params = [];
    $where = [];
    $search = trim((string) ($filters['q'] ?? ''));
    $category = trim((string) ($filters['category'] ?? ''));

    if ($category !== '') {
        $where[] = 'c.slug = :category';
        $params['category'] = $category;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sort = $filters['sort'] ?? 'featured';
    $orderSql = match ($sort) {
        'score' => 'average_score IS NULL ASC, average_score DESC, b.name ASC',
        'newest' => 'b.created_at DESC',
        default => 'b.featured DESC, average_score IS NULL ASC, average_score DESC, b.name ASC',
    };

    $sql = "
        SELECT
            b.id,
            b.name,
            b.slug,
            b.description,
            b.url,
            b.image_url,
            b.company_location,
            b.manufacturing_location,
            b.featured,
            b.created_at,
            c.name AS category_name,
            c.slug AS category_slug,
            AVG(s.score) AS average_score,
            COUNT(DISTINCT i.id) AS item_count
        FROM brands b
        LEFT JOIN categories c ON c.id = b.category_id
        LEFT JOIN scores s ON s.entity_type = 'brand' AND s.entity_id = b.id
        LEFT JOIN items i ON i.brand_id = b.id
        {$whereSql}
        GROUP BY b.id
        ORDER BY {$orderSql}
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    if ($search === '') {
        return $results;
    }

    $ranked = [];
    foreach ($results as $result) {
        $score = fuzzy_brand_score($search, $result);
        if ($score > 0) {
            $result['_search_score'] = $score;
            $ranked[] = $result;
        }
    }

    usort($ranked, function (array $a, array $b) use ($sort): int {
        $searchOrder = ($b['_search_score'] <=> $a['_search_score']);
        if ($searchOrder !== 0) {
            return $searchOrder;
        }

        if ($sort === 'newest') {
            return strcmp((string) $b['created_at'], (string) $a['created_at']);
        }

        if ($sort === 'score') {
            return ((float) ($b['average_score'] ?? -1)) <=> ((float) ($a['average_score'] ?? -1));
        }

        return ((int) $b['featured'] <=> (int) $a['featured']) ?: strcmp($a['name'], $b['name']);
    });

    return $ranked;
}

function fuzzy_brand_score(string $query, array $brand): int
{
    $query = normalize_search_text($query);
    if ($query === '') {
        return 1;
    }

    $name = normalize_search_text((string) $brand['name']);
    $haystack = normalize_search_text(implode(' ', [
        $brand['name'] ?? '',
        $brand['description'] ?? '',
        $brand['category_name'] ?? '',
        $brand['company_location'] ?? '',
        $brand['manufacturing_location'] ?? '',
    ]));

    if (str_contains($name, $query)) {
        return 1000 - min(250, strlen($name) - strlen($query));
    }

    if (str_contains($haystack, $query)) {
        return 700;
    }

    similar_text($query, $name, $percent);
    $distance = levenshtein($query, $name);
    $maxLength = max(strlen($query), strlen($name), 1);
    $distanceRatio = $distance / $maxLength;

    if ($percent >= 72 || $distanceRatio <= 0.34) {
        return (int) round(550 + $percent - ($distanceRatio * 120));
    }

    foreach (preg_split('/\s+/', $name) ?: [] as $word) {
        if ($word === '') {
            continue;
        }

        similar_text($query, $word, $wordPercent);
        $wordDistance = levenshtein($query, $word);
        $wordRatio = $wordDistance / max(strlen($query), strlen($word), 1);
        if ($wordPercent >= 78 || $wordRatio <= 0.28) {
            return (int) round(480 + $wordPercent - ($wordRatio * 100));
        }
    }

    return 0;
}

function normalize_search_text(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
    return trim(preg_replace('/\s+/', ' ', $value) ?: '');
}

function search_suggestions(int $limit = 80): array
{
    $brandStmt = db()->prepare(
        "SELECT
            b.name,
            b.slug,
            b.description,
            c.name AS category_name,
            b.company_location,
            b.featured,
            b.popular,
            CASE WHEN " . store_like_where_sql() . " THEN 'Store' ELSE 'Brand' END AS suggestion_type,
            AVG(s.score) AS average_score
        FROM brands b
        LEFT JOIN categories c ON c.id = b.category_id
        LEFT JOIN scores s ON s.entity_type = 'brand' AND s.entity_id = b.id
        GROUP BY b.id
        ORDER BY b.popular DESC, b.featured DESC, average_score IS NULL ASC, average_score DESC, b.name ASC
        LIMIT :limit"
    );
    $brandStmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $brandStmt->execute();

    $brandSuggestions = array_map(static function (array $brand): array {
        return [
            'name' => $brand['name'],
            'href' => '/brands/' . $brand['slug'],
            'type' => $brand['suggestion_type'],
            'meta' => category_label($brand['category_name'] ?: 'Brand'),
            'description' => $brand['description'] ?: 'Brand details are being reviewed.',
            'popular' => (bool) $brand['popular'],
            'searchText' => normalize_search_text(implode(' ', [
                $brand['name'] ?? '',
                $brand['description'] ?? '',
                $brand['category_name'] ?? '',
                $brand['company_location'] ?? '',
            ])),
        ];
    }, $brandStmt->fetchAll());

    $itemStmt = db()->prepare(
        "SELECT
            i.name,
            i.slug,
            i.description,
            i.popular,
            c.name AS category_name,
            b.name AS brand_name,
            AVG(s.score) AS average_score
        FROM items i
        INNER JOIN brands b ON b.id = i.brand_id
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN scores s ON s.entity_type = 'item' AND s.entity_id = i.id
        GROUP BY i.id
        ORDER BY i.popular DESC, i.featured DESC, average_score IS NULL ASC, average_score DESC, i.name ASC
        LIMIT :limit"
    );
    $itemStmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $itemStmt->execute();

    $itemSuggestions = array_map(static function (array $item): array {
        return [
            'name' => $item['name'],
            'href' => '/items/' . $item['slug'],
            'type' => 'Item',
            'meta' => $item['brand_name'] ?: category_label($item['category_name'] ?: 'Item'),
            'description' => $item['description'] ?: 'Item details are being reviewed.',
            'popular' => (bool) $item['popular'],
            'searchText' => normalize_search_text(implode(' ', [
                $item['name'] ?? '',
                $item['description'] ?? '',
                $item['category_name'] ?? '',
                $item['brand_name'] ?? '',
            ])),
        ];
    }, $itemStmt->fetchAll());

    $suggestions = array_merge($brandSuggestions, $itemSuggestions);
    usort($suggestions, 'sort_search_suggestions');

    return array_slice($suggestions, 0, $limit);
}

function sort_search_suggestions(array $a, array $b): int
{
    return ((int) $b['popular'] <=> (int) $a['popular'])
        ?: strcmp($a['name'], $b['name']);
}

function featured_brands(int $limit = 6): array
{
    $stmt = db()->prepare(
        "SELECT
            b.id,
            b.name,
            b.slug,
            b.description,
            b.url,
            b.image_url,
            b.company_location,
            b.manufacturing_location,
            c.name AS category_name,
            AVG(s.score) AS average_score,
            COUNT(DISTINCT i.id) AS item_count
        FROM brands b
        LEFT JOIN categories c ON c.id = b.category_id
        LEFT JOIN scores s ON s.entity_type = 'brand' AND s.entity_id = b.id
        LEFT JOIN items i ON i.brand_id = b.id
        WHERE b.featured = 1
        GROUP BY b.id
        ORDER BY average_score IS NULL ASC, average_score DESC, b.name ASC
        LIMIT :limit"
    );
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function featured_stores(int $limit = 6): array
{
    return list_stores($limit);
}

function featured_items(int $limit = 6): array
{
    $stmt = db()->prepare(
        "SELECT i.*, b.name AS brand_name, b.slug AS brand_slug, c.name AS category_name, AVG(s.score) AS average_score
         FROM items i
         INNER JOIN brands b ON b.id = i.brand_id
         LEFT JOIN categories c ON c.id = i.category_id
         LEFT JOIN scores s ON s.entity_type = 'item' AND s.entity_id = i.id
         WHERE i.featured = 1
         GROUP BY i.id
         ORDER BY average_score IS NULL ASC, average_score DESC, i.name ASC
         LIMIT :limit"
    );
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function latest_news_article(): ?array
{
    $stmt = db()->query('SELECT * FROM news_articles ORDER BY published_at DESC, id DESC LIMIT 1');
    $article = $stmt->fetch();
    return $article ?: null;
}

function news_articles(): array
{
    return db()->query('SELECT * FROM news_articles ORDER BY published_at DESC, id DESC')->fetchAll();
}

function find_brand_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT b.*, c.name AS category_name, c.slug AS category_slug, AVG(s.score) AS average_score
         FROM brands b
         LEFT JOIN categories c ON c.id = b.category_id
         LEFT JOIN scores s ON s.entity_type = 'brand' AND s.entity_id = b.id
         WHERE b.slug = :slug
         GROUP BY b.id
         LIMIT 1"
    );
    $stmt->execute(['slug' => $slug]);
    $brand = $stmt->fetch();
    return $brand ?: null;
}

function find_item_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT i.*, b.name AS brand_name, b.slug AS brand_slug, c.name AS category_name, c.slug AS category_slug, AVG(s.score) AS average_score
         FROM items i
         INNER JOIN brands b ON b.id = i.brand_id
         LEFT JOIN categories c ON c.id = i.category_id
         LEFT JOIN scores s ON s.entity_type = 'item' AND s.entity_id = i.id
         WHERE i.slug = :slug
         GROUP BY i.id
         LIMIT 1"
    );
    $stmt->execute(['slug' => $slug]);
    $item = $stmt->fetch();
    return $item ?: null;
}

function find_brand_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM brands WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $brand = $stmt->fetch();
    return $brand ?: null;
}

function find_item_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();
    return $item ?: null;
}

function brand_items(int $brandId): array
{
    $stmt = db()->prepare(
        "SELECT i.*, c.name AS category_name, AVG(s.score) AS average_score
         FROM items i
         LEFT JOIN categories c ON c.id = i.category_id
         LEFT JOIN scores s ON s.entity_type = 'item' AND s.entity_id = i.id
         WHERE i.brand_id = :brand_id
         GROUP BY i.id
         ORDER BY i.featured DESC, i.name"
    );
    $stmt->execute(['brand_id' => $brandId]);
    return $stmt->fetchAll();
}

function save_brand(array $data, ?int $id = null): int
{
    $categoryId = find_or_create_category($data['category'] ?? '');
    $baseSlug = slugify($data['name'] ?? '');
    $slug = unique_slug('brands', $baseSlug, $id);
    $params = [
        'name' => trim((string) $data['name']),
        'slug' => $slug,
        'category_id' => $categoryId,
        'description' => trim((string) ($data['description'] ?? '')),
        'url' => trim((string) ($data['url'] ?? '')),
        'image_url' => trim((string) ($data['image_url'] ?? '')),
        'company_location' => strtoupper(trim((string) ($data['company_location'] ?? ''))),
        'manufacturing_location' => strtoupper(trim((string) ($data['manufacturing_location'] ?? ''))),
        'warranty' => trim((string) ($data['warranty'] ?? '')),
        'notes' => trim((string) ($data['notes'] ?? '')),
        'featured' => bool_from_input($data['featured'] ?? 0),
        'popular' => bool_from_input($data['popular'] ?? 0),
    ];

    if ($params['url'] !== '' && ($params['description'] === '' || $params['image_url'] === '')) {
        $metadata = discover_og_metadata($params['url']);
        if ($params['description'] === '') {
            $params['description'] = $metadata['description'];
        }
        if ($params['image_url'] === '') {
            $params['image_url'] = $metadata['image'];
        }
    }

    if ($id === null) {
        $stmt = db()->prepare(
            'INSERT INTO brands (name, slug, category_id, description, url, image_url, company_location, manufacturing_location, warranty, notes, featured, popular)
             VALUES (:name, :slug, :category_id, :description, :url, :image_url, :company_location, :manufacturing_location, :warranty, :notes, :featured, :popular)'
        );
        $stmt->execute($params);
        return (int) db()->lastInsertId();
    }

    $params['id'] = $id;
    $stmt = db()->prepare(
        'UPDATE brands
         SET name = :name, slug = :slug, category_id = :category_id, description = :description,
             url = :url, image_url = :image_url, company_location = :company_location,
             manufacturing_location = :manufacturing_location, warranty = :warranty,
             notes = :notes, featured = :featured, popular = :popular, updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $stmt->execute($params);
    return $id;
}

function save_item(array $data, ?int $id = null): int
{
    $categoryId = find_or_create_category($data['category'] ?? '');
    $brandId = (int) ($data['brand_id'] ?? 0);
    $brand = find_brand_by_id($brandId);
    if (!$brand) {
        throw new InvalidArgumentException('Item requires a valid brand.');
    }

    $baseSlug = slugify($brand['name'] . '-' . ($data['name'] ?? ''));
    $slug = unique_slug('items', $baseSlug, $id);
    $params = [
        'brand_id' => $brandId,
        'name' => trim((string) $data['name']),
        'slug' => $slug,
        'category_id' => $categoryId,
        'description' => trim((string) ($data['description'] ?? '')),
        'url' => trim((string) ($data['url'] ?? '')),
        'image_url' => trim((string) ($data['image_url'] ?? '')),
        'featured' => bool_from_input($data['featured'] ?? 0),
        'popular' => bool_from_input($data['popular'] ?? 0),
    ];

    if ($id === null) {
        $stmt = db()->prepare(
            'INSERT INTO items (brand_id, name, slug, category_id, description, url, image_url, featured, popular)
             VALUES (:brand_id, :name, :slug, :category_id, :description, :url, :image_url, :featured, :popular)'
        );
        $stmt->execute($params);
        return (int) db()->lastInsertId();
    }

    $params['id'] = $id;
    $stmt = db()->prepare(
        'UPDATE items
         SET brand_id = :brand_id, name = :name, slug = :slug, category_id = :category_id,
             description = :description, url = :url, image_url = :image_url, featured = :featured, popular = :popular,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $stmt->execute($params);
    return $id;
}
