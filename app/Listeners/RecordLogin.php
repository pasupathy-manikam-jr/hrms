<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class RecordLogin
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            LoginHistory::record($event->user, $this->request->ip(), $this->request->userAgent());
        }
    }
}
