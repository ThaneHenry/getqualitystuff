<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/item_images.php';

function all_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
}

function all_criteria(): array
{
    return db()->query('SELECT * FROM score_criteria ORDER BY id')->fetchAll();
}

function all_awards(): array
{
    return db()->query('SELECT * FROM awards ORDER BY id')->fetchAll();
}

function brand_awards(int $brandId): array
{
    $stmt = db()->prepare(
        'SELECT a.*, ba.awarded_at, ba.note
         FROM brand_awards ba
         INNER JOIN awards a ON a.id = ba.award_id
         WHERE ba.brand_id = :brand_id
         ORDER BY a.id'
    );
    $stmt->execute(['brand_id' => $brandId]);
    return $stmt->fetchAll();
}

function save_brand_awards(int $brandId, array $awardIds): void
{
    $validIds = array_map(static fn (array $award): int => (int) $award['id'], all_awards());
    $selectedIds = array_values(array_unique(array_intersect(
        $validIds,
        array_map('intval', $awardIds)
    )));

    $delete = db()->prepare('DELETE FROM brand_awards WHERE brand_id = :brand_id');
    $delete->execute(['brand_id' => $brandId]);

    $insert = db()->prepare('INSERT INTO brand_awards (brand_id, award_id) VALUES (:brand_id, :award_id)');
    foreach ($selectedIds as $awardId) {
        $insert->execute(['brand_id' => $brandId, 'award_id' => $awardId]);
    }
}

function site_capabilities(): array
{
    return [
        'items' => (int) db()->query('SELECT COUNT(*) FROM items')->fetchColumn() > 0,
        'scores' => (int) db()->query('SELECT COUNT(*) FROM scores')->fetchColumn() > 0,
    ];
}

function directory_filter_options(): array
{
    $companyLocations = db()->query("SELECT DISTINCT company_location AS value FROM brands WHERE company_location != '' ORDER BY company_location")->fetchAll();
    $manufacturingLocations = db()->query("SELECT DISTINCT manufacturing_location AS value FROM brands WHERE manufacturing_location != '' ORDER BY manufacturing_location")->fetchAll();
    $validCountryCodes = static fn (array $rows): array => array_values(array_filter(
        array_column($rows, 'value'),
        static fn (string $value): bool => (bool) preg_match('/^[A-Z]{2}$/', $value)
    ));
    return [
        'categories' => public_categories(),
        'company_locations' => $validCountryCodes($companyLocations),
        'manufacturing_locations' => $validCountryCodes($manufacturingLocations),
        'statuses' => assessment_statuses(),
    ];
}

function assessment_sources(string $type, int $entityId): array
{
    $stmt = db()->prepare('SELECT * FROM assessment_sources WHERE entity_type = :type AND entity_id = :entity_id ORDER BY id');
    $stmt->execute(['type' => $type, 'entity_id' => $entityId]);
    return $stmt->fetchAll();
}

function save_assessment_sources(string $type, int $entityId, string $raw): void
{
    $delete = db()->prepare('DELETE FROM assessment_sources WHERE entity_type = :type AND entity_id = :entity_id');
    $delete->execute(['type' => $type, 'entity_id' => $entityId]);
    $insert = db()->prepare('INSERT INTO assessment_sources (entity_type, entity_id, label, url) VALUES (:type, :entity_id, :label, :url)');
    foreach (editorial_lines($raw) as $line) {
        [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
        if ($url === '') {
            $url = $label;
            $label = '';
        }
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $insert->execute(['type' => $type, 'entity_id' => $entityId, 'label' => $label, 'url' => $url]);
        }
    }
}

function assessment_sources_editor_value(string $type, int $entityId): string
{
    return implode("\n", array_map(
        static fn (array $source): string => ($source['label'] !== '' ? $source['label'] . ' | ' : '') . $source['url'],
        assessment_sources($type, $entityId)
    ));
}

function submit_public_feedback(array $data): void
{
    $type = in_array($data['type'] ?? '', ['suggest_brand', 'outdated_information'], true) ? $data['type'] : 'suggest_brand';
    $entityType = in_array($data['entity_type'] ?? '', ['brand', 'item'], true) ? $data['entity_type'] : null;
    $message = trim((string) ($data['message'] ?? ''));
    if ($message === '') {
        throw new InvalidArgumentException('Please include a message.');
    }
    $email = trim((string) ($data['contact_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address or leave it blank.');
    }
    $stmt = db()->prepare(
        'INSERT INTO public_feedback (type, entity_type, entity_id, contact_email, message)
         VALUES (:type, :entity_type, :entity_id, :contact_email, :message)'
    );
    $stmt->execute([
        'type' => $type,
        'entity_type' => $entityType,
        'entity_id' => $entityType ? (int) ($data['entity_id'] ?? 0) : null,
        'contact_email' => $email,
        'message' => $message,
    ]);
}

function public_feedback_entries(): array
{
    return db()->query(
        "SELECT f.*, CASE WHEN f.entity_type = 'brand' THEN b.name WHEN f.entity_type = 'item' THEN i.name END AS entity_name
         FROM public_feedback f
         LEFT JOIN brands b ON f.entity_type = 'brand' AND b.id = f.entity_id
         LEFT JOIN items i ON f.entity_type = 'item' AND i.id = f.entity_id
         ORDER BY CASE f.status WHEN 'new' THEN 0 WHEN 'reviewing' THEN 1 ELSE 2 END, f.created_at DESC"
    )->fetchAll();
}

function update_public_feedback_status(int $id, string $status): void
{
    if (!in_array($status, ['new', 'reviewing', 'resolved'], true)) {
        throw new InvalidArgumentException('Invalid feedback status.');
    }
    $stmt = db()->prepare('UPDATE public_feedback SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);
}

function find_or_create_category(?string $name): ?int
{
    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT id FROM categories WHERE lower(name) = lower(:name) LIMIT 1');
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
        "SELECT i.*, b.name AS brand_name, b.slug AS brand_slug, b.company_location, b.manufacturing_location, b.warranty,
                c.name AS category_name, c.slug AS category_slug, AVG(s.score) AS average_score
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
            b.manufacturing_location,
            b.delivery_locations,
            b.warranty,
            b.assessment_status,
            b.assessment_summary,
            b.assessment_strengths,
            b.assessment_caveats,
            b.reviewed_at,
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

function filter_directory_entries(array $entries, array $filters): array
{
    $category = trim((string) ($filters['category'] ?? ''));
    $mode = trim((string) ($filters['mode'] ?? 'all'));
    $status = trim((string) ($filters['status'] ?? ''));
    $company = trim((string) ($filters['company'] ?? ''));
    $manufacturing = trim((string) ($filters['manufacturing'] ?? ''));
    $warranty = trim((string) ($filters['warranty'] ?? ''));

    if ($category !== '') {
        $entries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['category_slug'] ?? '') === $category
        ));
    }
    $entries = array_values(array_filter($entries, static function (array $entry) use ($status, $company, $manufacturing, $warranty): bool {
        return ($status === '' || ($entry['assessment_status'] ?? 'listed') === $status)
            && ($company === '' || ($entry['company_location'] ?? '') === $company)
            && ($manufacturing === '' || ($entry['manufacturing_location'] ?? '') === $manufacturing)
            && ($warranty !== 'yes' || trim((string) ($entry['warranty'] ?? '')) !== '');
    }));

    if ($mode === 'featured') {
        $entries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => (int) ($entry['featured'] ?? 0) === 1
        ));
    }

    usort($entries, static function (array $a, array $b) use ($mode): int {
        return match ($mode) {
            'score' => ((float) ($b['average_score'] ?? -1)) <=> ((float) ($a['average_score'] ?? -1)),
            'newest' => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')),
            default => ((int) ($b['featured'] ?? 0) <=> (int) ($a['featured'] ?? 0))
                ?: strcmp((string) $a['name'], (string) $b['name']),
        };
    });

    return $entries;
}

function directory_results(array $filters): array
{
    $params = [];
    $where = [];
    $search = trim((string) ($filters['q'] ?? ''));
    $category = trim((string) ($filters['category'] ?? ''));
    $mode = trim((string) ($filters['mode'] ?? 'all'));
    $status = trim((string) ($filters['status'] ?? ''));
    $company = trim((string) ($filters['company'] ?? ''));
    $manufacturing = trim((string) ($filters['manufacturing'] ?? ''));
    $warranty = trim((string) ($filters['warranty'] ?? ''));

    if ($category !== '') {
        $where[] = 'c.slug = :category';
        $params['category'] = $category;
    }

    if ($mode === 'featured') {
        $where[] = 'b.featured = 1';
    }
    if ($status !== '') {
        $where[] = 'b.assessment_status = :status';
        $params['status'] = $status;
    }
    if ($company !== '') {
        $where[] = 'b.company_location = :company';
        $params['company'] = $company;
    }
    if ($manufacturing !== '') {
        $where[] = 'b.manufacturing_location = :manufacturing';
        $params['manufacturing'] = $manufacturing;
    }
    if ($warranty === 'yes') {
        $where[] = "b.warranty != ''";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderSql = match ($mode) {
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
            b.delivery_locations,
            b.warranty,
            b.assessment_status,
            b.assessment_summary,
            b.assessment_strengths,
            b.assessment_caveats,
            b.reviewed_at,
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

    usort($ranked, function (array $a, array $b) use ($mode): int {
        $searchOrder = ($b['_search_score'] <=> $a['_search_score']);
        if ($searchOrder !== 0) {
            return $searchOrder;
        }

        if ($mode === 'newest') {
            return strcmp((string) $b['created_at'], (string) $a['created_at']);
        }

        if ($mode === 'score') {
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
        $brand['assessment_summary'] ?? '',
        $brand['assessment_strengths'] ?? '',
        $brand['assessment_caveats'] ?? '',
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
            b.delivery_locations,
            b.assessment_status,
            b.assessment_summary,
            b.assessment_strengths,
            b.assessment_caveats,
            b.reviewed_at,
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
        "SELECT i.*, b.name AS brand_name, b.slug AS brand_slug, b.company_location, b.manufacturing_location, c.name AS category_name, AVG(s.score) AS average_score
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

function item_purchase_links(int $itemId): array
{
    $stmt = db()->prepare(
        'SELECT pl.*, b.name AS listing_name, b.slug AS listing_slug
         FROM item_purchase_links pl
         INNER JOIN brands b ON b.id = pl.listing_id
         WHERE pl.item_id = :item_id
         ORDER BY b.name'
    );
    $stmt->execute(['item_id' => $itemId]);
    return $stmt->fetchAll();
}

function purchase_links_editor_value(int $itemId): string
{
    return implode("\n", array_map(
        static fn (array $link): string => $link['listing_name'] . ' | ' . $link['url'],
        item_purchase_links($itemId)
    ));
}

function save_item_purchase_links(int $itemId, string $raw): void
{
    $links = [];
    foreach (editorial_lines($raw) as $line) {
        [$listingName, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
        if ($listingName === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }

        $stmt = db()->prepare('SELECT id FROM brands WHERE lower(name) = lower(:name) LIMIT 1');
        $stmt->execute(['name' => $listingName]);
        $listingId = $stmt->fetchColumn();
        if (!$listingId) {
            throw new InvalidArgumentException("Purchase-link listing does not exist: {$listingName}.");
        }
        $links[(int) $listingId] = $url;
    }

    $delete = db()->prepare('DELETE FROM item_purchase_links WHERE item_id = :item_id');
    $delete->execute(['item_id' => $itemId]);
    $insert = db()->prepare(
        'INSERT INTO item_purchase_links (item_id, listing_id, url) VALUES (:item_id, :listing_id, :url)'
    );
    foreach ($links as $listingId => $url) {
        $insert->execute(['item_id' => $itemId, 'listing_id' => $listingId, 'url' => $url]);
    }
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

function is_entry_saved(int $userId, string $entityType, int $entityId): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM saved_entries WHERE user_id = :user_id AND entity_type = :entity_type AND entity_id = :entity_id'
    );
    $stmt->execute(['user_id' => $userId, 'entity_type' => $entityType, 'entity_id' => $entityId]);
    return (bool) $stmt->fetchColumn();
}

function saved_entry_keys(int $userId): array
{
    $stmt = db()->prepare('SELECT entity_type, entity_id FROM saved_entries WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    return array_fill_keys(array_map(
        static fn (array $entry): string => $entry['entity_type'] . ':' . $entry['entity_id'],
        $stmt->fetchAll()
    ), true);
}

function set_entry_saved(int $userId, string $entityType, int $entityId, bool $saved): void
{
    if (!in_array($entityType, ['brand', 'item'], true)) {
        throw new InvalidArgumentException('Invalid saved entry type.');
    }

    if ($saved) {
        $stmt = db()->prepare(
            'INSERT OR IGNORE INTO saved_entries (user_id, entity_type, entity_id)
             VALUES (:user_id, :entity_type, :entity_id)'
        );
    } else {
        $stmt = db()->prepare(
            'DELETE FROM saved_entries WHERE user_id = :user_id AND entity_type = :entity_type AND entity_id = :entity_id'
        );
    }
    $stmt->execute(['user_id' => $userId, 'entity_type' => $entityType, 'entity_id' => $entityId]);
}

function record_recent_view(int $userId, string $entityType, int $entityId): void
{
    $stmt = db()->prepare(
        'INSERT INTO recently_viewed (user_id, entity_type, entity_id, viewed_at)
         VALUES (:user_id, :entity_type, :entity_id, CURRENT_TIMESTAMP)
         ON CONFLICT(user_id, entity_type, entity_id) DO UPDATE SET viewed_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute(['user_id' => $userId, 'entity_type' => $entityType, 'entity_id' => $entityId]);
}

function account_entries(int $userId, string $source = 'saved', int $limit = 24): array
{
    $table = $source === 'recent' ? 'recently_viewed' : 'saved_entries';
    $dateColumn = $source === 'recent' ? 'viewed_at' : 'created_at';
    $stmt = db()->prepare(
        "SELECT e.entity_type, e.entity_id, e.{$dateColumn} AS activity_at,
                CASE WHEN e.entity_type = 'brand' THEN b.name ELSE i.name END AS name,
                CASE WHEN e.entity_type = 'brand' THEN b.slug ELSE i.slug END AS slug,
                CASE WHEN e.entity_type = 'brand' THEN b.description ELSE i.description END AS description,
                CASE WHEN e.entity_type = 'brand' THEN b.image_url ELSE i.image_url END AS image_url,
                c.name AS category_name,
                ib.name AS brand_name
         FROM {$table} e
         LEFT JOIN brands b ON e.entity_type = 'brand' AND b.id = e.entity_id
         LEFT JOIN items i ON e.entity_type = 'item' AND i.id = e.entity_id
         LEFT JOIN brands ib ON ib.id = i.brand_id
         LEFT JOIN categories c ON c.id = COALESCE(b.category_id, i.category_id)
         WHERE e.user_id = :user_id AND (b.id IS NOT NULL OR i.id IS NOT NULL)
         ORDER BY e.{$dateColumn} DESC
         LIMIT :limit"
    );
    $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function user_preferences(int $userId): array
{
    $stmt = db()->prepare('SELECT category_ids, criterion_slugs FROM user_preferences WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $preferences = $stmt->fetch();
    return [
        'category_ids' => $preferences && $preferences['category_ids'] !== ''
            ? array_map('intval', explode(',', $preferences['category_ids']))
            : [],
        'criterion_slugs' => $preferences && $preferences['criterion_slugs'] !== ''
            ? explode(',', $preferences['criterion_slugs'])
            : [],
    ];
}

function save_user_preferences(int $userId, array $categoryIds, array $criterionSlugs): void
{
    $validCategoryIds = array_map('intval', array_column(all_categories(), 'id'));
    $validCriterionSlugs = array_column(all_criteria(), 'slug');
    $categoryIds = array_values(array_intersect($validCategoryIds, array_map('intval', $categoryIds)));
    $criterionSlugs = array_values(array_intersect($validCriterionSlugs, array_map('strval', $criterionSlugs)));

    $stmt = db()->prepare(
        'INSERT INTO user_preferences (user_id, category_ids, criterion_slugs)
         VALUES (:user_id, :category_ids, :criterion_slugs)
         ON CONFLICT(user_id) DO UPDATE SET
            category_ids = excluded.category_ids,
            criterion_slugs = excluded.criterion_slugs,
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'user_id' => $userId,
        'category_ids' => implode(',', $categoryIds),
        'criterion_slugs' => implode(',', $criterionSlugs),
    ]);
}

function personalized_brands(int $userId, int $limit = 6): array
{
    $preferences = user_preferences($userId);
    if (!$preferences['category_ids'] && !$preferences['criterion_slugs']) {
        return featured_brands($limit);
    }

    $categoryIds = $preferences['category_ids'] ?: [0];
    $criterionSlugs = $preferences['criterion_slugs'] ?: [''];
    $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $criterionPlaceholders = implode(',', array_fill(0, count($criterionSlugs), '?'));
    $sql = "SELECT b.*, c.name AS category_name, AVG(s.score) AS average_score, COUNT(DISTINCT i.id) AS item_count,
                   CASE WHEN b.category_id IN ({$categoryPlaceholders}) THEN 1 ELSE 0 END AS category_match,
                   COUNT(DISTINCT CASE WHEN sc.slug IN ({$criterionPlaceholders}) AND s.score >= 3.5 THEN sc.slug END) AS criterion_matches
            FROM brands b
            LEFT JOIN categories c ON c.id = b.category_id
            LEFT JOIN scores s ON s.entity_type = 'brand' AND s.entity_id = b.id
            LEFT JOIN score_criteria sc ON sc.id = s.criterion_id
            LEFT JOIN items i ON i.brand_id = b.id
            GROUP BY b.id
            ORDER BY category_match DESC, criterion_matches DESC, average_score IS NULL ASC, average_score DESC, b.featured DESC
            LIMIT ?";
    $stmt = db()->prepare($sql);
    $position = 1;
    foreach (array_merge($categoryIds, $criterionSlugs) as $value) {
        $stmt->bindValue($position++, $value);
    }
    $stmt->bindValue($position, $limit, PDO::PARAM_INT);
    $stmt->execute();
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
        'delivery_locations' => strtoupper(trim((string) ($data['delivery_locations'] ?? ''))),
        'warranty' => trim((string) ($data['warranty'] ?? '')),
        'notes' => trim((string) ($data['notes'] ?? '')),
        'assessment_status' => array_key_exists((string) ($data['assessment_status'] ?? ''), assessment_statuses()) ? $data['assessment_status'] : 'listed',
        'assessment_summary' => trim((string) ($data['assessment_summary'] ?? '')),
        'assessment_strengths' => trim((string) ($data['assessment_strengths'] ?? '')),
        'assessment_caveats' => trim((string) ($data['assessment_caveats'] ?? '')),
        'reviewed_at' => trim((string) ($data['reviewed_at'] ?? '')) ?: null,
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
            'INSERT INTO brands (name, slug, category_id, description, url, image_url, company_location, manufacturing_location, delivery_locations, warranty, notes, assessment_status, assessment_summary, assessment_strengths, assessment_caveats, reviewed_at, featured, popular)
             VALUES (:name, :slug, :category_id, :description, :url, :image_url, :company_location, :manufacturing_location, :delivery_locations, :warranty, :notes, :assessment_status, :assessment_summary, :assessment_strengths, :assessment_caveats, :reviewed_at, :featured, :popular)'
        );
        $stmt->execute($params);
        return (int) db()->lastInsertId();
    }

    $params['id'] = $id;
    $stmt = db()->prepare(
        'UPDATE brands
         SET name = :name, slug = :slug, category_id = :category_id, description = :description,
             url = :url, image_url = :image_url, company_location = :company_location,
             manufacturing_location = :manufacturing_location, delivery_locations = :delivery_locations, warranty = :warranty,
             notes = :notes, assessment_status = :assessment_status, assessment_summary = :assessment_summary,
             assessment_strengths = :assessment_strengths, assessment_caveats = :assessment_caveats, reviewed_at = :reviewed_at,
             featured = :featured, popular = :popular, updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $stmt->execute($params);
    return $id;
}

function save_item(array $data, ?int $id = null): int
{
    $existingItem = $id === null ? null : find_item_by_id($id);
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
        'warranty' => trim((string) ($data['warranty'] ?? '')),
        'warranty_details' => trim((string) ($data['warranty_details'] ?? '')),
        'assessment_status' => array_key_exists((string) ($data['assessment_status'] ?? ''), assessment_statuses()) ? $data['assessment_status'] : 'listed',
        'assessment_summary' => trim((string) ($data['assessment_summary'] ?? '')),
        'assessment_strengths' => trim((string) ($data['assessment_strengths'] ?? '')),
        'assessment_caveats' => trim((string) ($data['assessment_caveats'] ?? '')),
        'reviewed_at' => trim((string) ($data['reviewed_at'] ?? '')) ?: null,
        'featured' => bool_from_input($data['featured'] ?? 0),
        'popular' => bool_from_input($data['popular'] ?? 0),
    ];

    if ($id === null) {
        $stmt = db()->prepare(
            'INSERT INTO items (brand_id, name, slug, category_id, description, url, image_url, warranty, warranty_details, assessment_status, assessment_summary, assessment_strengths, assessment_caveats, reviewed_at, featured, popular)
             VALUES (:brand_id, :name, :slug, :category_id, :description, :url, :image_url, :warranty, :warranty_details, :assessment_status, :assessment_summary, :assessment_strengths, :assessment_caveats, :reviewed_at, :featured, :popular)'
        );
        $stmt->execute($params);
        $itemId = (int) db()->lastInsertId();
        schedule_item_image_processing($itemId, $params['image_url'], true);
        return $itemId;
    }

    $params['id'] = $id;
    $stmt = db()->prepare(
        'UPDATE items
         SET brand_id = :brand_id, name = :name, slug = :slug, category_id = :category_id,
             description = :description, url = :url, image_url = :image_url, warranty = :warranty,
             warranty_details = :warranty_details, featured = :featured, popular = :popular,
             assessment_status = :assessment_status, assessment_summary = :assessment_summary,
             assessment_strengths = :assessment_strengths, assessment_caveats = :assessment_caveats, reviewed_at = :reviewed_at,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $stmt->execute($params);
    schedule_item_image_processing($id, $params['image_url'], (string) ($existingItem['image_url'] ?? '') !== $params['image_url']);
    return $id;
}
