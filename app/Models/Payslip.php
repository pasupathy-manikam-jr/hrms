<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's pay for a payroll run, with its earning and deduction lines.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $gross_pay
 * @property string $total_deductions
 * @property string $net_pay
 * @property list<array{name: string, amount: string}> $earnings
 * @property list<array{name: string, amount: string}> $deductions
 * @property array<string, int>|null $statutory employee and employer EPF/SOCSO/EIS/PCB amounts in sen (see Statutory\Contributions)
 */
#[Fillable([
    'payroll_run_id', 'employee_id', 'payslip_number', 'basic_salary', 'total_earnings', 'gross_pay',
    'total_deductions', 'net_pay', 'earnings', 'deductions', 'statutory', 'status',
])]
class Payslip extends Model
{
    public const STATUSES = ['generated', 'sent', 'downloaded'];

    /** The demo's status tabs. */
    public const TAB_STATUSES = ['generated', 'downloaded'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payroll_run_id' => 'integer',
            'employee_id' => 'integer',
            'basic_salary' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'earnings' => 'array',
            'deductions' => 'array',
            'statutory' => 'array',
        ];
    }

    /**
     * Everything for manage-any-payslips; otherwise only the user's own payslips from completed runs.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-payslips')) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->can('manage-own-payslips') ? $user->id : 0))
                ->whereHas('payrollRun', fn ($q) => $q->where('status', 'completed'));
        }
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
