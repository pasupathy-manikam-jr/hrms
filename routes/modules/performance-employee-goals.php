<?php

use App\Http\Controllers\Performance\EmployeeGoalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-employee-goals'])
    ->controller(EmployeeGoalController::class)
    ->prefix('hr/performance/employee-goals')
    ->name('hr.performance.employee-goals.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-employee-goals')->name('store');
        Route::put('{employeeGoal}', 'update')->middleware('permission:edit-employee-goals')->name('update');
        Route::put('{employeeGoal}/progress', 'progress')->middleware('permission:edit-employee-goals')->name('progress');
        Route::delete('{employeeGoal}', 'destroy')->middleware('permission:delete-employee-goals')->name('destroy');
    });
