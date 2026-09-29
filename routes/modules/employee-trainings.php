<?php

use App\Http\Controllers\Training\EmployeeTrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-employee-trainings'])
    ->controller(EmployeeTrainingController::class)
    ->prefix('hr/employee-trainings')
    ->name('hr.employee-trainings.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('{employeeTraining}', 'show')->whereNumber('employeeTraining')->middleware('permission:view-employee-trainings')->name('show');
        Route::post('/', 'store')->middleware('permission:create-employee-trainings')->name('store');
        Route::post('bulk-assign', 'bulkAssign')->middleware('permission:assign-trainings')->name('bulk-assign');
        Route::put('{employeeTraining}', 'update')->middleware('permission:edit-employee-trainings')->name('update');
        Route::delete('{employeeTraining}', 'destroy')->middleware('permission:delete-employee-trainings')->name('destroy');
        Route::post('{employeeTraining}/record-assessment', 'recordAssessment')->middleware('permission:record-assessment-results')->name('record-assessment');
    });
