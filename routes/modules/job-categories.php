<?php

use App\Http\Controllers\Recruitment\JobCategoryController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-job-categories permission, so toggling needs edit-job-categories.
Route::middleware(['auth', 'verified', 'permission:manage-job-categories'])
    ->controller(JobCategoryController::class)
    ->prefix('hr/recruitment/job-categories')
    ->name('hr.recruitment.job-categories.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-job-categories')->name('store');
        Route::put('{jobCategory}', 'update')->middleware('permission:edit-job-categories')->name('update');
        Route::put('{jobCategory}/toggle-status', 'toggleStatus')->middleware('permission:edit-job-categories')->name('toggle-status');
        Route::delete('{jobCategory}', 'destroy')->middleware('permission:delete-job-categories')->name('destroy');
    });
