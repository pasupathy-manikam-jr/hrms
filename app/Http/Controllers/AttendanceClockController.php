<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
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
        $employee = $this->employee($request);
        $now = AttendanceRecord::now();

        if ($employee->todayAttendance()) {
            $this->fail(__('You have already clocked in today.'));
        }

        $record = new AttendanceRecord([
            'employee_id' => $employee->id,
            'date' => $now->toDateString(),
            'clock_in' => $now->format('H:i'),
            'status' => 'present',
        ]);
        $record->computeTimes($employee->shift)->save();

        return $this->done(__('Clocked in successfully.'));
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $employee = $this->employee($request);
        $now = AttendanceRecord::now();

        // Yesterday too, so a night shift can clock out after midnight.
        $record = $employee->attendanceRecords()
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->whereDate('date', '>=', $now->subDay()->toDateString())
            ->latest('date')
            ->first();

        if (! $record) {
            $this->fail(__('You have not clocked in today.'));
        }

        $record->clock_out = $now->format('H:i');
        $record->computeTimes($employee->shift)->save();

        return $this->done(__('Clocked out successfully.'));
    }

    private function employee(Request $request): Employee
    {
        /** @var User $user */
        $user = $request->user();

        return $user->employee ?? $this->fail(__('Your account has no employee record.'));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['attendance' => $message]);
    }
}
