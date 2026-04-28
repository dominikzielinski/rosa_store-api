<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Orders\Controllers\P24WebhookController;

// Reachable as `route('p24.webhook')` — passed to P24 as `urlStatus` during
// transaction registration. P24 retries the webhook on non-2xx responses,
// so the controller MUST be idempotent (it is — checks `p24_paid_at`).
Route::post('webhook', P24WebhookController::class)
    ->middleware('log.integration:p24.webhook')
    ->name('p24.webhook');
