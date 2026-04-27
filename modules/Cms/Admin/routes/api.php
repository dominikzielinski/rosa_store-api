<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Cms\Admin\Controllers\AdminFaqController;
use Modules\Cms\Admin\Controllers\AdminSiteSettingController;
use Modules\Cms\Admin\Controllers\AdminTestimonialController;

// Route name prefix `admin.cms.` is added by the RouteServiceProvider.

// Site settings — singleton, no ID in path
Route::put('settings', [AdminSiteSettingController::class, 'update'])
    ->name('settings.update');

// FAQ
Route::put('faq/{backofficeId}', [AdminFaqController::class, 'upsert'])
    ->where('backofficeId', '[0-9]+')
    ->name('faq.upsert');
Route::delete('faq/{backofficeId}', [AdminFaqController::class, 'destroy'])
    ->where('backofficeId', '[0-9]+')
    ->name('faq.destroy');

// Testimonials
Route::put('testimonials/{backofficeId}', [AdminTestimonialController::class, 'upsert'])
    ->where('backofficeId', '[0-9]+')
    ->name('testimonials.upsert');
Route::delete('testimonials/{backofficeId}', [AdminTestimonialController::class, 'destroy'])
    ->where('backofficeId', '[0-9]+')
    ->name('testimonials.destroy');
