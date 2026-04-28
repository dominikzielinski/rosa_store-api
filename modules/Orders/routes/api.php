<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Orders\Controllers\OrderController;

// Route name prefix `orders.` is added by the RouteServiceProvider.

// Place order — public, rate-limited via the `orders` named limiter.
Route::post('/', [OrderController::class, 'store'])
    ->middleware(['throttle:orders', 'log.integration:orders.store'])
    ->name('store');

// Polling status — public but reveals only non-PII status fields.
// Throttle to prevent enumeration of recently placed order numbers.
Route::get('{orderNumber}/status', [OrderController::class, 'status'])
    ->middleware('throttle:60,1')
    ->where('orderNumber', 'RD-[0-9]{8}')
    ->name('status');
