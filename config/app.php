<?php
return [
    'name' => $_ENV['APP_NAME'] ?? 'FORT (Fast Short)',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
    'key' => $_ENV['APP_KEY'] ?? '',
    'totp_issuer' => $_ENV['TOTP_ISSUER'] ?? 'FORT (Fast Short)',
];
