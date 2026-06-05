<?php

declare(strict_types=1);

function config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = config();
    $storageDir = dirname($config['database_path']);

    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $config['database_path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    migrate($pdo);
    seed_defaults($pdo);

    return $pdo;
}

function migrate(PDO $pdo): void
{
    $schema = file_get_contents(config()['base_path'] . '/database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Unable to read database schema.');
    }

    $pdo->exec($schema);
    ensure_brand_columns($pdo);
    ensure_item_columns($pdo);
}

function ensure_brand_columns(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(brands)')->fetchAll();
    $existing = array_column($columns, 'name');
    $needed = [
        'company_location' => "ALTER TABLE brands ADD COLUMN company_location TEXT NOT NULL DEFAULT ''",
        'manufacturing_location' => "ALTER TABLE brands ADD COLUMN manufacturing_location TEXT NOT NULL DEFAULT ''",
        'warranty' => "ALTER TABLE brands ADD COLUMN warranty TEXT NOT NULL DEFAULT ''",
        'notes' => "ALTER TABLE brands ADD COLUMN notes TEXT NOT NULL DEFAULT ''",
        'popular' => "ALTER TABLE brands ADD COLUMN popular INTEGER NOT NULL DEFAULT 0",
    ];

    foreach ($needed as $column => $sql) {
        if (!in_array($column, $existing, true)) {
            $pdo->exec($sql);
        }
    }
}

function ensure_item_columns(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(items)')->fetchAll();
    $existing = array_column($columns, 'name');
    $needed = [
        'popular' => "ALTER TABLE items ADD COLUMN popular INTEGER NOT NULL DEFAULT 0",
    ];

    foreach ($needed as $column => $sql) {
        if (!in_array($column, $existing, true)) {
            $pdo->exec($sql);
        }
    }
}

function seed_defaults(PDO $pdo): void
{
    $criteria = [
        ['sustainability', 'Sustainability'],
        ['ethics', 'Ethics'],
        ['durability', 'Durability'],
        ['repairability', 'Repairability'],
        ['transparency', 'Transparency'],
        ['packaging', 'Packaging'],
        ['value', 'Value'],
    ];

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO score_criteria (slug, name) VALUES (:slug, :name)');
    foreach ($criteria as [$slug, $name]) {
        $stmt->execute(['slug' => $slug, 'name' => $name]);
    }

    $news = $pdo->prepare(
        'INSERT OR IGNORE INTO news_articles (title, slug, excerpt, body, published_at)
         VALUES (:title, :slug, :excerpt, :body, :published_at)'
    );
    $news->execute([
        'title' => 'Get Quality Stuff is starting with a brand-first directory',
        'slug' => 'brand-first-directory',
        'excerpt' => 'The first version of Get Quality Stuff focuses on finding better brands, with items and stores living inside each brand profile.',
        'body' => 'Get Quality Stuff is being shaped around a simple idea: start with the brand. The homepage now puts search first, highlights a small set of featured brands, and keeps deeper item discovery inside brand pages.',
        'published_at' => date('Y-m-d H:i:s'),
    ]);

    $config = config();
    if ($config['admin_email'] === '' || $config['admin_password'] === '') {
        return;
    }

    $existing = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $existing->execute(['email' => $config['admin_email']]);

    if (!$existing->fetch()) {
        $insert = $pdo->prepare(
            'INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)'
        );
        $insert->execute([
            'email' => $config['admin_email'],
            'password_hash' => password_hash($config['admin_password'], PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);
    }
}
