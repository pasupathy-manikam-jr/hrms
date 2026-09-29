<?php

use App\Http\Controllers\System\EmailTemplateController;
use Illuminate\Support\Facades\Route;

// The demo has no email-template permissions; they sit with the email settings.
Route::middleware(['auth', 'verified', 'permission:manage-email-settings'])
    ->controller(EmailTemplateController::class)
    ->prefix('email-templates')
    ->name('email-templates.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('preview', 'preview')->name('preview');
        Route::get('{emailTemplate}', 'show')->name('show');
        Route::put('{emailTemplate}', 'update')->name('update');
    });
