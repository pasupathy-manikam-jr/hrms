<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Carried-forward days and manual corrections for one employee, leave type and year.
 *
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $carried_forward
 * @property int $manual_adjustment
 * @property string|null $adjustment_reason
 */
#[Fillable(['employee_id', 'leave_type_id', 'year', 'carried_forward', 'manual_adjustment', 'adjustment_reason'])]
class LeaveBalanceAdjustment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'year' => 'integer',
            'carried_forward' => 'integer',
            'manual_adjustment' => 'integer',
        ];
    }
}
