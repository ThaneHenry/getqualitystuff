<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function start_app_session(): void
{
    $config = config();
    session_name($config['session_name']);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, email, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('/login');
    }

    return $user;
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('/admin/login');
    }
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE lower(email) = lower(:email) LIMIT 1');
    $stmt->execute(['email' => trim($email)]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function create_user(string $email, string $password, string $role = 'member'): int
{
    $email = trim($email);

    $stmt = db()->prepare(
        'INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)'
    );
    $stmt->execute([
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);

    return (int) db()->lastInsertId();
}

function login(string $email, string $password): bool
{
    $user = find_user_by_email($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function login_user_id(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
