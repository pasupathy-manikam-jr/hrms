<?php

use App\Http\Controllers\Training\TrainingTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-training-types'])
    ->controller(TrainingTypeController::class)
    ->prefix('hr/training-types')
    ->name('hr.training-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-training-types')->name('store');
        Route::put('{trainingType}', 'update')->middleware('permission:edit-training-types')->name('update');
        Route::put('{trainingType}/departments', 'assignDepartments')->middleware('permission:edit-training-types')->name('assign-departments');
        Route::delete('{trainingType}', 'destroy')->middleware('permission:delete-training-types')->name('destroy');
    });
