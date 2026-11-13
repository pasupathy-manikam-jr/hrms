@php
    $money = fn ($amount) => $currency.' '.number_format((float) $amount, 2);
    $run = $payslip->payrollRun;
    $employee = $payslip->employee;
    $earnings = [['name' => __('Basic Salary'), 'amount' => $payslip->basic_salary], ...$payslip->earnings];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Payslip') }} {{ $payslip->payslip_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        .facts td { padding: 3px 0; width: 50%; vertical-align: top; }
        .cols > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 6px; }
        h2 { font-size: 11px; background: #e5e7eb; padding: 4px 6px; margin: 14px 0 4px; }
        .lines td { padding: 3px 6px; border-bottom: 1px solid #e5e7eb; }
        .amt { text-align: right; white-space: nowrap; }
        .total td { font-weight: bold; border-top: 1px solid #111; border-bottom: 0; }
        .net { margin-top: 16px; background: #eef2ff; padding: 8px 10px; font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $employer['titleText'] }}</h1>
    @if ($employer['employerRegistrationNumber'])
        <div class="muted">{{ $employer['employerRegistrationNumber'] }}</div>
    @endif

    <h2>{{ __('Payslip') }} {{ $payslip->payslip_number }}</h2>
    <table class="facts">
        <tr>
            <td><span class="muted">{{ __('Employee') }}:</span> <strong>{{ $employee->user->name }}</strong> ({{ $employee->employee_id }})</td>
            <td><span class="muted">{{ __('Payroll Run') }}:</span> {{ $run->title }}</td>
        </tr>
        <tr>
            <td><span class="muted">{{ __('Designation') }}:</span> {{ $employee->designation?->name ?? '-' }}</td>
            <td><span class="muted">{{ __('Pay Period') }}:</span> {{ $run->pay_period_start->toDateString() }} – {{ $run->pay_period_end->toDateString() }}</td>
        </tr>
        <tr>
            <td></td>
            <td><span class="muted">{{ __('Pay Date') }}:</span> {{ $run->pay_date->toDateString() }}</td>
        </tr>
    </table>

    <table class="cols">
        <tr>
            @foreach ([[__('Earnings'), $earnings, __('Gross Pay'), $payslip->gross_pay], [__('Deductions'), $payslip->deductions, __('Total Deductions'), $payslip->total_deductions]] as [$title, $lines, $totalLabel, $total])
                <td>
                    <h2>{{ $title }}</h2>
                    <table class="lines">
                        @foreach ($lines as $line)
                            <tr><td>{{ $line['name'] }}</td><td class="amt">{{ $money($line['amount']) }}</td></tr>
                        @endforeach
                        <tr class="total"><td>{{ $totalLabel }}</td><td class="amt">{{ $money($total) }}</td></tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="net">{{ __('Net Pay') }}: {{ $money($payslip->net_pay) }}</div>
</body>
</html>
