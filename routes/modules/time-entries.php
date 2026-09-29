<?php

use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-time-entries'])
    ->controller(TimeEntryController::class)
    ->prefix('hr/time-entries')
    ->name('hr.time-entries.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('export', 'export')->middleware('permission:export-time-entry')->name('export');
        Route::get('download-template', 'template')->middleware('permission:import-time-entry')->name('download.template');
        Route::post('import', 'import')->middleware(['permission:import-time-entry', 'throttle:10,1'])->name('import');
        Route::post('/', 'store')->middleware('permission:create-time-entries')->name('store');
        Route::put('{timeEntry}', 'update')->middleware('permission:edit-time-entries')->name('update');
        Route::put('{timeEntry}/approve', 'approve')->middleware('permission:approve-time-entries')->name('approve');
        Route::put('{timeEntry}/reject', 'reject')->middleware('permission:reject-time-entries')->name('reject');
        Route::delete('{timeEntry}', 'destroy')->middleware('permission:delete-time-entries')->name('destroy');
    });
