<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Przelewy24 (P24) integration
|--------------------------------------------------------------------------
|
| Sandbox URL: https://sandbox.przelewy24.pl
| Production URL: https://secure.przelewy24.pl
|
| Auth: HTTP Basic where username = pos_id, password = REPORTS key.
|       (NOT the "klucz do zamówień" — that's the legacy trnDirect API.)
| Sign: SHA-384 hash of a JSON-encoded payload that includes the CRC key.
|
*/

return [
    'sandbox' => (bool) env('P24_SANDBOX', true),

    // POS id assigned by P24. `merchant_id` defaults to the same value — they
    // only differ when one merchant operates multiple POS (e.g. several shops
    // under one P24 account); set P24_MERCHANT_ID explicitly in that case.
    'pos_id' => (int) env('P24_POS_ID', 0),
    'merchant_id' => (int) env('P24_MERCHANT_ID', env('P24_POS_ID', 0)),

    // CRC key — used for sign hashing (register/verify/notification payloads).
    'crc_key' => (string) env('P24_CRC_KEY', ''),

    // Reports key — used as the HTTP Basic Auth password on REST API calls.
    'reports_key' => (string) env('P24_REPORTS_KEY', ''),

    // Legacy "klucz do zamówień" — kept for compatibility / reference, not used
    // by the REST API. Required by the old trnDirect flow and some webhooks.
    'api_key' => (string) env('P24_API_KEY', ''),

    // Currency + country fixed to PL — extend when EUR/USD flow is added.
    'currency' => 'PLN',
    'country' => 'PL',
    'language' => 'pl',

    // HTTP timeout for P24 API calls (seconds)
    'timeout' => 10,
];
