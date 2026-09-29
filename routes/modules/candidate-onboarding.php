<?php

use App\Http\Controllers\Recruitment\CandidateOnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-candidate-onboarding'])
    ->controller(CandidateOnboardingController::class)
    ->prefix('hr/recruitment/candidate-onboarding')
    ->name('hr.recruitment.candidate-onboarding.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{candidateOnboarding}', 'show')->whereNumber('candidateOnboarding')->middleware('permission:view-candidate-onboarding')->name('show');
        Route::post('/', 'store')->middleware('permission:create-candidate-onboarding')->name('store');
        Route::put('{candidateOnboarding}', 'update')->middleware('permission:edit-candidate-onboarding')->name('update');
        Route::delete('{candidateOnboarding}', 'destroy')->middleware('permission:delete-candidate-onboarding')->name('destroy');
        Route::put('{candidateOnboarding}/tasks/{task}', 'updateTask')->middleware('permission:manage-candidate-onboarding-status')->scopeBindings()->name('tasks.update');
    });
