<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pim\Sync\Controllers\PanelNotifyController;

// Webhook from backoffice signaling a PIM change.
// Auth via the same Bearer token used everywhere else (`backoffice.token`).
// Route name prefix `panel.` is added by the RouteServiceProvider.
Route::post('notify', [PanelNotifyController::class, 'store'])
    ->middleware('log.integration:panel.notify')
    ->name('notify');
