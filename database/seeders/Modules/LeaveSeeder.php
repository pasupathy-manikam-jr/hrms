<?php

namespace Database\Seeders\Modules;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class LeaveSeeder extends Seeder
{
    /**
     * Seed the demo's leave data. Add further leave modules as their own seed*() steps.
     */
    public function run(): void
    {
        $this->seedLeaveTypes();
        $this->seedLeavePolicies();
        $this->seedLeaveApplications();
        $this->seedThisWeeksLeave();
        $this->seedLeaveBalanceAdjustments();
    }

    private function seedLeaveTypes(): void
    {
        foreach (File::json(database_path('demo/leave-types.json'), JSON_THROW_ON_ERROR) as $leaveType) {
            LeaveType::query()->firstOrCreate(['name' => $leaveType['name']], $leaveType);
        }
    }

    private function seedLeavePolicies(): void
    {
        foreach (File::json(database_path('demo/leave-policies.json'), JSON_THROW_ON_ERROR) as $policy) {
            $leaveType = LeaveType::query()->where('name', $policy['leave_type'])->first();

            if ($leaveType) {
                LeavePolicy::query()->firstOrCreate(
                    ['name' => $policy['name']],
                    [...Arr::except($policy, 'leave_type'), 'leave_type_id' => $leaveType->id],
                );
            }
        }
    }

    /**
     * The demo's applications, matched to our employees by email, then by name.
     */
    private function seedLeaveApplications(): void
    {
        foreach (File::json(database_path('demo/leave-applications.json'), JSON_THROW_ON_ERROR) as $row) {
            $user = User::query()->where('email', $row['employee_email'])->first()
                ?? User::query()->where('name', $row['employee_name'])->first();
            $employee = $user ? Employee::query()->where('user_id', $user->id)->first() : null;
            $leaveType = LeaveType::query()->where('name', $row['leave_type'])->first();

            if (! $employee || ! $leaveType) {
                continue;
            }

            $application = LeaveApplication::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'start_date' => $row['start_date']],
                [
                    'leave_policy_id' => LeavePolicy::query()->where('leave_type_id', $leaveType->id)->value('id'),
                    'end_date' => $row['end_date'],
                    'total_days' => LeaveApplication::workingDaysBetween(Carbon::parse($row['start_date']), Carbon::parse($row['end_date'])),
                    'reason' => $row['reason'],
                    'status' => $row['status'],
                    'manager_comments' => $row['manager_comments'],
                    'approved_by' => $row['approved_by'] ? User::query()->where('email', $row['approved_by'])->value('id') : null,
                    'approved_at' => $row['approved_at'],
                ],
            );

            if ($application->wasRecentlyCreated) {
                $application->forceFill(['created_at' => $row['created_at'], 'updated_at' => $row['created_at']])->save();
            }
        }
    }

    /**
     * The demo's approved leave in the current week, so the leave calendar opens with something on it.
     * "day" counts from this week's Monday; re-seeding in a later week moves the leave there.
     */
    private function seedThisWeeksLeave(): void
    {
        $monday = today()->startOfWeek(Carbon::MONDAY);
        // The Malaysian names seeder may already have renamed the demo's users.
        $renamed = collect(File::json(database_path('demo/malaysian-names.json'), JSON_THROW_ON_ERROR))->pluck('email', 'from');

        foreach (File::json(database_path('demo/leave-calendar.json'), JSON_THROW_ON_ERROR) as $row) {
            $employee = Employee::query()->whereRelation('user', fn ($q) => $q->whereIn('email', [$row['employee_email'], $renamed[$row['employee_email']] ?? null]))->first();
            $leaveType = LeaveType::query()->where('name', $row['leave_type'])->first();

            if (! $employee || ! $leaveType) {
                continue;
            }

            $start = $monday->copy()->addDays($row['day']);
            $end = $start->copy()->addDays($row['days'] - 1);

            LeaveApplication::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'reason' => $row['reason'], 'status' => 'approved'],
                [
                    'leave_policy_id' => LeavePolicy::query()->where('leave_type_id', $leaveType->id)->value('id'),
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'total_days' => LeaveApplication::workingDaysBetween($start, $end),
                    'approved_by' => User::query()->where('email', 'company@example.com')->value('id'),
                    'approved_at' => $monday->copy()->subDays(3),
                ],
            );
        }
    }

    /**
     * Carried-forward days and manual corrections; used and pending days come from the applications.
     */
    private function seedLeaveBalanceAdjustments(): void
    {
        foreach (File::json(database_path('demo/leave-balances.json'), JSON_THROW_ON_ERROR) as $row) {
            $employeeId = Employee::query()->where('employee_id', $row['employee_code'])->value('id')
                ?? Employee::query()->whereRelation('user', 'name', $row['employee_name'])->value('id');
            $leaveTypeId = LeaveType::query()->where('name', $row['leave_type'])->value('id');

            if ($employeeId && $leaveTypeId) {
                LeaveBalanceAdjustment::query()->firstOrCreate(
                    ['employee_id' => $employeeId, 'leave_type_id' => $leaveTypeId, 'year' => $row['year']],
                    Arr::only($row, ['carried_forward', 'manual_adjustment', 'adjustment_reason']),
                );
            }
        }
    }
}
