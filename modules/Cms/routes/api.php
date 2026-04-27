<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Cms\Controllers\FaqController;
use Modules\Cms\Controllers\SiteSettingController;
use Modules\Cms\Controllers\TestimonialController;

// Route name prefix `cms.` is added by the RouteServiceProvider — do not duplicate here.
Route::get('settings', [SiteSettingController::class, 'show'])->name('settings.show');
Route::get('faq', [FaqController::class, 'index'])->name('faq.index');
Route::get('testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
