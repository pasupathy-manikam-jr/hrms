<?php

use App\Http\Controllers\System\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-users'])
    ->controller(UserController::class)
    ->prefix('users')
    ->name('users.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{user}', 'show')->whereNumber('user')->name('show');
        Route::post('/', 'store')->middleware('permission:create-users')->name('store');
        Route::put('{user}', 'update')->middleware('permission:edit-users')->name('update');
        Route::put('{user}/reset-password', 'resetPassword')->middleware('permission:reset-password-users')->name('reset-password');
        Route::put('{user}/toggle-status', 'toggleStatus')->middleware('permission:toggle-status-users')->name('toggle-status');
        Route::delete('{user}', 'destroy')->middleware('permission:delete-users')->name('destroy');
    });
