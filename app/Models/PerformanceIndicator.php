<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\PerformanceIndicatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string|null $measurement_unit
 * @property string|null $target_value
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['category_id', 'name', 'description', 'measurement_unit', 'target_value', 'status', 'created_by'])]
class PerformanceIndicator extends Model
{
    /** @use HasFactory<PerformanceIndicatorFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'performance-indicators';

    public const STATUSES = ['active', 'inactive'];

    public const UNITS = ['Rating', 'Percentage', 'Count', 'Hours'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['category_id' => 'integer', 'created_by' => 'integer'];
    }

    /**
     * @return BelongsTo<PerformanceIndicatorCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicatorCategory::class, 'category_id');
    }
}
