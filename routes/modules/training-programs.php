<?php

use App\Http\Controllers\Training\TrainingProgramController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-training-programs'])
    ->controller(TrainingProgramController::class)
    ->prefix('hr/training-programs')
    ->name('hr.training-programs.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{trainingProgram}', 'show')->whereNumber('trainingProgram')->middleware('permission:view-training-programs')->name('show');
        Route::post('/', 'store')->middleware('permission:create-training-programs')->name('store');
        Route::put('{trainingProgram}', 'update')->middleware('permission:edit-training-programs')->name('update');
        Route::delete('{trainingProgram}', 'destroy')->middleware('permission:delete-training-programs')->name('destroy');
    });
