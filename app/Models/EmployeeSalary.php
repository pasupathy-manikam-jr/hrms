<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\EmployeeSalaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An employee's basic salary plus the salary components assigned to them.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $basic_salary
 * @property bool $is_active
 * @property-read Employee $employee
 * @property-read Collection<int, SalaryComponent> $components
 */
#[Fillable(['employee_id', 'basic_salary', 'is_active', 'notes'])]
class EmployeeSalary extends Model
{
    /** @use HasFactory<EmployeeSalaryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'basic_salary' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Everyone for manage-any-employee-salaries; only the user's own for manage-own-employee-salaries.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-employee-salaries')) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->can('manage-own-employee-salaries') ? $user->id : 0));
        }
    }

    /**
     * The pay for one period: gross = basic + earnings, net = gross - deductions.
     * Percentage components are taken of the basic salary; every line is rounded to the cent
     * before summing, so the lines always add up to the totals. Inactive components are skipped.
     *
     * @return array{basic_salary: string, earnings: list<array{name: string, amount: string}>, deductions: list<array{name: string, amount: string}>, total_earnings: string, gross_pay: string, total_deductions: string, net_pay: string}
     */
    public function calculate(): array
    {
        $basic = Money::toCents($this->basic_salary);
        $lines = ['earning' => [], 'deduction' => []];
        $sums = ['earning' => 0, 'deduction' => 0];

        foreach ($this->components->where('status', 'active')->sortBy('id') as $component) {
            $amount = $component->amountFor($basic);
            $lines[$component->type][] = ['name' => $component->name, 'amount' => Money::format($amount)];
            $sums[$component->type] += $amount;
        }

        $gross = $basic + $sums['earning'];

        return [
            'basic_salary' => Money::format($basic),
            'earnings' => $lines['earning'],
            'deductions' => $lines['deduction'],
            'total_earnings' => Money::format($sums['earning']),
            'gross_pay' => Money::format($gross),
            'total_deductions' => Money::format($sums['deduction']),
            'net_pay' => Money::format($gross - $sums['deduction']),
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsToMany<SalaryComponent, $this>
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(SalaryComponent::class, 'employee_salary_component');
    }
}
