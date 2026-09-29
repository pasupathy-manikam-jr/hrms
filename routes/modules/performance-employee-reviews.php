<?php

use App\Http\Controllers\Performance\EmployeeReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-employee-reviews'])
    ->controller(EmployeeReviewController::class)
    ->prefix('hr/performance/employee-reviews')
    ->name('hr.performance.employee-reviews.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{employeeReview}', 'show')->whereNumber('employeeReview')->middleware('permission:view-employee-reviews')->name('show');
        Route::get('create', 'create')->middleware('permission:create-employee-reviews')->name('create');
        Route::post('/', 'store')->middleware('permission:create-employee-reviews')->name('store');
        Route::put('{employeeReview}', 'update')->middleware('permission:edit-employee-reviews')->name('update');
        Route::put('{employeeReview}/status', 'changeStatus')->middleware('permission:edit-employee-reviews')->name('change-status');
        Route::get('{employeeReview}/conduct', 'conduct')->whereNumber('employeeReview')->middleware('permission:edit-employee-reviews')->name('conduct');
        Route::put('{employeeReview}/conduct', 'submitConduct')->middleware('permission:edit-employee-reviews')->name('submit-conduct');
        Route::delete('{employeeReview}', 'destroy')->middleware('permission:delete-employee-reviews')->name('destroy');
    });
