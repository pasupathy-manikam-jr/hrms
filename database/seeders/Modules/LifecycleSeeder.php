<?php

namespace Database\Seeders\Modules;

use App\Models\Award;
use App\Models\AwardType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Promotion;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warning;
use Database\Seeders\Concerns\AttachesSampleDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The demo's awards, promotions, transfers and warnings. Employees are matched by email, then name.
 * Employees already hold their post-promotion/transfer placement, so nothing is re-applied here.
 */
class LifecycleSeeder extends Seeder
{
    use AttachesSampleDocuments;

    public function run(): void
    {
        $this->seedAwards();
        $this->seedPromotions();
        $this->seedTransfers();
        $this->seedWarnings();
    }

    private function seedAwards(): void
    {
        foreach ($this->rows('awards') as [$employeeId, $row]) {
            $awardTypeId = AwardType::query()->where('name', $row['award_type'])->value('id');

            if ($awardTypeId) {
                Award::query()->firstOrCreate(
                    ['employee_id' => $employeeId, 'award_type_id' => $awardTypeId, 'award_date' => $row['award_date']],
                    Arr::only($row, ['gift', 'monetary_value', 'description']),
                );
            }
        }
    }

    private function seedPromotions(): void
    {
        foreach ($this->rows('promotions') as [$employeeId, $row]) {
            $designationId = $this->designationId($row['designation']);

            if ($designationId) {
                Promotion::query()->firstOrCreate(
                    ['employee_id' => $employeeId, 'designation_id' => $designationId, 'promotion_date' => $row['promotion_date']],
                    [
                        ...Arr::only($row, ['effective_date', 'salary_adjustment', 'reason', 'status']),
                        'previous_designation_id' => Designation::query()->where('name', $row['previous_designation'])->value('id'),
                        'approved_at' => $row['status'] === 'pending' ? null : $row['effective_date'],
                    ],
                );
            }
        }
    }

    private function seedTransfers(): void
    {
        foreach ($this->rows('transfers') as [$employeeId, $row]) {
            $to = $this->placement($row['to']);

            if (in_array(null, $to, true)) {
                continue;
            }

            $from = $this->placement($row['from']);

            Transfer::query()->firstOrCreate(
                ['employee_id' => $employeeId, 'transfer_date' => $row['transfer_date'], 'to_designation_id' => $to['designation']],
                [
                    ...Arr::only($row, ['effective_date', 'reason', 'notes', 'status', 'approved_at']),
                    'from_branch_id' => $from['branch'],
                    'from_department_id' => $from['department'],
                    'from_designation_id' => $from['designation'],
                    'to_branch_id' => $to['branch'],
                    'to_department_id' => $to['department'],
                    'approved_by' => $row['approved_by'] ? User::query()->where('email', $row['approved_by'])->value('id') : null,
                ],
            );
        }
    }

    private function seedWarnings(): void
    {
        $fallbackIssuer = User::query()->where('email', 'hr@example.com')->value('id');

        foreach ($this->rows('warnings') as [$employeeId, $row]) {
            Warning::query()->firstOrCreate(
                ['employee_id' => $employeeId, 'subject' => $row['subject'], 'warning_date' => $row['warning_date']],
                [
                    ...Arr::only($row, [
                        'warning_type', 'severity', 'expiry_date', 'description', 'status', 'acknowledgment_date', 'employee_response',
                        'has_improvement_plan', 'improvement_plan_goals', 'improvement_plan_start_date', 'improvement_plan_end_date',
                    ]),
                    'warning_by' => User::query()->where('email', $row['warning_by'])->value('id') ?? $fallbackIssuer,
                ],
            );
        }

        // The demo attaches a transfer order to each transfer.
        Transfer::query()->whereNull('file_path')->with('employee.user:id,name', 'toBranch:id,name', 'toDepartment:id,name')->each(
            fn (Transfer $transfer) => $this->attachSampleDocument($transfer, Transfer::UPLOAD_DIRECTORY, 'transfer-order.pdf', [
                'Transfer Order',
                'Employee: '.($transfer->employee->user->name ?? ''),
                'New branch: '.($transfer->toBranch->name ?? '').'    New department: '.($transfer->toDepartment->name ?? ''),
                'Effective date: '.$transfer->effective_date->toDateString(),
                '',
                (string) $transfer->reason,
            ]),
        );

        // The demo attaches a promotion letter to each promotion.
        Promotion::query()->whereNull('file_path')->with('employee.user:id,name', 'designation:id,name')->each(
            fn (Promotion $promotion) => $this->attachSampleDocument($promotion, Promotion::UPLOAD_DIRECTORY, 'promotion-letter.pdf', [
                'Promotion Letter',
                'Employee: '.($promotion->employee->user->name ?? ''),
                'New designation: '.($promotion->designation->name ?? ''),
                'Effective date: '.$promotion->effective_date->toDateString(),
                '',
                (string) $promotion->reason,
            ]),
        );

        Warning::query()->whereNull('file_path')->each(fn (Warning $warning) => $this->attachWarningLetter($warning));
    }

    /**
     * A generated warning letter as the warning's supporting document (the demo attaches a file to each).
     */
    private function attachWarningLetter(Warning $warning): void
    {
        $this->attachSampleDocument($warning, Warning::UPLOAD_DIRECTORY, Str::slug($warning->subject).'-warning-letter.pdf', [
            'Warning Letter: '.$warning->subject,
            'Employee: '.($warning->employee()->with('user:id,name')->first()?->user->name ?? ''),
            'Date: '.$warning->warning_date->toDateString(),
            'Type: '.str_replace('_', ' ', ucfirst($warning->warning_type)).'    Severity: '.ucfirst($warning->severity),
            '',
            (string) $warning->description,
            '',
            'Please acknowledge this warning with HR.',
        ]);
    }

    /**
     * Demo rows whose employee exists here, as [employee id, row] pairs.
     *
     * @return list<array{int, array<string, mixed>}>
     */
    private function rows(string $file): array
    {
        $rows = [];

        foreach (File::json(database_path("demo/{$file}.json"), JSON_THROW_ON_ERROR) as $row) {
            $userId = User::query()->where('email', $row['employee_email'])->value('id')
                ?? User::query()->where('name', $row['employee_name'])->value('id');
            $employeeId = $userId ? Employee::query()->where('user_id', $userId)->value('id') : null;

            if ($employeeId) {
                $rows[] = [(int) $employeeId, $row];
            }
        }

        return $rows;
    }

    /**
     * @param  array{designation: string, department: string, branch: string}|null  $designation
     */
    private function designationId(?array $designation): ?int
    {
        if (! $designation) {
            return null;
        }

        $exact = Designation::query()->where('name', $designation['designation'])
            ->whereHas('department', fn ($q) => $q->where('name', $designation['department'])
                ->whereHas('branch', fn ($b) => $b->where('name', $designation['branch'])))
            ->value('id');

        return $exact ?? Designation::query()->where('name', $designation['designation'])->value('id');
    }

    /**
     * The demo's placements aren't always a consistent branch > department > designation chain,
     * so each part is resolved on its own.
     *
     * @param  array{branch: ?string, department: ?string, department_branch: ?string, designation: ?array{designation: string, department: string, branch: string}}  $place
     * @return array{branch: ?int, department: ?int, designation: ?int}
     */
    private function placement(array $place): array
    {
        $department = Department::query()->where('name', $place['department'])
            ->orderByRaw('branch_id = ? desc', [Branch::query()->where('name', $place['department_branch'])->value('id') ?? 0])
            ->value('id');

        return [
            'branch' => Branch::query()->where('name', $place['branch'])->value('id'),
            'department' => $department,
            'designation' => $this->designationId($place['designation']),
        ];
    }
}
