<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Contact\Controllers\ContactSubmissionController;

// Route name prefix `contact.` is added by the RouteServiceProvider — do not duplicate here.
Route::post('/', [ContactSubmissionController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('store');
