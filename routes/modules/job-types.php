<?php

use App\Http\Controllers\Recruitment\JobTypeController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-job-types permission, so toggling needs edit-job-types.
Route::middleware(['auth', 'verified', 'permission:manage-job-types'])
    ->controller(JobTypeController::class)
    ->prefix('hr/recruitment/job-types')
    ->name('hr.recruitment.job-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-job-types')->name('store');
        Route::put('{jobType}', 'update')->middleware('permission:edit-job-types')->name('update');
        Route::put('{jobType}/toggle-status', 'toggleStatus')->middleware('permission:edit-job-types')->name('toggle-status');
        Route::delete('{jobType}', 'destroy')->middleware('permission:delete-job-types')->name('destroy');
    });
