<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonInterface;
use Database\Factories\WarningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $warning_by
 * @property string $warning_type
 * @property string $severity
 * @property CarbonInterface $warning_date
 * @property string $status
 * @property bool $has_improvement_plan
 * @property string|null $file_path
 */
#[Fillable([
    'employee_id', 'warning_by', 'warning_type', 'subject', 'severity', 'warning_date', 'expiry_date',
    'description', 'status', 'acknowledgment_date', 'employee_response', 'approved_by', 'approved_at', 'created_by',
    'has_improvement_plan', 'improvement_plan_goals', 'improvement_plan_start_date', 'improvement_plan_end_date',
    'improvement_plan_progress', 'file_path', 'file_name', 'file_type', 'file_size',
])]
class Warning extends Model
{
    use BelongsToEmployee;

    /** @use HasFactory<WarningFactory> */
    use HasFactory;

    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'warnings';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    public const MODULE = 'warnings';

    public const STATUSES = ['draft', 'issued', 'acknowledged', 'expired'];

    public const TYPES = ['performance', 'attendance', 'conduct', 'policy_violation', 'other'];

    public const SEVERITIES = ['verbal', 'written', 'final'];

    /** Roles whose users can issue warnings (the demo's "managers" list). */
    public const ISSUER_ROLES = ['company', 'hr'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'warning_by' => 'integer',
            'warning_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'acknowledgment_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'has_improvement_plan' => 'boolean',
            'improvement_plan_start_date' => 'date:Y-m-d',
            'improvement_plan_end_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warning_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
