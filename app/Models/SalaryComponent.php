<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\SalaryComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $calculation_type
 * @property string $default_amount
 * @property string|null $percentage_of_basic
 * @property string $status
 */
#[Fillable(['name', 'description', 'type', 'calculation_type', 'default_amount', 'percentage_of_basic', 'is_taxable', 'is_mandatory', 'status'])]
class SalaryComponent extends Model
{
    /** @use HasFactory<SalaryComponentFactory> */
    use HasFactory;

    public const TYPES = ['earning', 'deduction'];

    public const CALCULATION_TYPES = ['fixed', 'percentage'];

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'percentage_of_basic' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_mandatory' => 'boolean',
        ];
    }

    /**
     * This component's amount in cents for a basic salary in cents.
     */
    public function amountFor(int $basicCents): int
    {
        return $this->calculation_type === 'percentage'
            ? Money::percentOf($basicCents, $this->percentage_of_basic)
            : Money::toCents($this->default_amount);
    }
}
