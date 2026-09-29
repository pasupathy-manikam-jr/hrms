<?php

use App\Http\Controllers\Recruitment\ChecklistItemController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-checklist-items permission, so toggling needs edit-checklist-items.
Route::middleware(['auth', 'verified', 'permission:manage-checklist-items'])
    ->controller(ChecklistItemController::class)
    ->prefix('hr/recruitment/checklist-items')
    ->name('hr.recruitment.checklist-items.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-checklist-items')->name('store');
        Route::put('{checklistItem}', 'update')->middleware('permission:edit-checklist-items')->name('update');
        Route::put('{checklistItem}/toggle-status', 'toggleStatus')->middleware('permission:edit-checklist-items')->name('toggle-status');
        Route::delete('{checklistItem}', 'destroy')->middleware('permission:delete-checklist-items')->name('destroy');
    });
