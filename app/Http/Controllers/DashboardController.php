<?php

namespace App\Http\Controllers;

use App\Support\Dashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the company dashboard, or the personal one for users limited to their own records.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->cannot('manage-any-employees')) {
            $employee = $user->employee()->with('shift')->first();

            return Inertia::render('employee-dashboard', [
                'dashboardData' => Dashboard::employee($user),
                'todayAttendance' => $employee?->todayAttendance()?->only(['clock_in', 'clock_out']),
                'shift' => $employee?->shift?->only(['name', 'start_time', 'end_time']),
            ]);
        }

        return Inertia::render('dashboard', [
            'dashboardData' => Dashboard::company(
                $user,
                $request->integer('hiring_year') ?: null,
                $request->integer('payroll_year') ?: null,
            ),
        ]);
    }
}
