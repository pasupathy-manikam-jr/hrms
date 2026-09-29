<?php

namespace Database\Seeders\Modules;

use App\Models\Complaint;
use App\Models\Employee;
use App\Models\Resignation;
use App\Models\Termination;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\Concerns\AttachesSampleDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * The demo's resignations, terminations, trips and complaints, matched to our employees by email, then by name.
 * Employee statuses are left as the demo has them; the daily terminations:complete-due run applies due terminations.
 */
class LifecycleExitSeeder extends Seeder
{
    use AttachesSampleDocuments;

    public function run(): void
    {
        $this->seed('resignations', fn (array $row, int $employeeId) => Resignation::query()->firstOrCreate(
            ['employee_id' => $employeeId, 'resignation_date' => $row['resignation_date']],
            [...Arr::only($row, ['last_working_day', 'notice_period', 'reason', 'description', 'status', 'approved_at']), 'approved_by' => $this->userId($row['approved_by'])],
        ));

        $this->seed('terminations', fn (array $row, int $employeeId) => Termination::query()->firstOrCreate(
            ['employee_id' => $employeeId, 'termination_date' => $row['termination_date']],
            [...Arr::only($row, ['termination_type', 'notice_date', 'notice_period', 'reason', 'description', 'status', 'approved_at']), 'approved_by' => $this->userId($row['approved_by'])],
        ));

        $this->seed('trips', fn (array $row, int $employeeId) => Trip::query()->firstOrCreate(
            ['employee_id' => $employeeId, 'start_date' => $row['start_date'], 'destination' => $row['destination']],
            Arr::only($row, ['purpose', 'end_date', 'description', 'expected_outcomes', 'status', 'advance_amount', 'advance_status', 'total_expenses', 'reimbursement_status', 'trip_report']),
        ));

        $this->seed('complaints', fn (array $row, int $employeeId) => Complaint::query()->firstOrCreate(
            ['employee_id' => $employeeId, 'subject' => $row['subject'], 'complaint_date' => $row['complaint_date']],
            [
                ...Arr::only($row, ['complaint_type', 'description', 'status', 'investigation_notes', 'resolution_action', 'resolution_date']),
                'against_employee_id' => $this->employeeId($row['against_email'], $row['against_name']),
            ],
        ));

        // The demo's complaints are assigned to an HR investigator and carry a supporting document.
        $investigator = User::query()->where('email', 'hr@example.com')->value('id');
        Complaint::query()->whereNull('assigned_to')->update(['assigned_to' => $investigator]);
        Complaint::query()->whereNull('file_path')->with('employee.user:id,name')->each(
            fn (Complaint $complaint) => $this->attachSampleDocument($complaint, Complaint::UPLOAD_DIRECTORY, 'complaint-statement.pdf', [
                'Complaint Statement: '.$complaint->subject,
                'Complainant: '.($complaint->employee->user->name ?? ''),
                'Date: '.$complaint->complaint_date->toDateString(),
                'Type: '.$complaint->complaint_type,
                '',
                (string) $complaint->description,
            ]),
        );

        // The demo attaches a document to each trip (here, a generated itinerary).
        Trip::query()->whereNull('file_path')->with('employee.user:id,name')->each(
            fn (Trip $trip) => $this->attachSampleDocument($trip, Trip::UPLOAD_DIRECTORY, 'travel-itinerary.pdf', [
                'Travel Itinerary: '.$trip->destination,
                'Traveller: '.($trip->employee->user->name ?? ''),
                'Dates: '.$trip->start_date->toDateString().' to '.$trip->end_date->toDateString(),
                'Purpose: '.$trip->purpose,
                '',
                (string) $trip->description,
            ]),
        );

        // The demo attaches a termination letter to each termination and records the exit interview once completed.
        Termination::query()->where('status', 'completed')->where('exit_interview_conducted', false)->each(
            fn (Termination $termination) => $termination->update([
                'exit_interview_conducted' => true,
                'exit_interview_date' => $termination->termination_date->toDateString(),
                'exit_feedback' => 'Enjoyed working with the team and appreciated the support during the handover.',
            ]),
        );
        Termination::query()->whereNull('file_path')->with('employee.user:id,name')->each(
            fn (Termination $termination) => $this->attachSampleDocument($termination, Termination::UPLOAD_DIRECTORY, 'termination-letter.pdf', [
                'Termination Letter',
                'Employee: '.($termination->employee->user->name ?? ''),
                'Type: '.ucfirst($termination->termination_type).'    Last day: '.$termination->termination_date->toDateString(),
                'Reason: '.$termination->reason,
                '',
                (string) $termination->description,
            ]),
        );

        // The demo attaches a resignation letter to each resignation.
        Resignation::query()->whereNull('file_path')->with('employee.user:id,name')->each(
            fn (Resignation $resignation) => $this->attachSampleDocument($resignation, Resignation::UPLOAD_DIRECTORY, 'resignation-letter.pdf', [
                'Resignation Letter',
                'From: '.($resignation->employee->user->name ?? ''),
                'Date: '.$resignation->resignation_date->toDateString(),
                'Last working day: '.$resignation->last_working_day->toDateString(),
                'Reason: '.$resignation->reason,
                '',
                (string) $resignation->description,
            ]),
        );
    }

    /**
     * @param  callable(array<string, mixed>, int): Model  $create
     */
    private function seed(string $file, callable $create): void
    {
        foreach (File::json(database_path("demo/{$file}.json"), JSON_THROW_ON_ERROR) as $row) {
            $employeeId = $this->employeeId($row['employee_email'], $row['employee_name']);

            if (! $employeeId) {
                continue;
            }

            $record = $create($row, $employeeId);

            if ($record->wasRecentlyCreated) {
                $record->forceFill(['created_at' => $row['created_at'], 'updated_at' => $row['created_at']])->save();
            }
        }
    }

    private function employeeId(?string $email, ?string $name): ?int
    {
        if (! $email && ! $name) {
            return null;
        }

        $userId = User::query()->where('email', $email)->value('id') ?? User::query()->where('name', $name)->value('id');

        return $userId ? Employee::query()->where('user_id', $userId)->value('id') : null;
    }

    private function userId(?string $email): ?int
    {
        return $email ? User::query()->where('email', $email)->value('id') : null;
    }
}
