<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/repository.php';
require_once dirname(__DIR__) . '/app/importer.php';

start_app_session();
db();

$path = current_path();
$method = request_method();

try {
    route($path, $method);
} catch (Throwable $e) {
    http_response_code(500);
    render('error', [
        'title' => 'Error',
        'message' => $e->getMessage(),
    ]);
}

function route(string $path, string $method): void
{
    if ($path === '/') {
        render('home', [
            'title' => 'Home',
            'featuredBrands' => featured_brands(3),
            'featuredStores' => featured_stores(3),
            'featuredItems' => featured_items(3),
            'searchSuggestions' => search_suggestions(),
            'categories' => all_categories(),
            'filters' => [
                'q' => $_GET['q'] ?? '',
                'category' => $_GET['category'] ?? '',
                'sort' => $_GET['sort'] ?? 'featured',
            ],
        ]);
        return;
    }

    if ($path === '/search') {
        $hasSearch = trim((string) ($_GET['q'] ?? '')) !== '' || trim((string) ($_GET['category'] ?? '')) !== '';
        render('brands_index', [
            'title' => $hasSearch ? 'Results' : 'Search',
            'results' => directory_results($_GET),
            'categories' => all_categories(),
            'filters' => [
                'q' => $_GET['q'] ?? '',
                'category' => $_GET['category'] ?? '',
                'sort' => $_GET['sort'] ?? 'featured',
            ],
        ]);
        return;
    }

    if ($path === '/brands') {
        $hasSearch = trim((string) ($_GET['q'] ?? '')) !== '' || trim((string) ($_GET['category'] ?? '')) !== '';
        render('brands_index', [
            'title' => $hasSearch ? 'Results' : 'Brands',
            'results' => directory_results($_GET),
            'categories' => all_categories(),
            'filters' => [
                'q' => $_GET['q'] ?? '',
                'category' => $_GET['category'] ?? '',
                'sort' => $_GET['sort'] ?? 'featured',
            ],
        ]);
        return;
    }

    if ($path === '/stores') {
        render('stores_index', [
            'title' => 'Stores',
            'stores' => list_stores(),
        ]);
        return;
    }

    if ($path === '/items') {
        render('items_index', [
            'title' => 'Items',
            'items' => list_items(),
        ]);
        return;
    }

    if ($path === '/news') {
        render('news', [
            'title' => 'News',
            'articles' => news_articles(),
        ]);
        return;
    }

    if ($path === '/register') {
        if ($method === 'POST') {
            handle_register();
            return;
        }

        render('auth/register', ['title' => 'Create account']);
        return;
    }

    if ($path === '/login') {
        if ($method === 'POST') {
            verify_csrf();
            if (login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
                redirect('/account');
            }
            render('auth/login', [
                'title' => 'Log in',
                'error' => 'Those login details did not match.',
                'email' => $_POST['email'] ?? '',
            ]);
            return;
        }

        render('auth/login', ['title' => 'Log in']);
        return;
    }

    if ($path === '/logout' && $method === 'POST') {
        verify_csrf();
        logout();
        redirect('/');
    }

    if ($path === '/account') {
        render('auth/account', [
            'title' => 'Account',
            'user' => require_user(),
        ]);
        return;
    }

    if (preg_match('#^/brands/([a-z0-9-]+)$#', $path, $matches)) {
        $brand = find_brand_by_slug($matches[1]);
        if (!$brand) {
            not_found();
        }
        render('brand', [
            'title' => $brand['name'],
            'brand' => $brand,
            'scores' => entity_scores('brand', (int) $brand['id']),
            'items' => brand_items((int) $brand['id']),
        ]);
        return;
    }

    if (preg_match('#^/items/([a-z0-9-]+)$#', $path, $matches)) {
        $item = find_item_by_slug($matches[1]);
        if (!$item) {
            not_found();
        }
        render('item', [
            'title' => $item['name'],
            'item' => $item,
            'scores' => entity_scores('item', (int) $item['id']),
        ]);
        return;
    }

    if ($path === '/admin/login') {
        if ($method === 'POST') {
            verify_csrf();
            if (login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
                redirect('/admin');
            }
            render('admin/login', ['title' => 'Admin login', 'error' => 'Those login details did not match.']);
            return;
        }

        render('admin/login', ['title' => 'Admin login']);
        return;
    }

    if ($path === '/admin/logout' && $method === 'POST') {
        verify_csrf();
        logout();
        redirect('/');
    }

    if (str_starts_with($path, '/admin')) {
        require_admin();
        route_admin($path, $method);
        return;
    }

    not_found();
}

function handle_register(): void
{
    verify_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Use at least 8 characters for your password.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'The passwords did not match.';
    }

    if ($email !== '' && find_user_by_email($email) !== null) {
        $errors[] = 'An account already exists for that email.';
    }

    if ($errors) {
        render('auth/register', [
            'title' => 'Create account',
            'errors' => $errors,
            'email' => $email,
        ]);
        return;
    }

    $userId = create_user($email, $password);
    login_user_id($userId);
    flash('Your account is ready.');
    redirect('/account');
}

function route_admin(string $path, string $method): void
{
    if ($path === '/admin') {
        $logs = db()->query('SELECT * FROM import_logs ORDER BY created_at DESC LIMIT 5')->fetchAll();
        render('admin/dashboard', [
            'title' => 'Admin',
            'brands' => list_brands(),
            'items' => list_items(),
            'logs' => $logs,
        ]);
        return;
    }

    if ($path === '/admin/import') {
        $data = ['title' => 'Import CSV'];
        if ($method === 'POST') {
            verify_csrf();
            $relativePath = trim((string) ($_POST['csv_path'] ?? ''));
            $fullPath = config()['base_path'] . '/' . ltrim($relativePath, '/');
            try {
                $data['result'] = import_csv($fullPath);
                flash('CSV import finished.');
            } catch (Throwable $e) {
                $data['error'] = $e->getMessage();
            }
        }
        render('admin/import', $data);
        return;
    }

    if ($path === '/admin/brands/new') {
        handle_brand_form($method, null);
        return;
    }

    if (preg_match('#^/admin/brands/(\d+)/edit$#', $path, $matches)) {
        handle_brand_form($method, (int) $matches[1]);
        return;
    }

    if (preg_match('#^/admin/brands/(\d+)/get-logo$#', $path, $matches) && $method === 'POST') {
        handle_brand_logo((int) $matches[1]);
        return;
    }

    if (preg_match('#^/admin/brands/(\d+)/delete$#', $path, $matches) && $method === 'POST') {
        verify_csrf();
        $itemIds = db()->prepare('SELECT id FROM items WHERE brand_id = :id');
        $itemIds->execute(['id' => (int) $matches[1]]);
        foreach ($itemIds->fetchAll() as $item) {
            $deleteScores = db()->prepare("DELETE FROM scores WHERE entity_type = 'item' AND entity_id = :id");
            $deleteScores->execute(['id' => (int) $item['id']]);
        }
        $deleteBrandScores = db()->prepare("DELETE FROM scores WHERE entity_type = 'brand' AND entity_id = :id");
        $deleteBrandScores->execute(['id' => (int) $matches[1]]);
        $stmt = db()->prepare('DELETE FROM brands WHERE id = :id');
        $stmt->execute(['id' => (int) $matches[1]]);
        flash('Brand deleted.');
        redirect('/admin');
    }

    if ($path === '/admin/items/new') {
        handle_item_form($method, null);
        return;
    }

    if (preg_match('#^/admin/items/(\d+)/edit$#', $path, $matches)) {
        handle_item_form($method, (int) $matches[1]);
        return;
    }

    if (preg_match('#^/admin/items/(\d+)/get-logo$#', $path, $matches) && $method === 'POST') {
        handle_item_logo((int) $matches[1]);
        return;
    }

    if (preg_match('#^/admin/items/(\d+)/delete$#', $path, $matches) && $method === 'POST') {
        verify_csrf();
        $deleteScores = db()->prepare("DELETE FROM scores WHERE entity_type = 'item' AND entity_id = :id");
        $deleteScores->execute(['id' => (int) $matches[1]]);
        $stmt = db()->prepare('DELETE FROM items WHERE id = :id');
        $stmt->execute(['id' => (int) $matches[1]]);
        flash('Item deleted.');
        redirect('/admin');
    }

    not_found();
}

function handle_brand_form(string $method, ?int $id): void
{
    $brand = $id === null ? null : find_brand_by_id($id);
    if ($id !== null && !$brand) {
        not_found();
    }

    if ($method === 'POST') {
        verify_csrf();
        $brandId = save_brand($_POST, $id);
        save_scores('brand', $brandId, $_POST['scores'] ?? []);
        flash('Brand saved.');
        redirect('/admin');
    }

    $categoryName = '';
    if ($brand && $brand['category_id']) {
        $stmt = db()->prepare('SELECT name FROM categories WHERE id = :id');
        $stmt->execute(['id' => $brand['category_id']]);
        $categoryName = (string) $stmt->fetchColumn();
    }

    render('admin/brand_form', [
        'title' => $id === null ? 'New brand' : 'Edit brand',
        'brand' => $brand,
        'categoryName' => $categoryName,
        'scores' => $id === null ? blank_scores() : entity_scores('brand', $id),
    ]);
}

function handle_brand_logo(int $id): void
{
    verify_csrf();
    $brand = find_brand_by_id($id);
    if (!$brand) {
        not_found();
    }

    $imageUrl = discover_og_image($brand['url'] ?? '');
    if ($imageUrl === '') {
        flash('No logo image found for that saved URL.');
        redirect('/admin/brands/' . $id . '/edit');
    }

    $stmt = db()->prepare('UPDATE brands SET image_url = :image_url, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute(['image_url' => $imageUrl, 'id' => $id]);
    flash('Logo image updated.');
    redirect('/admin/brands/' . $id . '/edit');
}

function handle_item_form(string $method, ?int $id): void
{
    $item = $id === null ? null : find_item_by_id($id);
    if ($id !== null && !$item) {
        not_found();
    }

    if ($method === 'POST') {
        verify_csrf();
        $itemId = save_item($_POST, $id);
        save_scores('item', $itemId, $_POST['scores'] ?? []);
        flash('Item saved.');
        redirect('/admin');
    }

    $categoryName = '';
    if ($item && $item['category_id']) {
        $stmt = db()->prepare('SELECT name FROM categories WHERE id = :id');
        $stmt->execute(['id' => $item['category_id']]);
        $categoryName = (string) $stmt->fetchColumn();
    }

    render('admin/item_form', [
        'title' => $id === null ? 'New item' : 'Edit item',
        'item' => $item,
        'brands' => list_brands(),
        'categoryName' => $categoryName,
        'scores' => $id === null ? blank_scores() : entity_scores('item', $id),
    ]);
}

function handle_item_logo(int $id): void
{
    verify_csrf();
    $item = find_item_by_id($id);
    if (!$item) {
        not_found();
    }

    $imageUrl = discover_og_image($item['url'] ?? '');
    if ($imageUrl === '') {
        flash('No logo image found for that saved URL.');
        redirect('/admin/items/' . $id . '/edit');
    }

    $stmt = db()->prepare('UPDATE items SET image_url = :image_url, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute(['image_url' => $imageUrl, 'id' => $id]);
    flash('Logo image updated.');
    redirect('/admin/items/' . $id . '/edit');
}

function blank_scores(): array
{
    return array_map(
        fn (array $criterion) => ['slug' => $criterion['slug'], 'name' => $criterion['name'], 'score' => null],
        all_criteria()
    );
}

function not_found(): never
{
    http_response_code(404);
    render('error', ['title' => 'Not found', 'message' => 'That page could not be found.']);
    exit;
}
