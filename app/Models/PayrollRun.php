<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * @property int $id
 * @property string $title
 * @property Carbon $pay_period_start
 * @property Carbon $pay_period_end
 * @property Carbon $pay_date
 * @property string $total_gross_pay
 * @property string $total_deductions
 * @property string $total_net_pay
 * @property int $employee_count
 * @property string $status
 */
#[Fillable(['title', 'payroll_frequency', 'pay_period_start', 'pay_period_end', 'pay_date', 'notes'])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    public const STATUSES = ['draft', 'processing', 'completed', 'cancelled'];

    public const FREQUENCIES = ['weekly', 'biweekly', 'monthly'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pay_period_start' => 'date:Y-m-d',
            'pay_period_end' => 'date:Y-m-d',
            'pay_date' => 'date:Y-m-d',
            'total_gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net_pay' => 'decimal:2',
            'employee_count' => 'integer',
        ];
    }

    /**
     * Completed (and cancelled) runs can no longer be processed, edited or deleted.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    /**
     * (Re)generate one payslip per active employee (joined by the period end) with an active salary, and store the totals.
     * Idempotent: the run's previous payslips are replaced, never duplicated.
     */
    public function process(): void
    {
        throw_if($this->isLocked(), LogicException::class, 'A locked payroll run cannot be processed.');

        DB::transaction(function () {
            $this->payslips()->delete();

            $salaries = EmployeeSalary::query()
                ->where('is_active', true)
                ->whereHas('employee', fn ($q) => $q->where('employee_status', 'active')
                    ->where(fn ($q) => $q->whereNull('date_of_joining')->orWhere('date_of_joining', '<=', $this->pay_period_end->toDateString())))
                ->with('components')
                ->orderBy('employee_id')
                ->get();

            $totals = ['gross_pay' => 0, 'total_deductions' => 0, 'net_pay' => 0];

            foreach ($salaries as $salary) {
                $pay = $salary->calculate();

                $this->payslips()->create([
                    ...$pay,
                    'employee_id' => $salary->employee_id,
                    'payslip_number' => sprintf('PS-%s-%05d-%04d', $this->pay_period_start->format('Ym'), $this->id, $salary->employee_id),
                ]);

                foreach ($totals as $key => $sum) {
                    $totals[$key] = $sum + Money::toCents($pay[$key]);
                }
            }

            $this->forceFill([
                'total_gross_pay' => Money::format($totals['gross_pay']),
                'total_deductions' => Money::format($totals['total_deductions']),
                'total_net_pay' => Money::format($totals['net_pay']),
                'employee_count' => $salaries->count(),
            ])->save();
        });
    }

    /**
     * Net pay per month (by pay period start) of the year's completed runs, for the dashboard's Payroll Trend.
     *
     * @return list<array{month: string, netPay: float}>
     */
    public static function monthlyNetPay(int $year): array
    {
        $cents = array_fill(1, 12, 0);

        static::query()
            ->where('status', 'completed')
            ->whereBetween('pay_period_start', ["{$year}-01-01", "{$year}-12-31"])
            ->get(['pay_period_start', 'total_net_pay'])
            ->each(function (self $run) use (&$cents) {
                $cents[$run->pay_period_start->month] += Money::toCents($run->total_net_pay);
            });

        return array_map(fn (int $month) => [
            'month' => Carbon::create($year, $month, 1)->format('M'),
            'netPay' => round($cents[$month] / 100, 2),
        ], range(1, 12));
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
