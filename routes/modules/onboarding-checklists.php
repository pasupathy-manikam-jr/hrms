<?php

use App\Http\Controllers\Recruitment\OnboardingChecklistController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-onboarding-checklists permission, so toggling needs edit-onboarding-checklists.
Route::middleware(['auth', 'verified', 'permission:manage-onboarding-checklists'])
    ->controller(OnboardingChecklistController::class)
    ->prefix('hr/recruitment/onboarding-checklists')
    ->name('hr.recruitment.onboarding-checklists.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-onboarding-checklists')->name('store');
        Route::put('{onboardingChecklist}', 'update')->middleware('permission:edit-onboarding-checklists')->name('update');
        Route::put('{onboardingChecklist}/toggle-status', 'toggleStatus')->middleware('permission:edit-onboarding-checklists')->name('toggle-status');
        Route::delete('{onboardingChecklist}', 'destroy')->middleware('permission:delete-onboarding-checklists')->name('destroy');
    });
