<?php

use App\Http\Controllers\Documents\EmployeeContractController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-employee-contracts'])
    ->controller(EmployeeContractController::class)
    ->prefix('hr/contracts/employee-contracts')
    ->name('hr.contracts.employee-contracts.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-employee-contracts')->name('store');
        Route::put('{employeeContract}', 'update')->middleware('permission:edit-employee-contracts')->name('update');
        Route::put('{employeeContract}/status', 'changeStatus')->middleware('permission:edit-employee-contracts')->name('change-status');
        Route::delete('{employeeContract}', 'destroy')->middleware('permission:delete-employee-contracts')->name('destroy');
    });
