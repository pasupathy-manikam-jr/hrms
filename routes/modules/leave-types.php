<?php

use App\Http\Controllers\LeaveTypeController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-leave-types permission, so toggling needs edit-leave-types.
Route::middleware(['auth', 'verified', 'permission:manage-leave-types'])
    ->controller(LeaveTypeController::class)
    ->prefix('hr/leave-types')
    ->name('hr.leave-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-leave-types')->name('store');
        Route::put('{leaveType}', 'update')->middleware('permission:edit-leave-types')->name('update');
        Route::put('{leaveType}/toggle-status', 'toggleStatus')->middleware('permission:edit-leave-types')->name('toggle-status');
        Route::delete('{leaveType}', 'destroy')->middleware('permission:delete-leave-types')->name('destroy');
    });
