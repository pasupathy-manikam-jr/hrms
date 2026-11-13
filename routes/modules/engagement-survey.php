<?php

use App\Http\Controllers\Engagement\EngagementCampaignController;
use App\Http\Controllers\Engagement\EngagementStatementController;
use App\Http\Controllers\Engagement\MySurveyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('engagement')->name('engagement.')->group(function () {
    Route::middleware('permission:manage-engagement-survey')->group(function () {
        Route::controller(EngagementCampaignController::class)->prefix('campaigns')->name('campaigns.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('permission:create-engagement-survey')->name('store');
            Route::get('{campaign}', 'show')->name('show');
            Route::put('{campaign}', 'update')->middleware('permission:edit-engagement-survey')->name('update');
            Route::delete('{campaign}', 'destroy')->middleware('permission:delete-engagement-survey')->name('destroy');
        });

        Route::controller(EngagementStatementController::class)->prefix('statements')->name('statements.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('permission:create-engagement-survey')->name('store');
            Route::put('{statement}', 'update')->middleware('permission:edit-engagement-survey')->name('update');
            Route::put('{statement}/toggle-status', 'toggleStatus')->middleware('permission:edit-engagement-survey')->name('toggle-status');
            Route::delete('{statement}', 'destroy')->middleware('permission:delete-engagement-survey')->name('destroy');
        });
    });

    Route::controller(MySurveyController::class)->middleware('permission:take-engagement-survey')->prefix('my-surveys')->name('my.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{campaign}', 'show')->name('show');
        Route::post('{campaign}', 'store')->middleware('throttle:20,1')->name('store');
    });
});
