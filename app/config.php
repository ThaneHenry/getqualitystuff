<?php

declare(strict_types=1);

return [
    'app_name' => 'Get Quality Stuff',
    'app_domain' => 'getqualitystuff.com',
    'app_url' => rtrim(getenv('GET_QUALITY_STUFF_APP_URL') ?: 'http://127.0.0.1:8000', '/'),
    'base_path' => dirname(__DIR__),
    'database_path' => getenv('GET_QUALITY_STUFF_DATABASE_PATH') ?: dirname(__DIR__) . '/storage/getqualitystuff.sqlite',
    'admin_email' => getenv('GET_QUALITY_STUFF_ADMIN_EMAIL') ?: '',
    'admin_password' => getenv('GET_QUALITY_STUFF_ADMIN_PASSWORD') ?: '',
    'session_name' => 'getqualitystuff_session',
    'mail_transport' => getenv('GET_QUALITY_STUFF_MAIL_TRANSPORT') ?: 'log',
    'mail_from' => getenv('GET_QUALITY_STUFF_MAIL_FROM') ?: 'hello@getqualitystuff.com',
];
