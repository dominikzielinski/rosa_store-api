<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Team notification email
    |--------------------------------------------------------------------------
    |
    | Address that receives every new contact submission (both B2B and retail).
    | If left empty, team notifications are skipped.
    |
    */
    'notification_email' => env('CONTACT_NOTIFICATION_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Max submissions from a single IP per minute. Protects the public endpoint
    | from basic abuse.
    |
    */
    'rate_limit_per_minute' => 5,
];
