<?php

use App\Http\Controllers\Recruitment\InterviewTypeController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-interview-types permission, so toggling needs edit-interview-types.
Route::middleware(['auth', 'verified', 'permission:manage-interview-types'])
    ->controller(InterviewTypeController::class)
    ->prefix('hr/recruitment/interview-types')
    ->name('hr.recruitment.interview-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-interview-types')->name('store');
        Route::put('{interviewType}', 'update')->middleware('permission:edit-interview-types')->name('update');
        Route::put('{interviewType}/toggle-status', 'toggleStatus')->middleware('permission:edit-interview-types')->name('toggle-status');
        Route::delete('{interviewType}', 'destroy')->middleware('permission:delete-interview-types')->name('destroy');
    });
