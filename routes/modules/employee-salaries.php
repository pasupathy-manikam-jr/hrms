<?php

use App\Http\Controllers\Payroll\EmployeeSalaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-employee-salaries'])
    ->controller(EmployeeSalaryController::class)
    ->prefix('hr/employee-salaries')
    ->name('hr.employee-salaries.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-employee-salaries')->name('store');
        Route::put('{employeeSalary}', 'update')->middleware('permission:edit-employee-salaries')->name('update');
        Route::get('{employeeSalary}/payroll', 'payroll')->name('payroll');
        // The demo has no toggle-status-employee-salaries permission, so the lock needs edit-employee-salaries.
        Route::put('{employeeSalary}/toggle-status', 'toggleStatus')->middleware('permission:edit-employee-salaries')->name('toggle-status');
        Route::delete('{employeeSalary}', 'destroy')->middleware('permission:delete-employee-salaries')->name('destroy');
    });
