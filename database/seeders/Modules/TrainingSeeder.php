<?php

namespace Database\Seeders\Modules;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\TrainingAssessment;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class TrainingSeeder extends Seeder
{
    /**
     * Seed the demo's training types, programs, sessions (with trainers), employee trainings,
     * assessments and assessment results. Demo people are matched to our employees by email;
     * unknown ones are skipped.
     */
    public function run(): void
    {
        $demo = File::json(database_path('demo/training.json'), JSON_THROW_ON_ERROR);
        $company = User::query()->where('email', 'company@example.com')->value('id');
        $branches = Branch::query()->pluck('id', 'name');
        $employees = Employee::query()->join('users', 'users.id', '=', 'employees.user_id')->pluck('employees.id', 'users.email');

        $types = [];
        foreach ($demo['types'] as $row) {
            $branchId = $branches[$row['branch']] ?? null;
            $type = TrainingType::query()->firstOrCreate(['name' => $row['name']], [
                'description' => $row['description'], 'branch_id' => $branchId, 'created_by' => $company,
            ]);
            $type->departments()->syncWithoutDetaching(
                Department::query()->where('branch_id', $branchId)->whereIn('name', $row['departments'])->pluck('id'),
            );
            $types[$row['name']] = $type->id;
        }

        $programs = [];
        foreach ($demo['programs'] as $row) {
            $programs[$row['name']] = TrainingProgram::query()->firstOrCreate(['name' => $row['name']], [
                ...Arr::except($row, 'type'), 'training_type_id' => $types[$row['type']], 'created_by' => $company,
            ])->id;
        }

        foreach ($demo['sessions'] as $row) {
            $session = TrainingSession::query()->firstOrCreate(
                ['training_program_id' => $programs[$row['program']], 'name' => $row['name'], 'start_date' => $row['start_date']],
                [...Arr::except($row, ['program', 'trainers']), 'created_by' => $company],
            );
            $session->trainers()->syncWithoutDetaching($employees->only($row['trainers'])->values());
        }

        $trainings = [];
        foreach ($demo['employee_trainings'] as $row) {
            if (! isset($employees[$row['employee']])) {
                continue;
            }

            $trainings[$row['employee'].'|'.$row['program']] = EmployeeTraining::query()->firstOrCreate(
                ['employee_id' => $employees[$row['employee']], 'training_program_id' => $programs[$row['program']]],
                [...Arr::except($row, ['employee', 'program']), 'assigned_by' => $company],
            );
        }

        $assessments = [];
        foreach ($demo['assessments'] as $row) {
            $assessments[$row['program'].'|'.$row['name']] = TrainingAssessment::query()->firstOrCreate(
                ['training_program_id' => $programs[$row['program']], 'name' => $row['name']],
                [...Arr::except($row, 'program'), 'created_by' => $company],
            );
        }

        foreach ($demo['results'] as $row) {
            $training = $trainings[$row['employee'].'|'.$row['program']] ?? null;
            $assessment = $assessments[$row['program'].'|'.$row['assessment']];

            $training?->results()->firstOrCreate(['training_assessment_id' => $assessment->id], [
                'score' => $row['score'],
                'is_passed' => (float) $row['score'] >= (float) $assessment->passing_score,
                'feedback' => $row['feedback'],
                'assessment_date' => $row['assessment_date'],
                'assessed_by' => $company,
            ]);
        }
    }
}
