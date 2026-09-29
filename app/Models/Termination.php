<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonInterface;
use Database\Factories\TerminationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $termination_type
 * @property CarbonInterface $notice_date
 * @property CarbonInterface $termination_date
 * @property string $status
 * @property Employee $employee
 * @property string|null $file_path
 * @property string|null $file_name
 */
#[Fillable([
    'employee_id', 'termination_type', 'notice_date', 'termination_date', 'notice_period', 'reason', 'description',
    'status', 'approved_by', 'approved_at',
    'file_path', 'file_name', 'file_type', 'file_size', 'exit_interview_conducted', 'exit_interview_date', 'exit_feedback',
])]
class Termination extends Model
{
    use BelongsToEmployee;

    /** @use HasFactory<TerminationFactory> */
    use HasFactory;

    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'terminations';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    public const MODULE = 'terminations';

    /** "planned" awaits approval; "in progress" is approved but not yet due. */
    public const STATUSES = ['planned', 'in progress', 'completed'];

    public const TYPES = ['retirement', 'resignation', 'layoff', 'misconduct', 'performance', 'end of contract', 'other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exit_interview_conducted' => 'boolean',
            'exit_interview_date' => 'date:Y-m-d',
            'employee_id' => 'integer',
            'notice_date' => 'date:Y-m-d',
            'termination_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Once approved and due (or explicitly completed), mark it completed and the employee terminated, atomically.
     */
    public function completeIfDue(): void
    {
        $due = $this->status === 'completed' || ($this->status === 'in progress' && $this->termination_date->lte(today()));

        if (! $due) {
            return;
        }

        DB::transaction(function () {
            $this->update(['status' => 'completed']);
            $this->employee()->update(['employee_status' => 'terminated']);
        });
    }

    /**
     * Complete every approved termination whose date has arrived (run daily by the scheduler).
     */
    public static function completeDue(): int
    {
        $due = static::query()->where('status', 'in progress')->whereDate('termination_date', '<=', today())->get();
        $due->each->completeIfDue();

        return $due->count();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
