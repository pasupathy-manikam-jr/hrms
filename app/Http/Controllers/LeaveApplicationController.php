<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\Csv;
use App\Support\LeaveBalances;
use App\Support\TableQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['week_start' => ['nullable', 'date_format:Y-m-d'], 'calendar_employee_id' => ['nullable', 'integer']]);

        $weekStart = ($request->date('week_start') ?? today())->toImmutable()->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6);

        // The demo's weekly calendar: one row per visible employee, with their approved leave that week.
        // ponytail: every visible employee is one row (no paging); fine for company-sized headcounts.
        $calendarEmployees = Employee::query()->visibleTo($user)
            ->with('user:id,name,avatar_path', 'designation:id,name')
            ->where('employee_status', '!=', 'terminated')
            ->when($request->integer('calendar_employee_id'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        $query = $this->filteredQuery($request);

        $counts = TableQuery::countBy($query, 'status');

        $this->applyStatus($query, $request);

        return Inertia::render('hr/leave-applications/index', [
            // Search is applied above so the status counts match it; TableQuery gets no searchable columns.
            'weekStart' => $weekStart->toDateString(),
            'calendarRows' => $calendarEmployees->map(fn (Employee $employee) => [
                'id' => $employee->id, 'name' => $employee->user->name, 'avatar' => $employee->user->avatar,
                'gender' => $employee->gender, 'designation' => $employee->designation?->name,
            ]),
            'calendarLeaves' => LeaveApplication::query()->visibleTo($user)
                ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'leaveType:id,name,color,is_paid', 'leavePolicy:id,name', 'approver:id,name')
                ->where('status', 'approved')
                ->whereIn('employee_id', $calendarEmployees->modelKeys())
                ->whereDate('start_date', '<=', $weekEnd)
                ->whereDate('end_date', '>=', $weekStart)
                ->orderBy('start_date')
                ->get(),
            'leaveApplications' => TableQuery::paginate($query, $request, [], ['start_date', 'end_date', 'total_days', 'status', 'created_at']),
            'employees' => $user->can('manage-any-leave-applications')
                ? Employee::query()->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
                    ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id])
                : [],
            'leaveTypes' => LeaveType::query()->where('status', 'active')->orderBy('id')->get(['id', 'name', 'color']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(LeaveApplication::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'leave_type_id', 'employee_id', 'week_start', 'calendar_employee_id']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $applications = $this->applyStatus($this->filteredQuery($request), $request)->orderByDesc('start_date')->orderBy('id')->lazy();

        return Csv::download('leave-applications-'.now()->format('Y-m-d').'.csv', [
            'Employee ID', 'Employee', 'Leave Type', 'Start Date', 'End Date', 'Total Days', 'Reason', 'Status', 'Approved By', 'Approved At', 'Manager Comments', 'Applied On',
        ], $applications->map(fn (LeaveApplication $a) => [
            $a->employee->employee_id, $a->employee->user->name, $a->leaveType->name, $a->start_date->toDateString(), $a->end_date->toDateString(),
            $a->total_days, $a->reason, $a->status, $a->approver?->name, $a->approved_at?->toDateTimeString(), $a->manager_comments, $a->created_at?->toDateTimeString(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $policy = $this->policyFor($data['leave_type_id']);
        $autoApprove = $policy && ! $policy->requires_approval;

        LeaveApplication::create([
            ...$data,
            'leave_policy_id' => $policy?->id,
            'status' => $autoApprove ? 'approved' : 'pending',
            'approved_at' => $autoApprove ? now() : null,
        ]);

        return $this->done(__('Leave application submitted successfully.'));
    }

    public function update(Request $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->authorizePending($request, $leaveApplication);

        $data = $this->validated($request, $leaveApplication);

        $leaveApplication->update([...$data, 'leave_policy_id' => $this->policyFor($data['leave_type_id'])?->id]);

        return $this->done(__('Leave application updated successfully.'));
    }

    public function destroy(Request $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Staff may remove any record; employees can only withdraw their own pending requests.
        $user->can('manage-any-leave-applications')
            ? $this->authorizeRecord($request, $leaveApplication)
            : $this->authorizePending($request, $leaveApplication);

        $leaveApplication->delete();

        return $this->done(__('Leave application deleted successfully.'));
    }

    public function approve(Request $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->authorizePending($request, $leaveApplication);
        $data = $request->validate(['manager_comments' => ['nullable', 'string', 'max:1000']]);

        // The balance may have shrunk since the request was made (e.g. a manual adjustment).
        $remaining = LeaveBalances::remaining($leaveApplication->employee_id, $leaveApplication->leaveType, $leaveApplication->start_date->year, $leaveApplication->id);

        if ($leaveApplication->total_days > $remaining) {
            throw ValidationException::withMessages(['manager_comments' => __('This request exceeds the remaining leave balance (:days days).', ['days' => max(0, $remaining)])]);
        }

        $this->decide($request, $leaveApplication, 'approved', $data['manager_comments'] ?? null);

        return $this->done(__('Leave application approved successfully.'));
    }

    public function reject(Request $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->authorizePending($request, $leaveApplication);
        $data = $request->validate(['manager_comments' => ['nullable', 'string', 'max:1000']]);

        $this->decide($request, $leaveApplication, 'rejected', $data['manager_comments'] ?? null);

        return $this->done(__('Leave application rejected successfully.'));
    }

    private function decide(Request $request, LeaveApplication $leaveApplication, string $status, ?string $comments): void
    {
        $leaveApplication->update([
            'status' => $status,
            'manager_comments' => $comments,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * The list's visible applications with its employee/leave type filters and search (before the status tab).
     *
     * @return Builder<LeaveApplication>
     */
    private function filteredQuery(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        return LeaveApplication::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'leaveType:id,name,color', 'leavePolicy:id,name', 'approver:id,name')
            ->when($request->integer('leave_type_id'), fn ($q, $id) => $q->where('leave_type_id', $id))
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('reason', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));
    }

    /**
     * @param  Builder<LeaveApplication>  $query
     * @return Builder<LeaveApplication>
     */
    private function applyStatus(Builder $query, Request $request): Builder
    {
        return $query->when(in_array($request->input('status'), LeaveApplication::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));
    }

    private function authorizeRecord(Request $request, LeaveApplication $leaveApplication): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(LeaveApplication::query()->visibleTo($user)->whereKey($leaveApplication->id)->exists(), 404);
    }

    private function authorizePending(Request $request, LeaveApplication $leaveApplication): void
    {
        $this->authorizeRecord($request, $leaveApplication);
        abort_unless($leaveApplication->status === 'pending', 403, __('Only pending leave applications can be changed.'));
    }

    private function policyFor(int $leaveTypeId): ?LeavePolicy
    {
        return LeavePolicy::query()->where(['leave_type_id' => $leaveTypeId, 'status' => 'active'])->oldest('id')->first();
    }

    /**
     * Validate the request and work out total_days server-side.
     *
     * @return array{employee_id: int, leave_type_id: int, start_date: string, end_date: string, reason: string|null, total_days: int}
     */
    private function validated(Request $request, ?LeaveApplication $leaveApplication = null): array
    {
        /** @var User $user */
        $user = $request->user();
        $canApplyForOthers = $user->can('manage-any-leave-applications');

        $data = $request->validate([
            'employee_id' => [Rule::requiredIf($canApplyForOthers), 'integer', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('status', 'active')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Employees only ever apply for themselves: the employee comes from the signed-in user.
        if (! $canApplyForOthers) {
            $data['employee_id'] = $leaveApplication->employee_id ?? $user->employee()->value('id')
                ?? throw ValidationException::withMessages(['employee_id' => __('Your account has no employee profile.')]);
        }

        $employeeId = (int) $data['employee_id'];
        $leaveType = LeaveType::query()->whereKey($data['leave_type_id'])->firstOrFail();
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $totalDays = LeaveApplication::workingDaysBetween($start, $end, Employee::query()->whereKey($employeeId)->value('branch_id'));
        $policy = $this->policyFor($leaveType->id);

        if ($totalDays === 0) {
            throw ValidationException::withMessages(['end_date' => __('The selected dates contain no working days.')]);
        }

        if ($policy && ($totalDays < $policy->min_days_per_application || $totalDays > $policy->max_days_per_application)) {
            throw ValidationException::withMessages(['end_date' => __('This leave type allows :min to :max days per application.', [
                'min' => $policy->min_days_per_application, 'max' => $policy->max_days_per_application,
            ])]);
        }

        $overlaps = LeaveApplication::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', LeaveApplication::ACTIVE_STATUSES)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->when($leaveApplication, fn ($q) => $q->whereKeyNot($leaveApplication->id))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => __('These dates overlap another pending or approved leave application.')]);
        }

        $remaining = LeaveBalances::remaining($employeeId, $leaveType, $start->year, $leaveApplication?->id);

        if ($totalDays > $remaining) {
            throw ValidationException::withMessages(['leave_type_id' => __('This request exceeds the remaining leave balance (:days days).', ['days' => max(0, $remaining)])]);
        }

        return [
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => $data['reason'] ?? null,
            'total_days' => $totalDays,
        ];
    }
}
