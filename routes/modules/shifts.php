<?php

use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-shifts permission, so toggling needs edit-shifts.
Route::middleware(['auth', 'verified', 'permission:manage-shifts'])
    ->controller(ShiftController::class)
    ->prefix('hr/shifts')
    ->name('hr.shifts.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-shifts')->name('store');
        Route::put('{shift}', 'update')->middleware('permission:edit-shifts')->name('update');
        Route::put('{shift}/toggle-status', 'toggleStatus')->middleware('permission:edit-shifts')->name('toggle-status');
        Route::delete('{shift}', 'destroy')->middleware('permission:delete-shifts')->name('destroy');
    });
