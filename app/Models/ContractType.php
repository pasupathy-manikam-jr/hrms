<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_renewable
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'description', 'default_duration_months', 'probation_period_months', 'notice_period_days', 'is_renewable', 'status', 'created_by'])]
class ContractType extends Model
{
    use HasCreator;

    public const MODULE = 'contract-types';

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_duration_months' => 'integer',
            'probation_period_months' => 'integer',
            'notice_period_days' => 'integer',
            'is_renewable' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return HasMany<EmployeeContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }
}
