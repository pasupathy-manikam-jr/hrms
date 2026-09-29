<?php

use App\Http\Controllers\Recruitment\JobLocationController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-job-locations permission, so toggling needs edit-job-locations.
Route::middleware(['auth', 'verified', 'permission:manage-job-locations'])
    ->controller(JobLocationController::class)
    ->prefix('hr/recruitment/job-locations')
    ->name('hr.recruitment.job-locations.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-job-locations')->name('store');
        Route::put('{jobLocation}', 'update')->middleware('permission:edit-job-locations')->name('update');
        Route::put('{jobLocation}/toggle-status', 'toggleStatus')->middleware('permission:edit-job-locations')->name('toggle-status');
        Route::delete('{jobLocation}', 'destroy')->middleware('permission:delete-job-locations')->name('destroy');
    });
