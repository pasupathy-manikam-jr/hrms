<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scheduled maintenance job for an asset (the demo's "Maintenance Schedule").
 *
 * @property int $id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 */
#[Fillable(['asset_id', 'maintenance_type', 'start_date', 'end_date', 'cost', 'details', 'supplier'])]
class AssetMaintenance extends Model
{
    /** @var list<string> */
    protected $appends = ['status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'cost' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Upcoming, in progress or completed, from today's date against the schedule.
     *
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->start_date->isAfter(today()) => 'upcoming',
            $this->end_date->isBefore(today()) => 'completed',
            default => 'in_progress',
        });
    }
}
