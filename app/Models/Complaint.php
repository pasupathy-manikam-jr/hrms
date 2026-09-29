<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonInterface;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * employee_id is the complainant: manage-own-complaints users see only complaints they filed,
 * never ones filed against them.
 *
 * @property int $id
 * @property int $employee_id
 * @property int|null $against_employee_id
 * @property CarbonInterface $complaint_date
 * @property string $status
 */
#[Fillable([
    'employee_id', 'against_employee_id', 'complaint_type', 'subject', 'complaint_date', 'description',
    'status', 'investigation_notes', 'resolution_action', 'resolution_date', 'is_anonymous', 'assigned_to',
    'resolution_deadline', 'follow_up_action', 'follow_up_date', 'feedback', 'file_path', 'file_name', 'file_type', 'file_size',
])]
class Complaint extends Model
{
    use BelongsToEmployee;

    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'complaints';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    public const MODULE = 'complaints';

    public const STATUSES = ['submitted', 'under investigation', 'resolved', 'dismissed'];

    /** Roles whose users can investigate complaints (the demo's "Assign To" list). */
    public const INVESTIGATOR_ROLES = ['company', 'hr', 'manager'];

    public const TYPES = ['Discrimination', 'Harassment', 'Management Issues', 'Workplace Conditions', 'Other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'against_employee_id' => 'integer',
            'complaint_date' => 'date:Y-m-d',
            'resolution_date' => 'date:Y-m-d',
            'resolution_deadline' => 'date:Y-m-d',
            'follow_up_date' => 'date:Y-m-d',
            'is_anonymous' => 'boolean',
            'assigned_to' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function againstEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'against_employee_id');
    }
}
