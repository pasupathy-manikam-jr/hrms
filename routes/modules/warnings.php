<?php

use App\Http\Controllers\WarningController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-warnings'])
    ->controller(WarningController::class)
    ->prefix('hr/warnings')
    ->name('hr.warnings.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-warnings')->name('store');
        Route::put('{warning}', 'update')->middleware('permission:edit-warnings')->name('update');
        Route::put('{warning}/status', 'changeStatus')->middleware('permission:approve-warnings|acknowledge-warnings')->name('change-status');
        Route::put('{warning}/improvement-plan', 'improvementPlan')->middleware('permission:edit-warnings')->name('improvement-plan');
        Route::get('{warning}/document', 'document')->name('document');
        Route::delete('{warning}', 'destroy')->middleware('permission:delete-warnings')->name('destroy');
    });
