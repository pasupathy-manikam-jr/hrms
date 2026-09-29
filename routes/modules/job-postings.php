<?php

use App\Http\Controllers\Recruitment\JobPostingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-job-postings'])
    ->controller(JobPostingController::class)
    ->prefix('hr/recruitment/job-postings')
    ->name('hr.recruitment.job-postings.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{jobPosting}', 'show')->whereNumber('jobPosting')->middleware('permission:view-job-postings')->name('show');
        Route::post('/', 'store')->middleware('permission:create-job-postings')->name('store');
        Route::put('{jobPosting}', 'update')->middleware('permission:edit-job-postings')->name('update');
        Route::put('{jobPosting}/publish', 'publish')->middleware('permission:publish-job-postings')->name('publish');
        Route::delete('{jobPosting}', 'destroy')->middleware('permission:delete-job-postings')->name('destroy');
    });
