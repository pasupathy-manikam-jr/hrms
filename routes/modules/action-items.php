<?php

use App\Http\Controllers\ActionItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-action-items'])
    ->controller(ActionItemController::class)
    ->prefix('meetings/action-items')
    ->name('meetings.action-items.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-action-items')->name('store');
        Route::put('{actionItem}', 'update')->middleware('permission:edit-action-items')->name('update');
        Route::put('{actionItem}/progress', 'progress')->middleware('permission:edit-action-items')->name('progress');
        Route::delete('{actionItem}', 'destroy')->middleware('permission:delete-action-items')->name('destroy');
    });
