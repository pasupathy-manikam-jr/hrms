<?php

use App\Http\Controllers\Performance\IndicatorCategoryController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-x permissions for performance, so toggling needs edit-x.
Route::middleware(['auth', 'verified', 'permission:manage-performance-indicator-categories'])
    ->controller(IndicatorCategoryController::class)
    ->prefix('hr/performance/indicator-categories')
    ->name('hr.performance.indicator-categories.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-performance-indicator-categories')->name('store');
        Route::put('{indicatorCategory}', 'update')->middleware('permission:edit-performance-indicator-categories')->name('update');
        Route::put('{indicatorCategory}/toggle-status', 'toggleStatus')->middleware('permission:edit-performance-indicator-categories')->name('toggle-status');
        Route::delete('{indicatorCategory}', 'destroy')->middleware('permission:delete-performance-indicator-categories')->name('destroy');
    });
