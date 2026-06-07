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

function google_auth_enabled(): bool
{
    $config = config();
    return $config['google_client_id'] !== '' && $config['google_client_secret'] !== '';
}

function google_auth_url(string $redirect = '/account'): string
{
    if (!google_auth_enabled()) {
        throw new RuntimeException('Google sign-in is not configured yet.');
    }

    $state = bin2hex(random_bytes(24));
    $codeVerifier = oauth_base64url(random_bytes(48));
    $_SESSION['google_oauth'] = [
        'state' => $state,
        'code_verifier' => $codeVerifier,
        'redirect' => safe_redirect_path($redirect, '/account'),
        'created_at' => time(),
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => config()['google_client_id'],
        'redirect_uri' => absolute_url('/auth/google/callback'),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'code_challenge' => oauth_base64url(hash('sha256', $codeVerifier, true)),
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ], '', '&', PHP_QUERY_RFC3986);
}

function complete_google_auth(string $code, string $state): int
{
    $request = $_SESSION['google_oauth'] ?? null;
    unset($_SESSION['google_oauth']);

    if ($code === ''
        || !is_array($request)
        || !isset($request['state'], $request['code_verifier'], $request['created_at'])
        || !hash_equals((string) $request['state'], $state)
        || (int) $request['created_at'] < time() - 600
    ) {
        throw new RuntimeException('Google sign-in expired or could not be verified. Please try again.');
    }

    $tokens = oauth_json_request('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => config()['google_client_id'],
        'client_secret' => config()['google_client_secret'],
        'redirect_uri' => absolute_url('/auth/google/callback'),
        'grant_type' => 'authorization_code',
        'code_verifier' => (string) $request['code_verifier'],
    ]);
    $accessToken = (string) ($tokens['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('Google did not return a usable sign-in token.');
    }

    $profile = oauth_json_request('https://openidconnect.googleapis.com/v1/userinfo', null, [
        'Authorization: Bearer ' . $accessToken,
    ]);
    $subject = trim((string) ($profile['sub'] ?? ''));
    $email = trim((string) ($profile['email'] ?? ''));
    $emailVerified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($subject === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified) {
        throw new RuntimeException('Google did not provide a verified email address.');
    }

    return find_or_create_google_user($subject, $email);
}

function create_google_user(string $email): int
{
    $stmt = db()->prepare(
        'INSERT INTO users (email, password_hash, role, email_verified_at)
         VALUES (:email, :password_hash, :role, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([
        'email' => $email,
        'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        'role' => 'member',
    ]);
    return (int) db()->lastInsertId();
}

function find_or_create_google_user(string $subject, string $email): int
{
    $identity = db()->prepare(
        "SELECT user_id FROM user_identities WHERE provider = 'google' AND provider_subject = :subject LIMIT 1"
    );
    $identity->execute(['subject' => $subject]);
    $userId = $identity->fetchColumn();

    if (!$userId) {
        $user = find_user_by_email($email);
        $userId = $user ? (int) $user['id'] : create_google_user($email);
        $link = db()->prepare(
            "INSERT INTO user_identities (user_id, provider, provider_subject, email)
             VALUES (:user_id, 'google', :subject, :email)
             ON CONFLICT(user_id, provider) DO UPDATE SET
                provider_subject = excluded.provider_subject,
                email = excluded.email,
                updated_at = CURRENT_TIMESTAMP"
        );
        $link->execute(['user_id' => $userId, 'subject' => $subject, 'email' => $email]);
    }

    db()->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP) WHERE id = :id')
        ->execute(['id' => $userId]);

    return (int) $userId;
}

function google_auth_redirect(): string
{
    return safe_redirect_path($_SESSION['google_oauth']['redirect'] ?? null, '/account');
}

function oauth_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function oauth_json_request(string $url, ?array $form = null, array $headers = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Google sign-in requires the PHP cURL extension.');
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Unable to start the Google sign-in request.');
    }

    $headers[] = 'Accept: application/json';
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($form !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);

    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException($error !== '' ? 'Google sign-in could not connect.' : 'Google sign-in was not accepted.');
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Google returned an unexpected sign-in response.');
    }
    return $decoded;
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
