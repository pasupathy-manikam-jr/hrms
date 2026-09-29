<?php

use App\Http\Controllers\BranchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-branches'])
    ->controller(BranchController::class)
    ->prefix('hr/branches')
    ->name('hr.branches.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-branches')->name('store');
        Route::put('{branch}', 'update')->middleware('permission:edit-branches')->name('update');
        Route::put('{branch}/toggle-status', 'toggleStatus')->middleware('permission:toggle-status-branches')->name('toggle-status');
        Route::delete('{branch}', 'destroy')->middleware('permission:delete-branches')->name('destroy');
    });
