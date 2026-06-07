<?php

declare(strict_types=1);

$localEnvPath = dirname(__DIR__) . '/.env.local';
if (is_readable($localEnvPath)) {
    $localEnv = parse_ini_file($localEnvPath, false, INI_SCANNER_RAW) ?: [];
    foreach ($localEnv as $name => $value) {
        if (getenv((string) $name) === false && is_string($value)) {
            putenv($name . '=' . $value);
        }
    }
}

return [
    'app_name' => 'Get Quality Stuff',
    'app_domain' => 'getqualitystuff.com',
    'app_url' => rtrim(getenv('GET_QUALITY_STUFF_APP_URL') ?: 'http://127.0.0.1:8000', '/'),
    'base_path' => dirname(__DIR__),
    'database_path' => getenv('GET_QUALITY_STUFF_DATABASE_PATH') ?: dirname(__DIR__) . '/storage/getqualitystuff.sqlite',
    'admin_email' => getenv('GET_QUALITY_STUFF_ADMIN_EMAIL') ?: '',
    'admin_password' => getenv('GET_QUALITY_STUFF_ADMIN_PASSWORD') ?: '',
    'google_client_id' => getenv('GET_QUALITY_STUFF_GOOGLE_CLIENT_ID') ?: '',
    'google_client_secret' => getenv('GET_QUALITY_STUFF_GOOGLE_CLIENT_SECRET') ?: '',
    'session_name' => 'getqualitystuff_session',
    'mail_transport' => getenv('GET_QUALITY_STUFF_MAIL_TRANSPORT') ?: 'log',
    'mail_from' => getenv('GET_QUALITY_STUFF_MAIL_FROM') ?: 'hello@getqualitystuff.com',
];
