<?php

return [
    'secret' => env('JWT_SECRET'),
    'ttl' => env('JWT_TTL', 60), // Access token lifetime in minutes
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160), // Refresh token lifetime (14 days)
    'allowed_algorithms' => ['HS512'],
    'token_length' => 32,
    'rate_limit' => [
        'attempts' => 60,
        'decay_minutes' => 1
    ],
    'token_blacklist' => [
        'enabled' => true,
        'storage' => 'redis'
    ]
];