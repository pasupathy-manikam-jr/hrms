<?php

use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'home'])->name('home');
Route::get('page/{slug}', [LandingController::class, 'page'])->name('custom-page.show');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');
Route::get('translations/{locale}', [LocaleController::class, 'translations'])->name('translations.show');

Route::middleware('throttle:6,1')->group(function () {
    Route::post('contact', [LandingController::class, 'contact'])->name('contact.store');
    Route::post('newsletter', [LandingController::class, 'subscribe'])->name('newsletter.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->middleware('permission:manage-dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'permission:manage-settings'])
    ->controller(CompanySettingsController::class)
    ->prefix('settings')
    ->name('settings')
    ->group(function () {
        Route::get('/', 'show');
        Route::put('system', 'updateSystem')->name('.system');
        Route::put('brand', 'updateBrand')->name('.brand');
        Route::put('currency', 'updateCurrency')->name('.currency');
        Route::put('email', 'updateEmail')->name('.email');
        Route::post('email/test', 'testEmail')->middleware('throttle:6,1')->name('.email.test');
        Route::put('working-days', 'updateWorkingDays')->name('.working-days');
        Route::post('ip-restrictions', 'storeIpRestriction')->name('.ip-restrictions.store');
        Route::delete('ip-restrictions/{ipRestriction}', 'destroyIpRestriction')->name('.ip-restrictions.destroy');
    });

require __DIR__.'/hrm.php';
require __DIR__.'/settings.php';
