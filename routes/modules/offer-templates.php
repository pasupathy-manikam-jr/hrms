<?php

use App\Http\Controllers\Recruitment\OfferTemplateController;
use Illuminate\Support\Facades\Route;

// The demo has no toggle-status-offer-templates permission, so toggling needs edit-offer-templates.
Route::middleware(['auth', 'verified', 'permission:manage-offer-templates'])
    ->controller(OfferTemplateController::class)
    ->prefix('hr/recruitment/offer-templates')
    ->name('hr.recruitment.offer-templates.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->middleware('permission:create-offer-templates')->name('create');
        Route::get('{offerTemplate}/edit', 'edit')->whereNumber('offerTemplate')->middleware('permission:edit-offer-templates')->name('edit');
        Route::get('{offerTemplate}', 'show')->whereNumber('offerTemplate')->middleware('permission:view-offer-templates')->name('show');
        Route::post('/', 'store')->middleware('permission:create-offer-templates')->name('store');
        Route::put('{offerTemplate}', 'update')->middleware('permission:edit-offer-templates')->name('update');
        Route::put('{offerTemplate}/toggle-status', 'toggleStatus')->middleware('permission:edit-offer-templates')->name('toggle-status');
        Route::delete('{offerTemplate}', 'destroy')->middleware('permission:delete-offer-templates')->name('destroy');
    });
