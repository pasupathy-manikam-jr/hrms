<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonInterface;
use Database\Factories\ResignationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property CarbonInterface $resignation_date
 * @property CarbonInterface $last_working_day
 * @property string $status
 */
#[Fillable([
    'employee_id', 'resignation_date', 'last_working_day', 'notice_period', 'reason', 'description',
    'status', 'approved_by', 'approved_at', 'file_path', 'file_name', 'file_type', 'file_size',
])]
class Resignation extends Model
{
    use BelongsToEmployee;

    /** @use HasFactory<ResignationFactory> */
    use HasFactory;

    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'resignations';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    public const MODULE = 'resignations';

    public const STATUSES = ['pending', 'approved', 'rejected', 'completed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'resignation_date' => 'date:Y-m-d',
            'last_working_day' => 'date:Y-m-d',
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
