<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\ReviewCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $frequency
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'frequency', 'description', 'start_date', 'end_date', 'status', 'created_by'])]
class ReviewCycle extends Model
{
    /** @use HasFactory<ReviewCycleFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'review-cycles';

    public const STATUSES = ['active', 'inactive'];

    public const FREQUENCIES = ['Monthly', 'Quarterly', 'Semi-Annual', 'Annual'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'created_by' => 'integer'];
    }
}
