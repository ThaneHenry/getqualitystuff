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
        $homeUser = current_user();
        render('home', [
            'title' => 'Home',
            'featuredBrands' => featured_brands(3),
            'featuredStores' => featured_stores(3),
            'featuredItems' => featured_items(3),
            'searchSuggestions' => search_suggestions(),
            'forYouBrands' => $homeUser ? personalized_brands((int) $homeUser['id'], 3) : [],
            'categories' => public_categories(),
            'filters' => [
                'q' => $_GET['q'] ?? '',
                'category' => $_GET['category'] ?? '',
                'mode' => $_GET['mode'] ?? 'all',
            ],
        ]);
        return;
    }

    if ($path === '/search') {
        $hasSearch = trim((string) ($_GET['q'] ?? '')) !== '' || trim((string) ($_GET['category'] ?? '')) !== '';
        render('brands_index', [
            'title' => $hasSearch ? 'Results' : 'Search',
            'results' => directory_results($_GET),
            'categories' => public_categories(),
            'filterOptions' => directory_filter_options(),
            'filters' => [
                'q' => $_GET['q'] ?? '',
                'category' => $_GET['category'] ?? '',
                'mode' => $_GET['mode'] ?? 'all',
                'status' => $_GET['status'] ?? '',
                'company' => $_GET['company'] ?? '',
                'manufacturing' => $_GET['manufacturing'] ?? '',
                'warranty' => $_GET['warranty'] ?? '',
            ],
            'isSearchPage' => true,
        ]);
        return;
    }

    if ($path === '/brands') {
        render('brands_index', [
            'title' => 'Brands',
            'results' => directory_results($_GET),
            'categories' => public_categories(),
            'filterOptions' => directory_filter_options(),
            'filters' => [
                'category' => $_GET['category'] ?? '',
                'mode' => $_GET['mode'] ?? 'all',
                'status' => $_GET['status'] ?? '',
                'company' => $_GET['company'] ?? '',
                'manufacturing' => $_GET['manufacturing'] ?? '',
                'warranty' => $_GET['warranty'] ?? '',
            ],
            'isSearchPage' => false,
        ]);
        return;
    }

    if ($path === '/stores') {
        render('stores_index', [
            'title' => 'Stores',
            'stores' => filter_directory_entries(list_stores(), $_GET),
            'categories' => public_categories(),
            'filterOptions' => directory_filter_options(),
            'filters' => array_intersect_key($_GET, array_flip(['category', 'mode', 'status', 'company', 'manufacturing', 'warranty'])),
        ]);
        return;
    }

    if ($path === '/items') {
        if (!site_capabilities()['items']) {
            redirect('/brands');
        }
        render('items_index', [
            'title' => 'Items',
            'items' => filter_directory_entries(list_items(), $_GET),
            'categories' => public_categories(),
            'filterOptions' => directory_filter_options(),
            'filters' => array_intersect_key($_GET, array_flip(['category', 'mode', 'status', 'company', 'manufacturing', 'warranty'])),
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

    if ($path === '/about') {
        render('about', [
            'title' => 'About',
        ]);
        return;
    }

    if ($path === '/privacy') {
        render('privacy', [
            'title' => 'Privacy policy',
        ]);
        return;
    }

    if ($path === '/tos') {
        render('tos', [
            'title' => 'Terms of service',
        ]);
        return;
    }

    if ($path === '/awards') {
        render('awards', [
            'title' => 'Awards',
            'awards' => all_awards(),
        ]);
        return;
    }

    if ($path === '/feedback' && $method === 'POST') {
        verify_csrf();
        try {
            submit_public_feedback($_POST);
            flash('Thank you. Your message has been added for review.');
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage());
        }
        redirect(safe_redirect_path($_POST['redirect'] ?? null, '/'));
    }

    if ($path === '/about/brand') {
        render('about_brand', [
            'title' => 'Brand guide',
        ]);
        return;
    }

    if ($path === '/auth/google') {
        try {
            redirect(google_auth_url(safe_redirect_path($_GET['redirect'] ?? null, '/account')));
        } catch (RuntimeException $e) {
            flash($e->getMessage());
            redirect('/account?redirect=' . urlencode(safe_redirect_path($_GET['redirect'] ?? null, '/account')));
        }
    }

    if ($path === '/auth/google/callback') {
        $redirect = google_auth_redirect();
        if (isset($_GET['error'])) {
            unset($_SESSION['google_oauth']);
            flash('Google sign-in was cancelled.');
            redirect('/account?redirect=' . urlencode($redirect));
        }
        try {
            $userId = complete_google_auth((string) ($_GET['code'] ?? ''), (string) ($_GET['state'] ?? ''));
            login_user_id($userId);
            flash('You are signed in with Google.');
            redirect($redirect);
        } catch (RuntimeException $e) {
            flash($e->getMessage());
            redirect('/account?redirect=' . urlencode($redirect));
        }
    }

    if ($path === '/register') {
        if ($method === 'POST') {
            handle_register();
            return;
        }

        render('auth/register', [
            'title' => 'Create account',
            'redirect' => safe_redirect_path($_GET['redirect'] ?? null),
        ]);
        return;
    }

    if ($path === '/login') {
        if ($method === 'POST') {
            verify_csrf();
            if (login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
                redirect(safe_redirect_path($_POST['redirect'] ?? null));
            }
            render('auth/login', [
                'title' => 'Log in',
                'error' => 'Those login details did not match.',
                'email' => $_POST['email'] ?? '',
                'redirect' => safe_redirect_path($_POST['redirect'] ?? null),
            ]);
            return;
        }

        render('auth/login', [
            'title' => 'Log in',
            'redirect' => safe_redirect_path($_GET['redirect'] ?? null),
        ]);
        return;
    }

    if ($path === '/forgot-password') {
        if ($method === 'POST') {
            verify_csrf();
            $user = find_user_by_email((string) ($_POST['email'] ?? ''));
            if ($user) {
                send_password_reset_email($user);
            }
            flash('If an account exists for that email, a reset link has been sent.');
            redirect('/login');
        }
        render('auth/forgot_password', ['title' => 'Reset password']);
        return;
    }

    if ($path === '/reset-password') {
        $token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
        if ($method === 'POST') {
            verify_csrf();
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');
            if (strlen($password) < 8 || $password !== $confirmation) {
                render('auth/reset_password', [
                    'title' => 'Choose a new password',
                    'token' => $token,
                    'error' => 'Use matching passwords with at least 8 characters.',
                ]);
                return;
            }
            if (!reset_user_password($token, $password)) {
                render('auth/reset_password', [
                    'title' => 'Choose a new password',
                    'token' => '',
                    'error' => 'That reset link is invalid or has expired.',
                ]);
                return;
            }
            flash('Your password has been updated. You can log in now.');
            redirect('/login');
        }
        render('auth/reset_password', ['title' => 'Choose a new password', 'token' => $token]);
        return;
    }

    if ($path === '/verify-email') {
        if (verify_user_email((string) ($_GET['token'] ?? ''))) {
            flash('Your email address is verified.');
        } else {
            flash('That verification link is invalid or has expired.');
        }
        redirect('/account');
    }

    if ($path === '/account/resend-verification' && $method === 'POST') {
        verify_csrf();
        $user = require_user();
        if (!$user['email_verified_at']) {
            send_verification_email($user);
            flash('A new verification link has been sent.');
        }
        redirect('/account');
    }

    if ($path === '/logout' && $method === 'POST') {
        verify_csrf();
        logout();
        redirect('/');
    }

    if ($path === '/account') {
        $user = current_user();
        if (!$user) {
            render('auth/entry', [
                'title' => 'Join or log in',
                'redirect' => safe_redirect_path($_GET['redirect'] ?? null),
            ]);
            return;
        }
        render('auth/account', [
            'title' => 'Account',
            'user' => $user,
            'savedEntries' => account_entries((int) $user['id']),
            'recentEntries' => account_entries((int) $user['id'], 'recent', 6),
            'preferences' => user_preferences((int) $user['id']),
            'categories' => all_categories(),
            'criteria' => all_criteria(),
        ]);
        return;
    }

    if ($path === '/account/preferences' && $method === 'POST') {
        verify_csrf();
        $user = require_user();
        save_user_preferences(
            (int) $user['id'],
            is_array($_POST['category_ids'] ?? null) ? $_POST['category_ids'] : [],
            is_array($_POST['criterion_slugs'] ?? null) ? $_POST['criterion_slugs'] : []
        );
        flash('Your preferences have been updated.');
        redirect('/account#preferences');
    }

    if ($path === '/account/save' && $method === 'POST') {
        verify_csrf();
        $user = require_user();
        $entityType = (string) ($_POST['entity_type'] ?? '');
        $entityId = (int) ($_POST['entity_id'] ?? 0);
        set_entry_saved((int) $user['id'], $entityType, $entityId, ($_POST['saved'] ?? '') === '1');
        redirect(safe_redirect_path($_POST['redirect'] ?? null));
    }

    if (preg_match('#^/brands/([a-z0-9-]+)$#', $path, $matches)) {
        $brand = find_brand_by_slug($matches[1]);
        if (!$brand) {
            not_found();
        }
        $viewer = current_user();
        if ($viewer) {
            record_recent_view((int) $viewer['id'], 'brand', (int) $brand['id']);
        }
        render('brand', [
            'title' => $brand['name'],
            'brand' => $brand,
            'scores' => entity_scores('brand', (int) $brand['id']),
            'sources' => assessment_sources('brand', (int) $brand['id']),
            'awards' => brand_awards((int) $brand['id']),
            'items' => brand_items((int) $brand['id']),
            'isSaved' => $viewer ? is_entry_saved((int) $viewer['id'], 'brand', (int) $brand['id']) : false,
        ]);
        return;
    }

    if (preg_match('#^/items/([a-z0-9-]+)$#', $path, $matches)) {
        $item = find_item_by_slug($matches[1]);
        if (!$item) {
            not_found();
        }
        $viewer = current_user();
        if ($viewer) {
            record_recent_view((int) $viewer['id'], 'item', (int) $item['id']);
        }
        render('item', [
            'title' => $item['name'],
            'item' => $item,
            'scores' => entity_scores('item', (int) $item['id']),
            'sources' => assessment_sources('item', (int) $item['id']),
            'purchaseLinks' => item_purchase_links((int) $item['id']),
            'isSaved' => $viewer ? is_entry_saved((int) $viewer['id'], 'item', (int) $item['id']) : false,
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
            'redirect' => safe_redirect_path($_POST['redirect'] ?? null),
        ]);
        return;
    }

    $userId = create_user($email, $password);
    login_user_id($userId);
    $user = find_user_by_email($email);
    if ($user) {
        send_verification_email($user);
    }
    flash('Your account is ready. Check your email to verify it.');
    redirect(safe_redirect_path($_POST['redirect'] ?? null));
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
            'feedbackEntries' => public_feedback_entries(),
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

    if (preg_match('#^/admin/feedback/(\d+)/status$#', $path, $matches) && $method === 'POST') {
        verify_csrf();
        update_public_feedback_status((int) $matches[1], (string) ($_POST['status'] ?? 'new'));
        flash('Feedback status updated.');
        redirect('/admin#feedback');
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
        db()->prepare("DELETE FROM assessment_sources WHERE entity_type = 'brand' AND entity_id = :id")->execute(['id' => (int) $matches[1]]);
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
        db()->prepare("DELETE FROM assessment_sources WHERE entity_type = 'item' AND entity_id = :id")->execute(['id' => (int) $matches[1]]);
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
        save_assessment_sources('brand', $brandId, (string) ($_POST['assessment_sources'] ?? ''));
        save_brand_awards($brandId, is_array($_POST['award_ids'] ?? null) ? $_POST['award_ids'] : []);
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
        'assessmentSources' => $id === null ? '' : assessment_sources_editor_value('brand', $id),
        'awards' => all_awards(),
        'brandAwardIds' => $id === null ? [] : array_map(static fn (array $award): int => (int) $award['id'], brand_awards($id)),
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
        save_assessment_sources('item', $itemId, (string) ($_POST['assessment_sources'] ?? ''));
        save_item_purchase_links($itemId, (string) ($_POST['purchase_links'] ?? ''));
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
        'assessmentSources' => $id === null ? '' : assessment_sources_editor_value('item', $id),
        'purchaseLinks' => $id === null ? '' : purchase_links_editor_value($id),
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
