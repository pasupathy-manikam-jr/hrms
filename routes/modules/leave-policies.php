<?php

use App\Http\Controllers\LeavePolicyController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-leave-policies permission, so toggling needs edit-leave-policies.
Route::middleware(['auth', 'verified', 'permission:manage-leave-policies'])
    ->controller(LeavePolicyController::class)
    ->prefix('hr/leave-policies')
    ->name('hr.leave-policies.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-leave-policies')->name('store');
        Route::put('{leavePolicy}', 'update')->middleware('permission:edit-leave-policies')->name('update');
        Route::put('{leavePolicy}/toggle-status', 'toggleStatus')->middleware('permission:edit-leave-policies')->name('toggle-status');
        Route::delete('{leavePolicy}', 'destroy')->middleware('permission:delete-leave-policies')->name('destroy');
    });
