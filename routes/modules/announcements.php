<?php

use App\Http\Controllers\AnnouncementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-announcements'])
    ->controller(AnnouncementController::class)
    ->prefix('hr/announcements')
    ->name('hr.announcements.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('{announcement}', 'show')->whereNumber('announcement')->name('show');
        Route::get('{announcement}/statistics', 'statistics')->whereNumber('announcement')->name('statistics');
        Route::get('{announcement}/document', 'document')->whereNumber('announcement')->name('document');
        Route::post('/', 'store')->middleware('permission:create-announcements')->name('store');
        Route::put('{announcement}', 'update')->middleware('permission:edit-announcements')->name('update');
        Route::delete('{announcement}', 'destroy')->middleware('permission:delete-announcements')->name('destroy');
    });
