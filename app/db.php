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
    ensure_user_columns($pdo);
    ensure_auth_tables($pdo);
    ensure_brand_columns($pdo);
    ensure_item_columns($pdo);
    ensure_assessment_tables($pdo);
    ensure_award_tables($pdo);
}

function ensure_auth_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_identities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            provider TEXT NOT NULL,
            provider_subject TEXT NOT NULL,
            email TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (provider, provider_subject),
            UNIQUE (user_id, provider),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_user_identities_user ON user_identities(user_id);"
    );
}

function ensure_user_columns(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    $existing = array_column($columns, 'name');
    $needed = [
        'role' => "ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'member'",
        'email_verified_at' => 'ALTER TABLE users ADD COLUMN email_verified_at TEXT',
    ];

    foreach ($needed as $column => $sql) {
        if (!in_array($column, $existing, true)) {
            $pdo->exec($sql);
        }
    }
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
        'assessment_status' => "ALTER TABLE brands ADD COLUMN assessment_status TEXT NOT NULL DEFAULT 'listed'",
        'assessment_summary' => "ALTER TABLE brands ADD COLUMN assessment_summary TEXT NOT NULL DEFAULT ''",
        'assessment_strengths' => "ALTER TABLE brands ADD COLUMN assessment_strengths TEXT NOT NULL DEFAULT ''",
        'assessment_caveats' => "ALTER TABLE brands ADD COLUMN assessment_caveats TEXT NOT NULL DEFAULT ''",
        'reviewed_at' => "ALTER TABLE brands ADD COLUMN reviewed_at TEXT",
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
        'assessment_status' => "ALTER TABLE items ADD COLUMN assessment_status TEXT NOT NULL DEFAULT 'listed'",
        'assessment_summary' => "ALTER TABLE items ADD COLUMN assessment_summary TEXT NOT NULL DEFAULT ''",
        'assessment_strengths' => "ALTER TABLE items ADD COLUMN assessment_strengths TEXT NOT NULL DEFAULT ''",
        'assessment_caveats' => "ALTER TABLE items ADD COLUMN assessment_caveats TEXT NOT NULL DEFAULT ''",
        'reviewed_at' => "ALTER TABLE items ADD COLUMN reviewed_at TEXT",
    ];

    foreach ($needed as $column => $sql) {
        if (!in_array($column, $existing, true)) {
            $pdo->exec($sql);
        }
    }
}

function ensure_assessment_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS assessment_sources (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type TEXT NOT NULL CHECK (entity_type IN ('brand', 'item')),
            entity_id INTEGER NOT NULL,
            label TEXT NOT NULL DEFAULT '',
            url TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_assessment_sources_entity ON assessment_sources(entity_type, entity_id);
        CREATE TABLE IF NOT EXISTS public_feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL CHECK (type IN ('suggest_brand', 'outdated_information')),
            entity_type TEXT CHECK (entity_type IN ('brand', 'item')),
            entity_id INTEGER,
            contact_email TEXT NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'reviewing', 'resolved')),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_public_feedback_status ON public_feedback(status, created_at);"
    );
}

function ensure_award_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS awards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL UNIQUE,
            description TEXT NOT NULL DEFAULT '',
            criteria TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS brand_awards (
            brand_id INTEGER NOT NULL,
            award_id INTEGER NOT NULL,
            awarded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            note TEXT NOT NULL DEFAULT '',
            PRIMARY KEY (brand_id, award_id),
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
            FOREIGN KEY (award_id) REFERENCES awards(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_brand_awards_award_id ON brand_awards(award_id);"
    );
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

    $awards = [
        ['buy-it-for-life', 'Buy It for Life', 'Recognises products or brands built for exceptional longevity.', 'Credible evidence of durable construction, long service life, and support that helps owners keep products in use.'],
        ['repair-friendly', 'Repair Friendly', 'Recognises practical support for repair and maintenance.', 'Accessible parts, repair information, service options, or product design that makes repair meaningfully possible.'],
        ['biodegradable', 'Biodegradable', 'Recognises products or materials with a credible biodegradable end of life.', 'Clear, relevant evidence that the product or its principal materials biodegrade under stated and realistic conditions.'],
        ['low-waste', 'Low Waste', 'Recognises a material reduction in product or packaging waste.', 'Evidence of reusable, refillable, minimal, recycled, or otherwise low-waste product and packaging practices.'],
        ['transparent-supply-chain', 'Transparent Supply Chain', 'Recognises unusually clear sourcing and manufacturing disclosure.', 'Meaningful, current information about suppliers, manufacturing locations, materials, and responsible sourcing practices.'],
        ['strong-warranty', 'Strong Warranty', 'Recognises unusually strong warranty and aftercare support.', 'A clear, accessible warranty or guarantee that materially supports product longevity and customer confidence.'],
    ];
    $awardStmt = $pdo->prepare(
        'INSERT OR IGNORE INTO awards (slug, name, description, criteria) VALUES (:slug, :name, :description, :criteria)'
    );
    foreach ($awards as [$slug, $name, $description, $awardCriteria]) {
        $awardStmt->execute(compact('slug', 'name', 'description') + ['criteria' => $awardCriteria]);
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
