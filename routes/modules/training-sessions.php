<?php

use App\Http\Controllers\Training\TrainingSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-training-sessions'])
    ->controller(TrainingSessionController::class)
    ->prefix('hr/training-sessions')
    ->name('hr.training-sessions.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('calendar', 'calendar')->name('calendar');
        Route::get('{trainingSession}', 'show')->whereNumber('trainingSession')->middleware('permission:view-training-sessions')->name('show');
        Route::post('/', 'store')->middleware('permission:create-training-sessions')->name('store');
        Route::put('{trainingSession}', 'update')->middleware('permission:edit-training-sessions')->name('update');
        Route::delete('{trainingSession}', 'destroy')->middleware('permission:delete-training-sessions')->name('destroy');
    });
