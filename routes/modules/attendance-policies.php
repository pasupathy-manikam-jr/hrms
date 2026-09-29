<?php

use App\Http\Controllers\AttendancePolicyController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-attendance-policies permission, so toggling needs edit-attendance-policies.
Route::middleware(['auth', 'verified', 'permission:manage-attendance-policies'])
    ->controller(AttendancePolicyController::class)
    ->prefix('hr/attendance-policies')
    ->name('hr.attendance-policies.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-attendance-policies')->name('store');
        Route::put('{attendancePolicy}', 'update')->middleware('permission:edit-attendance-policies')->name('update');
        Route::put('{attendancePolicy}/toggle-status', 'toggleStatus')->middleware('permission:edit-attendance-policies')->name('toggle-status');
        Route::delete('{attendancePolicy}', 'destroy')->middleware('permission:delete-attendance-policies')->name('destroy');
    });
