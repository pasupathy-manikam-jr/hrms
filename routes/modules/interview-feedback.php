<?php

use App\Http\Controllers\Recruitment\InterviewFeedbackController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-interview-feedback'])
    ->controller(InterviewFeedbackController::class)
    ->prefix('hr/recruitment/interview-feedback')
    ->name('hr.recruitment.interview-feedback.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-interview-feedback')->name('store');
        Route::put('{interviewFeedback}', 'update')->middleware('permission:edit-interview-feedback')->name('update');
        Route::delete('{interviewFeedback}', 'destroy')->middleware('permission:delete-interview-feedback')->name('destroy');
    });
