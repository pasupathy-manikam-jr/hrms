<?php

namespace Database\Seeders\Modules;

use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\CandidateOnboarding;
use App\Models\CustomQuestion;
use App\Models\Employee;
use App\Models\OnboardingChecklist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class OnboardingSeeder extends Seeder
{
    /**
     * Seed the demo's custom questions, candidate assessments, onboarding checklists and candidate onboarding.
     */
    public function run(): void
    {
        $owner = User::query()->where('email', 'company@example.com')->value('id');
        $demo = File::json(database_path('demo/onboarding.json'), JSON_THROW_ON_ERROR);
        $candidates = Candidate::query()->pluck('id', 'email');
        $users = User::query()->pluck('id', 'name');

        foreach ($demo['custom_questions'] as $row) {
            CustomQuestion::query()->firstOrCreate(['question' => $row['question']], $row + ['created_by' => $owner]);
        }

        foreach ($demo['candidate_assessments'] as $row) {
            if (! $candidateId = $candidates[$row['candidate_email']] ?? null) {
                continue;
            }

            CandidateAssessment::query()->firstOrCreate(
                ['candidate_id' => $candidateId, 'assessment_name' => $row['assessment_name']],
                [...array_diff_key($row, ['candidate_email' => true]), 'conducted_by' => $users[$row['conducted_by']] ?? null, 'created_by' => $owner],
            );
        }

        foreach ($demo['onboarding_checklists'] as $row) {
            $checklist = OnboardingChecklist::query()->firstOrCreate(['name' => $row['name']], array_diff_key($row, ['items' => true]) + ['created_by' => $owner]);

            foreach ($row['items'] as $item) {
                $checklist->items()->firstOrCreate(['task_name' => $item['task_name']], $item + ['created_by' => $owner]);
            }
        }

        $checklists = OnboardingChecklist::query()->pluck('id', 'name');
        $buddies = Employee::query()->join('users', 'users.id', '=', 'employees.user_id')->pluck('employees.id', 'users.name');

        foreach ($demo['candidate_onboarding'] as $row) {
            $candidateId = $candidates[$row['candidate_email']] ?? null;
            if (! $candidateId || CandidateOnboarding::query()->where('candidate_id', $candidateId)->exists()) {
                continue;
            }

            $onboarding = CandidateOnboarding::start([
                'candidate_id' => $candidateId,
                'checklist_id' => $checklists[$row['checklist']],
                'start_date' => $row['start_date'],
                'buddy_employee_id' => $buddies[$row['buddy']] ?? null,
                'created_by' => $owner,
            ]);

            // Tick off tasks so the derived status matches the demo's.
            $done = match ($row['status']) {
                'Completed' => $onboarding->tasks,
                'In Progress' => $onboarding->tasks->take(intdiv($onboarding->tasks->count(), 2)),
                default => collect(),
            };
            $done->each(fn ($task) => $task->update(['status' => 'completed', 'completed_at' => $task->due_date]));
        }
    }
}
