<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonInterface;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 * @property string $status
 */
#[Fillable([
    'employee_id', 'purpose', 'destination', 'start_date', 'end_date', 'description', 'expected_outcomes',
    'status', 'advance_amount', 'advance_status', 'total_expenses', 'reimbursement_status', 'trip_report',
    'approved_by', 'approved_at', 'file_path', 'file_name', 'file_type', 'file_size',
])]
class Trip extends Model
{
    use BelongsToEmployee;

    /** @use HasFactory<TripFactory> */
    use HasFactory;

    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'trips';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    public const MODULE = 'trips';

    public const STATUSES = ['planned', 'ongoing', 'completed', 'cancelled'];

    /** The traveller asks ("requested"); approvers move it on. */
    public const ADVANCE_STATUSES = ['requested', 'approved', 'paid', 'reconciled'];

    /** The traveller submits ("pending"); approvers decide and pay. */
    public const REIMBURSEMENT_STATUSES = ['pending', 'approved', 'rejected', 'paid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'advance_amount' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
