<?php

use App\Http\Controllers\Benchmark\BenchmarkJobController;
use App\Http\Controllers\Benchmark\SurveyAnalyticsController;
use App\Http\Controllers\Benchmark\SurveyCorrectionController;
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

        Route::controller(BenchmarkJobController::class)->prefix('jobs')->name('jobs.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('permission:upload-benchmark-survey')->name('store');
            Route::put('{job}', 'update')->middleware('permission:upload-benchmark-survey')->name('update');
            Route::delete('{job}', 'destroy')->middleware('permission:delete-benchmark-survey')->name('destroy');
        });

        Route::controller(SurveyParticipantController::class)->prefix('participants')->name('participants.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('upload', 'upload')->middleware(['permission:upload-benchmark-survey', 'throttle:20,1'])->name('upload');
            Route::get('issues', 'issues')->middleware('permission:export-benchmark-survey')->name('issues');
            Route::get('{participant}', 'show')->whereNumber('participant')->name('show');
            Route::get('{participant}/download', 'download')->name('download');
            Route::delete('{participant}', 'destroy')->middleware('permission:delete-benchmark-survey')->name('destroy');
        });

        Route::controller(SurveyCorrectionController::class)->prefix('participants/{participant}')->name('participants.')
            ->middleware('permission:upload-benchmark-survey')->scopeBindings()->group(function () {
                Route::put('profile', 'profile')->name('profile');
                Route::post('salary-rows', 'storeRow')->name('rows.store');
                Route::put('salary-rows/{salaryRow}', 'updateRow')->name('rows.update');
                Route::delete('salary-rows/{salaryRow}', 'destroyRow')->name('rows.destroy');
                Route::put('benefits', 'benefits')->name('benefits');
                Route::put('attrition', 'attrition')->name('attrition');
                Route::post('accept', 'accept')->name('accept');
            });

        Route::controller(SurveyAnalyticsController::class)->prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('export', 'export')->middleware('permission:export-benchmark-survey')->name('export');
            Route::get('report', 'pdf')->middleware('permission:export-benchmark-survey')->name('pdf');
        });
    });
