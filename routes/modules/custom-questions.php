<?php

use App\Http\Controllers\Recruitment\CustomQuestionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-custom-questions'])
    ->controller(CustomQuestionController::class)
    ->prefix('hr/recruitment/custom-questions')
    ->name('hr.recruitment.custom-questions.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-custom-questions')->name('store');
        Route::put('{customQuestion}', 'update')->middleware('permission:edit-custom-questions')->name('update');
        Route::delete('{customQuestion}', 'destroy')->middleware('permission:delete-custom-questions')->name('destroy');
    });
