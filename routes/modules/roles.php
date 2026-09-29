<?php

use App\Http\Controllers\System\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-roles'])
    ->controller(RoleController::class)
    ->prefix('roles')
    ->name('roles.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->middleware('permission:create-roles')->name('create');
        Route::post('/', 'store')->middleware('permission:create-roles')->name('store');
        Route::get('{role}', 'show')->whereNumber('role')->name('show');
        Route::get('{role}/edit', 'edit')->middleware('permission:edit-roles')->name('edit');
        Route::put('{role}', 'update')->middleware('permission:edit-roles')->name('update');
        Route::delete('{role}', 'destroy')->middleware('permission:delete-roles')->name('destroy');
    });
