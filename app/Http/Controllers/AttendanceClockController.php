<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Clock in / clock out for the signed-in user's own employee record.
 */
class AttendanceClockController extends Controller
{
    public function clockIn(Request $request): RedirectResponse
    {
        $this->employee($request)->clockIn();

        return $this->done(__('Clocked in successfully.'));
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $this->employee($request)->clockOut();

        return $this->done(__('Clocked out successfully.'));
    }

    private function employee(Request $request): Employee
    {
        /** @var User $user */
        $user = $request->user();

        return $user->employee ?? throw ValidationException::withMessages(['attendance' => __('Your account has no employee record.')]);
    }
}
