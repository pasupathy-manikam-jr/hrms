<?php

use App\Http\Controllers\Recruitment\InterviewRoundController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-interview-rounds permission, so toggling needs edit-interview-rounds.

Route::middleware(['auth', 'verified', 'permission:manage-interview-rounds'])
    ->controller(InterviewRoundController::class)
    ->prefix('hr/recruitment/interview-rounds')
    ->name('hr.recruitment.interview-rounds.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-interview-rounds')->name('store');
        Route::put('{interviewRound}', 'update')->middleware('permission:edit-interview-rounds')->name('update');
        Route::put('{interviewRound}/toggle-status', 'toggleStatus')->middleware('permission:edit-interview-rounds')->name('toggle-status');
        Route::delete('{interviewRound}', 'destroy')->middleware('permission:delete-interview-rounds')->name('destroy');
    });
