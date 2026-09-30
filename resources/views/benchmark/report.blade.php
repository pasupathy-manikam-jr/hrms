<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Salary & Benefits Benchmark') }} — {{ $cycle->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; page-break-after: avoid; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 3px 5px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        td.num { text-align: right; white-space: nowrap; }
        .cover { page-break-after: always; }
    </style>
</head>
<body>
    <div class="cover">
        <p class="muted">{{ $brand }}</p>
        <h1>{{ __('Salary & Benefits Benchmark') }} {{ $cycle->name }}</h1>
        <p>{{ __('Generated on :date', ['date' => now()->format('d/m/Y')]) }}</p>

        <h2>{{ __('Methodology') }}</h2>
        <p>
            {{ __(':companies participating companies, :roles job roles and :incumbents employees are included.', [
                'companies' => $report['overview']['companies'],
                'roles' => $report['overview']['roles'],
                'incumbents' => number_format($report['overview']['incumbents']),
            ]) }}
            @if ($minCompanies > 1)
                {{ __('A figure is only shown when at least :min companies contribute to it; otherwise it reads "Insufficient data".', ['min' => $minCompanies]) }}
            @else
                {{ __('Confidentiality rule off: figures based on a single company are shown. For internal use only.') }}
            @endif
            {{ __('Salaries are monthly base pay in RM; percentiles are :weighting.', ['weighting' => ($filters['weighting'] ?? 'company') === 'incumbent' ? __('weighted by headcount') : __('company-weighted (each company counts once)')]) }}
        </p>

        <h2>{{ __('Filters') }}</h2>
        @forelse ($filters as $name => $value)
            <div>{{ __(\Illuminate\Support\Str::headline($name)) }}: <strong>{{ $value }}</strong></div>
        @empty
            <div>{{ __('All participants') }}</div>
        @endforelse
    </div>

    @foreach ($tables as $title => $rows)
        <h2>{{ $title }}</h2>
        <table>
            <thead>
                <tr>@foreach ($rows[0] as $heading)<th>{{ $heading }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach (array_slice($rows, 1) as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td @class(['num' => is_int($cell) || is_float($cell)])>{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
