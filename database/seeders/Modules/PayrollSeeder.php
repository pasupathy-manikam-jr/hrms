<?php

namespace Database\Seeders\Modules;

use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class PayrollSeeder extends Seeder
{
    /**
     * Seed the demo's salary components and employee salaries, then a completed monthly
     * payroll run (with payslips) for every month of the current year up to this one.
     */
    public function run(): void
    {
        $demo = File::json(database_path('demo/payroll.json'), JSON_THROW_ON_ERROR);

        foreach ($demo['salary_components'] as $component) {
            SalaryComponent::query()->firstOrCreate(['name' => $component['name']], $component);
        }

        $components = SalaryComponent::query()->pluck('id', 'name');

        foreach ($demo['employee_salaries'] as $row) {
            $employeeId = User::query()->where('email', $row['email'])->first()?->employee?->id;

            if (! $employeeId) {
                continue;
            }

            $salary = EmployeeSalary::query()->firstOrCreate(['employee_id' => $employeeId], Arr::only($row, ['basic_salary', 'is_active', 'notes']));

            if ($salary->wasRecentlyCreated) {
                $salary->components()->sync($components->only($row['components'])->values());
            }
        }

        $year = now()->year;

        foreach (range(1, now()->month) as $month) {
            $start = now()->setDate($year, $month, 1)->startOfDay();

            $run = PayrollRun::query()->firstOrCreate(['title' => $start->format('F Y').' Payroll'], [
                'payroll_frequency' => 'monthly',
                'pay_period_start' => $start->toDateString(),
                'pay_period_end' => $start->copy()->endOfMonth()->toDateString(),
                'pay_date' => $start->copy()->addMonthNoOverflow()->day(5)->toDateString(),
                'notes' => 'Monthly payroll for '.$start->format('F Y'),
            ]);

            if (! $run->isLocked()) {
                $run->process();
                $run->forceFill(['status' => 'completed'])->save();
            }
        }
    }
}
