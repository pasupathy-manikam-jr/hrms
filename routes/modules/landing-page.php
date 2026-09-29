<?php

use App\Http\Controllers\Landing\ContactController;
use App\Http\Controllers\Landing\CustomPageController;
use App\Http\Controllers\Landing\LandingPageSettingsController;
use App\Http\Controllers\Landing\NewsletterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-landing-page'])->group(function () {
    Route::get('landing-page/settings', [LandingPageSettingsController::class, 'show'])->name('landing-page.settings');
    Route::post('landing-page/settings', [LandingPageSettingsController::class, 'update'])->middleware('permission:edit-landing-page')->name('landing-page.settings.update');

    Route::controller(CustomPageController::class)
        ->prefix('custom-pages')
        ->name('landing-page.custom-pages.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->middleware('permission:edit-landing-page')->name('create');
            Route::post('/', 'store')->middleware('permission:edit-landing-page')->name('store');
            Route::get('{customPage}/edit', 'edit')->middleware('permission:edit-landing-page')->name('edit');
            Route::put('{customPage}', 'update')->middleware('permission:edit-landing-page')->name('update');
            Route::delete('{customPage}', 'destroy')->middleware('permission:edit-landing-page')->name('destroy');
        });
});

Route::middleware(['auth', 'verified', 'permission:manage-contacts'])
    ->controller(ContactController::class)
    ->prefix('contacts')
    ->name('contacts.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('{contact}/update-status', 'updateStatus')->middleware('permission:update-contact-status')->name('update-status');
        Route::post('{contact}/reply', 'reply')->middleware('permission:send-reply-contacts')->name('reply');
        Route::delete('{contact}', 'destroy')->middleware('permission:delete-contacts')->name('destroy');
    });

Route::middleware(['auth', 'verified', 'permission:manage-newsletters'])
    ->controller(NewsletterController::class)
    ->prefix('newsletters')
    ->name('newsletters.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::delete('{newsletter}', 'destroy')->middleware('permission:delete-newsletters')->name('destroy');
    });
