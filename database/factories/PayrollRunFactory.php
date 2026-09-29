<?php

namespace Database\Factories;

use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'title' => $start->format('F Y').' Payroll',
            'payroll_frequency' => 'monthly',
            'pay_period_start' => $start->toDateString(),
            'pay_period_end' => $start->copy()->endOfMonth()->toDateString(),
            'pay_date' => $start->copy()->addMonth()->day(5)->toDateString(),
            'notes' => null,
        ];
    }
}
