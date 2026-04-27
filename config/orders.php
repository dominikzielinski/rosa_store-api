<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Rate limit
    |--------------------------------------------------------------------------
    |
    | Maximum order submissions allowed per IP per minute. Used by the
    | `orders` named rate limiter in AppServiceProvider.
    |
    */
    'rate_limit_per_minute' => (int) env('ORDERS_RATE_LIMIT_PER_MINUTE', 10),
];
