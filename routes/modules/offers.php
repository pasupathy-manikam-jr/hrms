<?php

use App\Http\Controllers\Recruitment\OfferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-offers'])
    ->controller(OfferController::class)
    ->prefix('hr/recruitment/offers')
    ->name('hr.recruitment.offers.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('kanban', 'kanban')->name('kanban');
        Route::get('{offer}', 'show')->whereNumber('offer')->middleware('permission:view-offers')->name('show');
        Route::post('/', 'store')->middleware('permission:create-offers')->name('store');
        Route::put('{offer}', 'update')->middleware('permission:edit-offers')->name('update');
        Route::put('{offer}/update-status', 'updateStatus')->middleware('permission:approve-offers')->name('update-status');
        Route::delete('{offer}', 'destroy')->middleware('permission:delete-offers')->name('destroy');
    });
