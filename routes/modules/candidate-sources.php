<?php

use App\Http\Controllers\Recruitment\CandidateSourceController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-candidate-sources permission, so toggling needs edit-candidate-sources.
Route::middleware(['auth', 'verified', 'permission:manage-candidate-sources'])
    ->controller(CandidateSourceController::class)
    ->prefix('hr/recruitment/candidate-sources')
    ->name('hr.recruitment.candidate-sources.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-candidate-sources')->name('store');
        Route::put('{candidateSource}', 'update')->middleware('permission:edit-candidate-sources')->name('update');
        Route::put('{candidateSource}/toggle-status', 'toggleStatus')->middleware('permission:edit-candidate-sources')->name('toggle-status');
        Route::delete('{candidateSource}', 'destroy')->middleware('permission:delete-candidate-sources')->name('destroy');
    });
