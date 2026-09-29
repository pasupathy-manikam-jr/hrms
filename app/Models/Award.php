<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use Carbon\CarbonInterface;
use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $award_type_id
 * @property CarbonInterface $award_date
 */
#[Fillable(['employee_id', 'award_type_id', 'award_date', 'gift', 'monetary_value', 'description', 'created_by'])]
class Award extends Model
{
    use BelongsToEmployee;

    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    public const MODULE = 'awards';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'award_type_id' => 'integer',
            'award_date' => 'date:Y-m-d',
            'monetary_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AwardType, $this>
     */
    public function awardType(): BelongsTo
    {
        return $this->belongsTo(AwardType::class);
    }
}
