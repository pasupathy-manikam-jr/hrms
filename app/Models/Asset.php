<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The stored `status` column holds only the base state (available, under_maintenance, disposed).
 * Reading `$asset->status` returns "assigned" whenever an assignment is open, so the status
 * can never disagree with the assignment history.
 *
 * @property int $id
 * @property string $name
 * @property int|null $asset_type_id
 * @property Carbon|null $purchase_date
 * @property string $purchase_cost
 * @property string $salvage_value
 * @property int $useful_life_years
 * @property string $status
 * @property-read AssetAssignment|null $currentAssignment
 */
#[Fillable([
    'name', 'asset_type_id', 'serial_number', 'asset_code', 'purchase_date', 'purchase_cost',
    'salvage_value', 'useful_life_years', 'status', 'condition', 'location', 'description',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    /** Statuses a user may set; "assigned" is derived. */
    public const BASE_STATUSES = ['available', 'under_maintenance', 'disposed'];

    public const STATUSES = ['available', 'assigned', 'under_maintenance', 'disposed'];

    public const CONDITIONS = ['new', 'good', 'fair', 'poor'];

    /** Dashboard labels and colours, as in the demo. */
    private const STATUS_CHART = [
        'available' => ['Available', '#10B981'],
        'assigned' => ['Assigned', '#3B82F6'],
        'under_maintenance' => ['Maintenance', '#F59E0B'],
        'disposed' => ['Disposed', '#EF4444'],
    ];

    /** @var list<string> */
    protected $appends = ['current_value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_type_id' => 'integer',
            'useful_life_years' => 'integer',
            'purchase_date' => 'date:Y-m-d',
            'purchase_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AssetType, $this>
     */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /**
     * @return HasMany<AssetAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * @return HasOne<AssetAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (?string $value) => $this->currentAssignment !== null ? 'assigned' : (string) $value);
    }

    /**
     * @return Attribute<float, never>
     */
    protected function currentValue(): Attribute
    {
        return Attribute::get(fn () => $this->bookValue());
    }

    /**
     * Straight-line book value: (cost - salvage) spread evenly over the useful life,
     * charged per full month since purchase, never below the salvage value.
     */
    public function bookValue(?CarbonInterface $on = null): float
    {
        $cost = (float) $this->purchase_cost;
        $depreciable = $cost - (float) $this->salvage_value;

        if ($this->purchase_date === null || $this->useful_life_years <= 0 || $depreciable <= 0) {
            return round($cost, 2);
        }

        $months = max(0, (int) floor($this->purchase_date->diffInMonths($on ?? now(), false)));

        return round($cost - min($depreciable, $depreciable * $months / ($this->useful_life_years * 12)), 2);
    }

    /**
     * Everyone's assets for manage-any-assets; otherwise only those currently assigned to the user.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-assets')) {
            $query->whereHas('currentAssignment.employee', fn (Builder $q) => $q->where('user_id', $user->id));
        }
    }

    /**
     * Filter by the derived status.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWhereStatus(Builder $query, string $status): void
    {
        $status === 'assigned'
            ? $query->whereHas('currentAssignment')
            : $query->where('status', $status)->whereDoesntHave('currentAssignment');
    }

    /**
     * Asset counts per derived status.
     *
     * @param  Builder<self>|null  $query
     * @return array<string, int>
     */
    public static function statusCounts(?Builder $query = null): array
    {
        $query ??= static::query();
        $base = (clone $query)->whereDoesntHave('currentAssignment')->toBase()->reorder()
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $assigned = (clone $query)->whereHas('currentAssignment')->count();

        return collect(self::STATUSES)->mapWithKeys(fn ($status) => [
            $status => $status === 'assigned' ? $assigned : (int) ($base[$status] ?? 0),
        ])->all();
    }

    /**
     * Chart rows for the dashboard: [['name' => 'Available', 'value' => 3, 'color' => '#10B981'], ...].
     *
     * @param  Builder<self>|null  $query
     * @return list<array{name: string, value: int, color: string}>
     */
    public static function statusStats(?Builder $query = null): array
    {
        $counts = static::statusCounts($query);

        return array_map(
            fn (string $status, array $chart) => ['name' => $chart[0], 'value' => $counts[$status], 'color' => $chart[1]],
            array_keys(self::STATUS_CHART),
            self::STATUS_CHART,
        );
    }

    /**
     * Check the asset out to an employee. Only an available, unassigned asset can be assigned.
     */
    public function assignTo(Employee $employee, string $assignedAt, ?string $notes = null): AssetAssignment
    {
        return DB::transaction(function () use ($employee, $assignedAt, $notes) {
            $asset = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($asset->currentAssignment !== null) {
                throw ValidationException::withMessages(['employee_id' => __('This asset is already assigned.')]);
            }

            if ($asset->getAttributes()['status'] !== 'available') {
                throw ValidationException::withMessages(['employee_id' => __('Only available assets can be assigned.')]);
            }

            return $asset->assignments()->create([
                'employee_id' => $employee->id,
                'assigned_at' => $assignedAt,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Close the open assignment.
     */
    public function returnAsset(string $returnedAt, ?string $notes = null): void
    {
        DB::transaction(function () use ($returnedAt, $notes) {
            $assignment = $this->currentAssignment()->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['returned_at' => __('This asset is not assigned.')]);

            if ($assignment->assigned_at->greaterThan(Carbon::parse($returnedAt))) {
                throw ValidationException::withMessages(['returned_at' => __('The return date cannot be before the assignment date.')]);
            }

            $assignment->update([
                'returned_at' => $returnedAt,
                'notes' => trim(implode("\n", array_filter([$assignment->notes, $notes]))) ?: null,
            ]);
        });

        $this->unsetRelation('currentAssignment');
    }
}
