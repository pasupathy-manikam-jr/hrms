<?php

use App\Http\Controllers\Performance\IndicatorController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-x permissions for performance, so toggling needs edit-x.
Route::middleware(['auth', 'verified', 'permission:manage-performance-indicators'])
    ->controller(IndicatorController::class)
    ->prefix('hr/performance/indicators')
    ->name('hr.performance.indicators.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-performance-indicators')->name('store');
        Route::put('{indicator}', 'update')->middleware('permission:edit-performance-indicators')->name('update');
        Route::put('{indicator}/toggle-status', 'toggleStatus')->middleware('permission:edit-performance-indicators')->name('toggle-status');
        Route::delete('{indicator}', 'destroy')->middleware('permission:delete-performance-indicators')->name('destroy');
    });
