<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveBalances;
use App\Support\LeaveRequests;
use App\Support\TableQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in employee's own leave: types with balances, applications, applying and withdrawing.
 */
class LeaveController extends Controller
{
    public function types(Request $request): JsonResponse
    {
        $employeeId = $this->user($request)->employee()->value('id');
        $year = today()->year;

        return response()->json(LeaveType::query()->where('status', 'active')->orderBy('id')->get(['id', 'name', 'color', 'is_paid', 'max_days_per_year'])
            ->map(fn (LeaveType $type) => [...$type->toArray(), 'remaining' => $employeeId ? LeaveBalances::remaining($employeeId, $type, $year) : null]));
    }

    /**
     * @return LengthAwarePaginator<int, LeaveApplication>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $query = LeaveApplication::query()
            ->where('employee_id', $this->user($request)->employee()->value('id') ?? 0)
            ->with('leaveType:id,name,color', 'approver:id,name')
            ->when(in_array($request->input('status'), LeaveApplication::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return TableQuery::paginate($query, $request, [], ['start_date', 'created_at']);
    }

    public function store(Request $request): JsonResponse
    {
        $application = LeaveRequests::submit(LeaveRequests::validate($request));

        return response()->json(['message' => __('Leave application submitted successfully.'), 'application' => $application->load('leaveType:id,name,color')], 201);
    }

    /**
     * Withdraw one of the employee's own pending applications.
     */
    public function destroy(Request $request, int $leaveApplication): JsonResponse
    {
        LeaveApplication::query()
            ->where('employee_id', $this->user($request)->employee()->value('id') ?? 0)
            ->where('status', 'pending')
            ->findOrFail($leaveApplication)
            ->delete();

        return response()->json(['message' => __('Leave application deleted successfully.')]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
