<?php

use App\Http\Controllers\Performance\ReviewCycleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-review-cycles'])
    ->controller(ReviewCycleController::class)
    ->prefix('hr/performance/review-cycles')
    ->name('hr.performance.review-cycles.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-review-cycles')->name('store');
        Route::put('{reviewCycle}', 'update')->middleware('permission:edit-review-cycles')->name('update');
        Route::delete('{reviewCycle}', 'destroy')->middleware('permission:delete-review-cycles')->name('destroy');
    });
