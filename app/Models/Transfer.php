<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonInterface;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $to_branch_id
 * @property int $to_department_id
 * @property int $to_designation_id
 * @property CarbonInterface $transfer_date
 * @property CarbonInterface $effective_date
 * @property string $status
 * @property string|null $file_path
 * @property string|null $file_name
 */
#[Fillable([
    'employee_id', 'from_branch_id', 'to_branch_id', 'from_department_id', 'to_department_id',
    'from_designation_id', 'to_designation_id', 'transfer_date', 'effective_date', 'reason', 'notes',
    'status', 'approved_by', 'approved_at', 'created_by',
    'file_path', 'file_name', 'file_type', 'file_size',
])]
class Transfer extends Model
{
    use BelongsToEmployee;

    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'transfers';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    public const MODULE = 'employee-transfers';

    public const STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'from_branch_id' => 'integer',
            'to_branch_id' => 'integer',
            'from_department_id' => 'integer',
            'to_department_id' => 'integer',
            'from_designation_id' => 'integer',
            'to_designation_id' => 'integer',
            'transfer_date' => 'date:Y-m-d',
            'effective_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Approve and move the employee to the new branch, department and designation, atomically.
     */
    public function approve(User $approver): void
    {
        DB::transaction(function () use ($approver) {
            $this->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
            $this->employee()->update([
                'branch_id' => $this->to_branch_id,
                'department_id' => $this->to_department_id,
                'designation_id' => $this->to_designation_id,
            ]);
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function fromDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'from_designation_id');
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function toDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'to_designation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
