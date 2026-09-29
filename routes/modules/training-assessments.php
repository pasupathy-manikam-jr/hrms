<?php

use App\Http\Controllers\Training\TrainingAssessmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-training-assessments'])
    ->controller(TrainingAssessmentController::class)
    ->prefix('hr/training-assessments')
    ->name('hr.training-assessments.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{trainingAssessment}', 'show')->whereNumber('trainingAssessment')->middleware('permission:view-training-assessments')->name('show');
        Route::post('/', 'store')->middleware('permission:create-training-assessments')->name('store');
        Route::put('{trainingAssessment}', 'update')->middleware('permission:edit-training-assessments')->name('update');
        Route::delete('{trainingAssessment}', 'destroy')->middleware('permission:delete-training-assessments')->name('destroy');
    });
