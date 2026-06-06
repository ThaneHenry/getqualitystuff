<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mail.php';

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

    $stmt = db()->prepare('SELECT id, email, role, email_verified_at, created_at FROM users WHERE id = :id LIMIT 1');
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

function create_user_token(int $userId, string $type, int $validForSeconds): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = db()->prepare(
        'INSERT INTO user_tokens (user_id, type, token_hash, expires_at)
         VALUES (:user_id, :type, :token_hash, :expires_at)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', time() + $validForSeconds),
    ]);
    return $token;
}

function consume_user_token(string $token, string $type): ?array
{
    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT t.id AS token_id, u.*
         FROM user_tokens t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = :token_hash AND t.type = :type AND t.expires_at > CURRENT_TIMESTAMP
         LIMIT 1'
    );
    $stmt->execute(['token_hash' => $hash, 'type' => $type]);
    $record = $stmt->fetch();
    if (!$record) {
        return null;
    }

    $delete = db()->prepare('DELETE FROM user_tokens WHERE id = :id');
    $delete->execute(['id' => $record['token_id']]);
    return $record;
}

function send_verification_email(array $user): void
{
    db()->prepare("DELETE FROM user_tokens WHERE user_id = :user_id AND type = 'email_verification'")
        ->execute(['user_id' => $user['id']]);
    $token = create_user_token((int) $user['id'], 'email_verification', 86400);
    send_app_mail(
        $user['email'],
        'Verify your Get Quality Stuff account',
        "Welcome to Get Quality Stuff.\n\nVerify your email address:\n" . absolute_url('/verify-email?token=' . urlencode($token))
    );
}

function send_password_reset_email(array $user): void
{
    db()->prepare("DELETE FROM user_tokens WHERE user_id = :user_id AND type = 'password_reset'")
        ->execute(['user_id' => $user['id']]);
    $token = create_user_token((int) $user['id'], 'password_reset', 3600);
    send_app_mail(
        $user['email'],
        'Reset your Get Quality Stuff password',
        "Reset your password using this link. It expires in one hour:\n" . absolute_url('/reset-password?token=' . urlencode($token))
    );
}

function verify_user_email(string $token): bool
{
    $user = consume_user_token($token, 'email_verification');
    if (!$user) {
        return false;
    }

    db()->prepare('UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute(['id' => $user['id']]);
    return true;
}

function reset_user_password(string $token, string $password): bool
{
    $user = consume_user_token($token, 'password_reset');
    if (!$user) {
        return false;
    }

    db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id')
        ->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
    db()->prepare("DELETE FROM user_tokens WHERE user_id = :user_id AND type = 'password_reset'")
        ->execute(['user_id' => $user['id']]);
    return true;
}
