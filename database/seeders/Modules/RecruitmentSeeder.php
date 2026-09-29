<?php

namespace Database\Seeders\Modules;

use App\Models\Branch;
use App\Models\Candidate;
use App\Models\CandidateSource;
use App\Models\Department;
use App\Models\JobCategory;
use App\Models\JobLocation;
use App\Models\JobPosting;
use App\Models\JobType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class RecruitmentSeeder extends Seeder
{
    /**
     * Seed the demo's recruitment setup, job postings and candidates.
     */
    public function run(): void
    {
        $owner = User::query()->where('email', 'company@example.com')->value('id');

        foreach (['job-categories' => JobCategory::class, 'job-types' => JobType::class, 'job-locations' => JobLocation::class, 'candidate-sources' => CandidateSource::class] as $module => $model) {
            foreach ($this->demo($module) as $row) {
                $model::query()->firstOrCreate(['name' => $row['name']], $row + ['created_by' => $owner]);
            }
        }

        $types = JobType::query()->pluck('id', 'name');
        $locations = JobLocation::query()->pluck('id', 'name');
        $branches = Branch::query()->pluck('id', 'name');
        $departments = Department::query()->get(['id', 'name', 'branch_id'])->keyBy(fn (Department $d) => $d->branch_id.'|'.$d->name);

        foreach ($this->demo('job-postings') as $row) {
            $branchId = $branches[$row['branch']] ?? null;

            JobPosting::query()->firstOrCreate(['job_code' => $row['job_code']], [
                ...collect($row)->except(['job_type', 'location', 'branch', 'department'])->all(),
                'job_type_id' => $types[$row['job_type']] ?? null,
                'location_id' => $locations[$row['location']] ?? null,
                'branch_id' => $branchId,
                'department_id' => $departments[$branchId.'|'.$row['department']]->id ?? null,
                'created_by' => $owner,
            ]);
        }

        $jobs = JobPosting::query()->pluck('id', 'job_code');
        $sources = CandidateSource::query()->pluck('id', 'name');

        foreach ($this->demo('candidates') as $row) {
            Candidate::query()->firstOrCreate(['email' => $row['email'], 'job_id' => $jobs[$row['job_code']]], [
                ...collect($row)->except(['job_code', 'source'])->all(),
                'source_id' => $sources[$row['source']] ?? null,
                'created_by' => $owner,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demo(string $module): array
    {
        return File::json(database_path("demo/{$module}.json"), JSON_THROW_ON_ERROR);
    }
}
