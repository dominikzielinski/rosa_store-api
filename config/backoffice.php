<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Backoffice integration
|--------------------------------------------------------------------------
|
| ONE shared bearer token for traffic in BOTH directions:
|
|   shop → backoffice: POST /api/shop/orders, GET /api/shop/pim/*
|   backoffice → shop: POST /api/admin/*, POST /api/panel/notify
|
| Same `BACKOFFICE_API_TOKEN` in both apps' .env. Rotate together.
|
*/
return [
    'token' => env('BACKOFFICE_API_TOKEN'),

    // Backoffice base URL (no trailing slash)
    'url' => env('BACKOFFICE_INBOUND_URL', 'http://localhost:9000'),

    // Path templates appended to `url` for outbound calls
    'paths' => [
        'orders' => '/api/shop/orders',
        'pim_packages' => '/api/shop/pim/packages',
        'pim_package' => '/api/shop/pim/packages/{id}',
        'pim_boxes' => '/api/shop/pim/boxes',
        'pim_box' => '/api/shop/pim/boxes/{id}',
    ],

    // HTTP timeout for outbound calls (seconds)
    'timeout' => 10,
];
