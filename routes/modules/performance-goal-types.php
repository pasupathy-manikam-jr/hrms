<?php

use App\Http\Controllers\Performance\GoalTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-goal-types'])
    ->controller(GoalTypeController::class)
    ->prefix('hr/performance/goal-types')
    ->name('hr.performance.goal-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-goal-types')->name('store');
        Route::put('{goalType}', 'update')->middleware('permission:edit-goal-types')->name('update');
        Route::delete('{goalType}', 'destroy')->middleware('permission:delete-goal-types')->name('destroy');
    });
