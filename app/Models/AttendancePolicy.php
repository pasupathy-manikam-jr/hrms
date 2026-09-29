<?php

namespace App\Models;

use Database\Factories\AttendancePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'late_arrival_grace', 'early_departure_grace', 'half_day_threshold', 'overtime_rate_per_hour', 'status'])]
class AttendancePolicy extends Model
{
    /** @use HasFactory<AttendancePolicyFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'late_arrival_grace' => 'integer',
            'early_departure_grace' => 'integer',
            'half_day_threshold' => 'float',
            'overtime_rate_per_hour' => 'float',
        ];
    }
}
