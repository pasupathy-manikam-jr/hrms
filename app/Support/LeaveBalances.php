<?php

namespace App\Support;

use App\Models\LeaveApplication;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use Illuminate\Support\Collection;

/**
 * Leave balances computed on the fly: allocated = the type's max days per year + carried forward
 * + manual adjustment; used/pending = approved/pending application days starting in that year.
 */
class LeaveBalances
{
    /**
     * Balances keyed [employee_id][leave_type_id].
     *
     * @param  array<int, int>  $employeeIds
     * @param  Collection<int, LeaveType>  $leaveTypes
     * @return array<int, array<int, array{leave_type_id: int, allocated: int, carried_forward: int, manual_adjustment: int, adjustment_reason: string|null, used: int, pending: int, remaining: int}>>
     */
    public static function for(array $employeeIds, Collection $leaveTypes, int $year, ?int $exceptApplicationId = null): array
    {
        // ponytail: an application counts in the year it starts; split by year if leave across New Year matters.
        $days = LeaveApplication::query()
            ->toBase()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', LeaveApplication::ACTIVE_STATUSES)
            ->whereYear('start_date', $year)
            ->when($exceptApplicationId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->selectRaw('employee_id, leave_type_id, status, sum(total_days) as days')
            ->groupBy('employee_id', 'leave_type_id', 'status')
            ->get()
            ->groupBy(fn ($row) => "{$row->employee_id}.{$row->leave_type_id}.{$row->status}");

        $adjustments = LeaveBalanceAdjustment::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('year', $year)
            ->get()
            ->keyBy(fn (LeaveBalanceAdjustment $a) => "{$a->employee_id}.{$a->leave_type_id}");

        $balances = [];

        foreach ($employeeIds as $employeeId) {
            foreach ($leaveTypes as $type) {
                $adjustment = $adjustments->get("{$employeeId}.{$type->id}");
                $carried = $adjustment->carried_forward ?? 0;
                $manual = $adjustment->manual_adjustment ?? 0;
                $allocated = $type->max_days_per_year + $carried + $manual;
                $used = (int) ($days->get("{$employeeId}.{$type->id}.approved")?->first()->days ?? 0);
                $pending = (int) ($days->get("{$employeeId}.{$type->id}.pending")?->first()->days ?? 0);

                $balances[$employeeId][$type->id] = [
                    'leave_type_id' => $type->id,
                    'allocated' => $allocated,
                    'carried_forward' => $carried,
                    'manual_adjustment' => $manual,
                    'adjustment_reason' => $adjustment?->adjustment_reason,
                    'used' => $used,
                    'pending' => $pending,
                    'remaining' => $allocated - $used - $pending,
                ];
            }
        }

        return $balances;
    }

    /**
     * Days still free to request for one employee and type, ignoring the application being edited.
     */
    public static function remaining(int $employeeId, LeaveType $leaveType, int $year, ?int $exceptApplicationId = null): int
    {
        return self::for([$employeeId], collect([$leaveType]), $year, $exceptApplicationId)[$employeeId][$leaveType->id]['remaining'];
    }
}
