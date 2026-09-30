<?php

use App\Http\Controllers\Benchmark\SurveyAnalyticsController;
use App\Http\Controllers\Benchmark\SurveyCycleController;
use App\Http\Controllers\Benchmark\SurveyParticipantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-benchmark-survey'])
    ->prefix('benchmark')
    ->name('benchmark.')
    ->group(function () {
        Route::controller(SurveyCycleController::class)->prefix('cycles')->name('cycles.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('permission:upload-benchmark-survey')->name('store');
            Route::put('{cycle}', 'update')->middleware('permission:upload-benchmark-survey')->name('update');
            Route::delete('{cycle}', 'destroy')->middleware('permission:delete-benchmark-survey')->name('destroy');
            Route::get('{cycle}/template', 'template')->name('template');
        });

        Route::get('jobs', [SurveyCycleController::class, 'jobs'])->name('jobs.index');

        Route::controller(SurveyParticipantController::class)->prefix('participants')->name('participants.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('upload', 'upload')->middleware(['permission:upload-benchmark-survey', 'throttle:20,1'])->name('upload');
            Route::get('{participant}', 'show')->whereNumber('participant')->name('show');
            Route::get('{participant}/download', 'download')->name('download');
            Route::delete('{participant}', 'destroy')->middleware('permission:delete-benchmark-survey')->name('destroy');
        });

        Route::controller(SurveyAnalyticsController::class)->prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('export', 'export')->middleware('permission:export-benchmark-survey')->name('export');
            Route::get('report', 'pdf')->middleware('permission:export-benchmark-survey')->name('pdf');
        });
    });
