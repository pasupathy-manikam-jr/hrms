<?php

use App\Http\Controllers\HolidayController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-holidays'])
    ->controller(HolidayController::class)
    ->prefix('hr/holidays')
    ->name('hr.holidays.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('calendar', 'calendar')->name('calendar');
        Route::post('/', 'store')->middleware('permission:create-holidays')->name('store');
        Route::put('{holiday}', 'update')->middleware('permission:edit-holidays')->name('update');
        Route::delete('{holiday}', 'destroy')->middleware('permission:delete-holidays')->name('destroy');
    });
