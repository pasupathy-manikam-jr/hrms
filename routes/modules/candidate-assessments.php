<?php

use App\Http\Controllers\Recruitment\CandidateAssessmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-candidate-assessments'])
    ->controller(CandidateAssessmentController::class)
    ->prefix('hr/recruitment/candidate-assessments')
    ->name('hr.recruitment.candidate-assessments.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-candidate-assessments')->name('store');
        Route::put('{candidateAssessment}', 'update')->middleware('permission:edit-candidate-assessments')->name('update');
        Route::delete('{candidateAssessment}', 'destroy')->middleware('permission:delete-candidate-assessments')->name('destroy');
    });
