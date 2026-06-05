<?php

declare(strict_types=1);

return [
    'app_name' => 'Get Quality Stuff',
    'app_domain' => 'getqualitystuff.com',
    'base_path' => dirname(__DIR__),
    'database_path' => dirname(__DIR__) . '/storage/getqualitystuff.sqlite',
    'admin_email' => getenv('GET_QUALITY_STUFF_ADMIN_EMAIL') ?: '',
    'admin_password' => getenv('GET_QUALITY_STUFF_ADMIN_PASSWORD') ?: '',
    'session_name' => 'getqualitystuff_session',
];
