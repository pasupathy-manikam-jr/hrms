<?php

use App\Http\Controllers\AttendanceClockController;
use App\Http\Controllers\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-attendance-records'])
    ->controller(AttendanceRecordController::class)
    ->prefix('hr/attendance-records')
    ->name('hr.attendance-records.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('export', 'export')->middleware('permission:export-attendance-record')->name('export');
        Route::get('download-template', 'template')->middleware('permission:import-attendance-record')->name('download.template');
        Route::post('import', 'import')->middleware(['permission:import-attendance-record', 'throttle:10,1'])->name('import');
        Route::post('/', 'store')->middleware('permission:create-attendance-records')->name('store');
        Route::put('{attendanceRecord}', 'update')->middleware('permission:edit-attendance-records')->name('update');
        Route::delete('{attendanceRecord}', 'destroy')->middleware('permission:delete-attendance-records')->name('destroy');
    });

Route::middleware(['auth', 'verified', 'permission:clock-in-out'])
    ->controller(AttendanceClockController::class)
    ->prefix('attendance')
    ->name('attendance.')
    ->group(function () {
        Route::post('clock-in', 'clockIn')->name('clock-in');
        Route::post('clock-out', 'clockOut')->name('clock-out');
    });
