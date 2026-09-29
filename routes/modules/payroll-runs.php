<?php

use App\Http\Controllers\Payroll\PayrollRunController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-payroll-runs'])
    ->controller(PayrollRunController::class)
    ->prefix('hr/payroll-runs')
    ->name('hr.payroll-runs.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('export', 'export')->middleware('permission:export-payroll-runs')->name('export');
        Route::get('download-template', 'template')->middleware('permission:import-payroll-runs')->name('download.template');
        Route::post('import', 'import')->middleware(['permission:import-payroll-runs', 'throttle:10,1'])->name('import');
        Route::get('{payrollRun}', 'show')->whereNumber('payrollRun')->name('show');
        Route::post('/', 'store')->middleware('permission:create-payroll-runs')->name('store');
        Route::put('{payrollRun}', 'update')->middleware('permission:edit-payroll-runs')->name('update');
        Route::post('{payrollRun}/process', 'process')->middleware('permission:process-payroll-runs')->name('process');
        Route::post('{payrollRun}/complete', 'complete')->middleware('permission:process-payroll-runs')->name('complete');
        Route::delete('{payrollRun}', 'destroy')->middleware('permission:delete-payroll-runs')->name('destroy');
    });
