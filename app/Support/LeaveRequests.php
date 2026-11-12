<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Leave application rules shared by the web pages and the mobile API.
 */
class LeaveRequests
{
    /**
     * Create the application; auto-approved when its leave policy needs no approval.
     *
     * @param  array{employee_id: int, leave_type_id: int, start_date: string, end_date: string, reason: string|null, total_days: int}  $data
     */
    public static function submit(array $data): LeaveApplication
    {
        $policy = self::policyFor($data['leave_type_id']);
        $autoApprove = $policy && ! $policy->requires_approval;

        return LeaveApplication::create([
            ...$data,
            'leave_policy_id' => $policy?->id,
            'status' => $autoApprove ? 'approved' : 'pending',
            'approved_at' => $autoApprove ? now() : null,
        ]);
    }

    public static function policyFor(int $leaveTypeId): ?LeavePolicy
    {
        return LeavePolicy::query()->where(['leave_type_id' => $leaveTypeId, 'status' => 'active'])->oldest('id')->first();
    }

    /**
     * Validate the request and work out total_days server-side.
     *
     * @return array{employee_id: int, leave_type_id: int, start_date: string, end_date: string, reason: string|null, total_days: int}
     */
    public static function validate(Request $request, ?LeaveApplication $leaveApplication = null): array
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
        $policy = self::policyFor($leaveType->id);

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
