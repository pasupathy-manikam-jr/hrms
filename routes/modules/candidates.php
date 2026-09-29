<?php

use App\Http\Controllers\Recruitment\CandidateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-candidates'])
    ->controller(CandidateController::class)
    ->prefix('hr/recruitment/candidates')
    ->name('hr.recruitment.candidates.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('kanban', 'kanban')->name('kanban');
        Route::get('{candidate}', 'show')->whereNumber('candidate')->middleware('permission:view-candidates')->name('show');
        Route::put('{candidate}', 'update')->middleware('permission:edit-candidates')->name('update');
        Route::delete('{candidate}', 'destroy')->middleware('permission:delete-candidates')->name('destroy');
    });
