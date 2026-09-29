<?php

use App\Http\Controllers\BiometricAttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-biometric-attendance'])
    ->controller(BiometricAttendanceController::class)
    ->prefix('hr/biometric-attendance')
    ->name('hr.biometric-attendance.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('employees/{employee}', 'updateMapping')->name('update-mapping');
        Route::get('{employee}/{date}', 'show')->whereNumber('employee')->name('show');
        Route::post('import', 'import')->middleware('permission:sync-biometric-attendance')->name('import');
    });
