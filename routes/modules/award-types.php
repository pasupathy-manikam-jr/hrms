<?php

use App\Http\Controllers\AwardTypeController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-award-types permission, so toggling needs edit-award-types.
Route::middleware(['auth', 'verified', 'permission:manage-award-types'])
    ->controller(AwardTypeController::class)
    ->prefix('hr/award-types')
    ->name('hr.award-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-award-types')->name('store');
        Route::put('{awardType}', 'update')->middleware('permission:edit-award-types')->name('update');
        Route::put('{awardType}/toggle-status', 'toggleStatus')->middleware('permission:edit-award-types')->name('toggle-status');
        Route::delete('{awardType}', 'destroy')->middleware('permission:delete-award-types')->name('destroy');
    });
