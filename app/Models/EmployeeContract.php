<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stored status is one of STORED_STATUSES; an active contract past its end_date also reads as "expired".
 *
 * @property int $id
 * @property string $contract_number
 * @property int $employee_id
 * @property int|null $contract_type_id
 * @property CarbonInterface $start_date
 * @property CarbonInterface|null $end_date
 * @property string $basic_salary
 * @property string $status
 */
#[Fillable([
    'contract_number', 'employee_id', 'contract_type_id', 'contract_template_id', 'start_date', 'end_date',
    'basic_salary', 'terms_conditions', 'status', 'created_by',
])]
class EmployeeContract extends Model
{
    use BelongsToEmployee;

    public const MODULE = 'employee-contracts';

    /** Statuses a user can set (the demo's "Update Contract Status" choices). */
    public const STORED_STATUSES = ['draft', 'pending_approval', 'active', 'expired', 'terminated', 'renewed'];

    public const STATUSES = self::STORED_STATUSES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'contract_type_id' => 'integer',
            'contract_template_id' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'basic_salary' => 'decimal:2',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (string $value) => $value === 'active' && $this->end_date?->isBefore(today()) ? 'expired' : $value);
    }

    /**
     * Filter by effective status (expired = active and past end_date).
     *
     * @param  Builder<self>  $query
     */
    public function scopeWhereStatus(Builder $query, string $status): void
    {
        match ($status) {
            'expired' => $query->where(fn ($q) => $q->where('status', 'expired')
                ->orWhere(fn ($q) => $q->where('status', 'active')->whereDate('end_date', '<', today()))),
            'active' => $query->where('status', 'active')->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today())),
            default => $query->where('status', $status),
        };
    }

    /**
     * The next free number in the demo's EMP-2025-0001 format.
     */
    public static function nextNumber(): string
    {
        $number = (int) static::query()->max('id');

        do {
            $code = 'EMP-'.now()->year.'-'.str_pad((string) ++$number, 4, '0', STR_PAD_LEFT);
        } while (static::query()->where('contract_number', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<ContractType, $this>
     */
    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    /**
     * @return BelongsTo<ContractTemplate, $this>
     */
    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class);
    }
}
