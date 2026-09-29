<?php

namespace App\Models;

use Database\Factories\LeavePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $leave_type_id
 * @property int $min_days_per_application
 * @property int $max_days_per_application
 * @property bool $requires_approval
 */
#[Fillable([
    'name', 'description', 'leave_type_id', 'accrual_type', 'accrual_rate', 'carry_forward_limit',
    'min_days_per_application', 'max_days_per_application', 'requires_approval', 'status',
])]
class LeavePolicy extends Model
{
    /** @use HasFactory<LeavePolicyFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    public const ACCRUAL_TYPES = ['yearly', 'monthly'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_type_id' => 'integer',
            'accrual_rate' => 'decimal:2',
            'carry_forward_limit' => 'integer',
            'min_days_per_application' => 'integer',
            'max_days_per_application' => 'integer',
            'requires_approval' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
