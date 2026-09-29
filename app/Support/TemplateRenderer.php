<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Fills document/contract templates. Placeholders are written {name} or, as in the demo, {{name}}.
 * Templates are admin-written plain text or simple HTML; every substituted value is HTML-escaped.
 * Unknown placeholders are left as-is so the author can see what is still missing.
 */
class TemplateRenderer
{
    private const PLACEHOLDER = '/\{\{?\s*([a-z0-9_]+)\s*\}?\}/i';

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public static function render(string $template, array $values): string
    {
        return (string) preg_replace_callback(self::PLACEHOLDER, function (array $match) use ($values) {
            $key = strtolower($match[1]);

            return array_key_exists($key, $values) ? e((string) $values[$key]) : $match[0];
        }, $template);
    }

    /**
     * The placeholder names used in a template, in order of first use.
     *
     * @return list<string>
     */
    public static function placeholders(string $template): array
    {
        preg_match_all(self::PLACEHOLDER, $template, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * Employees to preview a template for.
     *
     * @return Collection<int, array{id: int, name: string, employee_id: string}>
     */
    public static function employeeOptions(): Collection
    {
        return Employee::query()->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
            ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id]);
    }

    /**
     * Placeholder values for an employee (plus company and today's date).
     *
     * @return array<string, string|null>
     */
    public static function employeeValues(Employee $employee): array
    {
        $employee->loadMissing('user:id,name,email', 'branch:id,name', 'department:id,name', 'designation:id,name');
        $today = now()->format((string) Setting::get('dateFormat'));

        return [
            'employee_name' => $employee->user->name,
            'employee_email' => $employee->user->email,
            'employee_id' => $employee->employee_id,
            'designation' => $employee->designation?->name,
            'job_title' => $employee->designation?->name,
            'department' => $employee->department?->name,
            'branch' => $employee->branch?->name,
            'joining_date' => $employee->date_of_joining?->format((string) Setting::get('dateFormat')),
            'employment_type' => $employee->employment_type,
            'company_name' => (string) Setting::get('titleText'),
            'date' => $today,
            'issue_date' => $today,
            'contract_date' => $today,
        ];
    }
}
