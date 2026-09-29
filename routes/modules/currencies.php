<?php

use App\Http\Controllers\System\CurrencyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-currencies'])
    ->controller(CurrencyController::class)
    ->prefix('currencies')
    ->name('currencies.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-currencies')->name('store');
        Route::put('{currency}', 'update')->middleware('permission:edit-currencies')->name('update');
        Route::delete('{currency}', 'destroy')->middleware('permission:delete-currencies')->name('destroy');
    });
