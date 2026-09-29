<?php

use App\Http\Controllers\Payroll\SalaryComponentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-salary-components'])
    ->controller(SalaryComponentController::class)
    ->prefix('hr/salary-components')
    ->name('hr.salary-components.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-salary-components')->name('store');
        Route::put('{salaryComponent}', 'update')->middleware('permission:edit-salary-components')->name('update');
        // The demo has no toggle-status-salary-components permission, so the lock needs edit-salary-components.
        Route::put('{salaryComponent}/toggle-status', 'toggleStatus')->middleware('permission:edit-salary-components')->name('toggle-status');
        Route::delete('{salaryComponent}', 'destroy')->middleware('permission:delete-salary-components')->name('destroy');
    });
