<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'auth/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        // เพิ่ม domains ที่อนุญาตเท่านั้น
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-Socket-Id',
        'Origin',
    ],

    'exposed_headers' => [
        'Content-Disposition',
    ],

    'max_age' => 7200, // 2 hours

    'supports_credentials' => true,

    'logging' => true,
];