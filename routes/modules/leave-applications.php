<?php

use App\Http\Controllers\LeaveApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-leave-applications'])
    ->controller(LeaveApplicationController::class)
    ->prefix('hr/leave-applications')
    ->name('hr.leave-applications.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('export', 'export')->middleware('permission:export-leave-applications')->name('export');
        Route::post('/', 'store')->middleware('permission:create-leave-applications')->name('store');
        Route::put('{leaveApplication}', 'update')->middleware('permission:edit-leave-applications')->name('update');
        Route::put('{leaveApplication}/approve', 'approve')->middleware('permission:approve-leave-applications')->name('approve');
        Route::put('{leaveApplication}/reject', 'reject')->middleware('permission:reject-leave-applications')->name('reject');
        Route::delete('{leaveApplication}', 'destroy')->middleware('permission:delete-leave-applications')->name('destroy');
    });
