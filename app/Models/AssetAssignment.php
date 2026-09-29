<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One checkout of an asset to an employee; open while returned_at is null.
 *
 * @property int $id
 * @property int $asset_id
 * @property int $employee_id
 * @property Carbon $assigned_at
 * @property Carbon|null $returned_at
 */
#[Fillable(['asset_id', 'employee_id', 'assigned_at', 'returned_at', 'notes'])]
class AssetAssignment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_id' => 'integer',
            'employee_id' => 'integer',
            'assigned_at' => 'date:Y-m-d',
            'returned_at' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
