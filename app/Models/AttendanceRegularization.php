<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $attendance_record_id
 * @property CarbonInterface $date
 * @property string $requested_clock_in
 * @property string|null $requested_clock_out
 * @property string $status
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id', 'attendance_record_id', 'date', 'requested_clock_in', 'requested_clock_out',
    'original_clock_in', 'original_clock_out', 'reason', 'status', 'manager_comments', 'approved_by', 'approved_at',
])]
class AttendanceRegularization extends Model
{
    use BelongsToEmployee;

    public const MODULE = 'attendance-regularizations';

    public const STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'attendance_record_id' => 'integer',
            'date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AttendanceRecord, $this>
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
