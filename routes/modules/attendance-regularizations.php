<?php

use App\Http\Controllers\AttendanceRegularizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-attendance-regularizations'])
    ->controller(AttendanceRegularizationController::class)
    ->prefix('hr/attendance-regularizations')
    ->name('hr.attendance-regularizations.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-attendance-regularizations')->name('store');
        Route::put('{attendanceRegularization}', 'update')->middleware('permission:edit-attendance-regularizations')->name('update');
        Route::put('{attendanceRegularization}/approve', 'approve')->middleware('permission:approve-attendance-regularizations')->name('approve');
        Route::put('{attendanceRegularization}/reject', 'reject')->middleware('permission:reject-attendance-regularizations')->name('reject');
        Route::delete('{attendanceRegularization}', 'destroy')->middleware('permission:delete-attendance-regularizations')->name('destroy');
    });
