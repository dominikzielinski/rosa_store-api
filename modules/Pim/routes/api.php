<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pim\Controllers\PackageController;

// Route name prefix `pim.` is added by the RouteServiceProvider — do not duplicate here.
Route::get('/', [PackageController::class, 'index'])->name('packages.index');
Route::get('{slug}', [PackageController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('packages.show');
