<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonInterface;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $previous_designation_id
 * @property int $designation_id
 * @property CarbonInterface $promotion_date
 * @property CarbonInterface $effective_date
 * @property string $status
 * @property string|null $file_path
 * @property string|null $file_name
 */
#[Fillable([
    'employee_id', 'previous_designation_id', 'designation_id', 'promotion_date', 'effective_date',
    'salary_adjustment', 'reason', 'status', 'approved_by', 'approved_at', 'created_by',
    'file_path', 'file_name', 'file_type', 'file_size',
])]
class Promotion extends Model
{
    use BelongsToEmployee;

    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'promotions';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    public const MODULE = 'promotions';

    public const STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'previous_designation_id' => 'integer',
            'designation_id' => 'integer',
            'promotion_date' => 'date:Y-m-d',
            'effective_date' => 'date:Y-m-d',
            'salary_adjustment' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Approve and move the employee to the new designation, atomically.
     */
    public function approve(User $approver): void
    {
        DB::transaction(function () use ($approver) {
            $this->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
            $this->employee()->update(['designation_id' => $this->designation_id]);
        });
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function previousDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'previous_designation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
