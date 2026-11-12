<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in employee's own attendance: today's record, clocking in/out and history.
 */
class AttendanceController extends Controller
{
    /**
     * @return LengthAwarePaginator<int, AttendanceRecord>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        return TableQuery::paginate($this->employee($request)->attendanceRecords()->getQuery(), $request, [], ['date'], 'date');
    }

    public function today(Request $request): JsonResponse
    {
        return response()->json(['record' => $this->employee($request)->todayAttendance()]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        return response()->json(['message' => __('Clocked in successfully.'), 'record' => $this->employee($request)->clockIn()], 201);
    }

    public function clockOut(Request $request): JsonResponse
    {
        return response()->json(['message' => __('Clocked out successfully.'), 'record' => $this->employee($request)->clockOut()]);
    }

    private function employee(Request $request): Employee
    {
        /** @var User $user */
        $user = $request->user();

        return $user->employee ?? throw ValidationException::withMessages(['attendance' => __('Your account has no employee record.')]);
    }
}
