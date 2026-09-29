<?php

use App\Http\Controllers\Recruitment\InterviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-interviews'])
    ->controller(InterviewController::class)
    ->prefix('hr/recruitment/interviews')
    ->name('hr.recruitment.interviews.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('kanban', 'kanban')->name('kanban');
        Route::get('{interview}', 'show')->whereNumber('interview')->middleware('permission:view-interviews')->name('show');
        Route::post('/', 'store')->middleware('permission:create-interviews')->name('store');
        Route::put('{interview}', 'update')->middleware('permission:edit-interviews')->name('update');
        Route::put('{interview}/update-status', 'updateStatus')->middleware('permission:edit-interviews')->name('update-status');
        Route::delete('{interview}', 'destroy')->middleware('permission:delete-interviews')->name('destroy');
    });
