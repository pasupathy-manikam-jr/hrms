<?php

use App\Models\LoginHistory;
use App\Models\Termination;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('terminations:complete-due', function () {
    $this->info(Termination::completeDue().' termination(s) completed.');
})->purpose('Terminate employees whose approved termination date has arrived');

Schedule::command('terminations:complete-due')->daily();

Schedule::command('model:prune', ['--model' => [LoginHistory::class]])->daily();
