<?php

use App\Http\Controllers\PromotionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-promotions'])
    ->controller(PromotionController::class)
    ->prefix('hr/promotions')
    ->name('hr.promotions.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-promotions')->name('store');
        Route::put('{promotion}', 'update')->middleware('permission:edit-promotions')->name('update');
        Route::put('{promotion}/status', 'changeStatus')->middleware('permission:approve-promotions|reject-promotions')->name('change-status');
        Route::get('{promotion}/document', 'document')->name('document');
        Route::delete('{promotion}', 'destroy')->middleware('permission:delete-promotions')->name('destroy');
    });
