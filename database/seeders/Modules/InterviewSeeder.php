<?php

namespace Database\Seeders\Modules;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\InterviewRound;
use App\Models\InterviewType;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\OfferTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class InterviewSeeder extends Seeder
{
    /**
     * Seed the demo's interview types and rounds, interviews, feedback, offer templates and offers.
     * Runs after RecruitmentSeeder (job postings and candidates) and EmployeeSeeder (interviewer users).
     */
    public function run(): void
    {
        $demo = File::json(database_path('demo/interviews.json'), JSON_THROW_ON_ERROR);
        $owner = User::query()->where('email', 'company@example.com')->value('id');
        $users = User::query()->pluck('id', 'email');
        $jobs = JobPosting::query()->pluck('id', 'job_code');
        $candidates = Candidate::query()->get(['id', 'email', 'job_id'])->keyBy('email');

        foreach ($demo['interview_types'] as $row) {
            InterviewType::query()->firstOrCreate(['name' => $row['name']], $row + ['created_by' => $owner]);
        }

        foreach ($demo['interview_rounds'] as $row) {
            if ($jobId = $jobs[$row['job_code']] ?? null) {
                InterviewRound::query()->firstOrCreate(
                    ['job_id' => $jobId, 'sequence_number' => $row['sequence_number']],
                    Arr::except($row, 'job_code') + ['created_by' => $owner],
                );
            }
        }

        $types = InterviewType::query()->pluck('id', 'name');
        $rounds = InterviewRound::query()->get(['id', 'job_id', 'name'])->keyBy(fn (InterviewRound $r) => $r->job_id.'|'.$r->name);
        $interviews = [];
        // The demo's interviews ran 17-25 Jan with "today" between the last completed (22nd) and the first
        // scheduled one (24th); move 23 Jan to today so the week view and "upcoming" have something to show.
        $shift = (int) CarbonImmutable::parse('2026-01-23')->diffInDays(today(), false);

        foreach ($demo['interviews'] as $row) {
            $candidate = $candidates[$row['candidate_email']] ?? null;
            $roundId = $candidate ? ($rounds[$candidate->job_id.'|'.$row['round']]->id ?? null) : null;
            if (! $candidate || ! $roundId) {
                continue;
            }

            $interview = Interview::query()->firstOrCreate(['candidate_id' => $candidate->id, 'round_id' => $roundId], [
                ...Arr::except($row, ['candidate_email', 'round', 'interview_type', 'interviewers']),
                'scheduled_date' => CarbonImmutable::parse($row['scheduled_date'])->addDays($shift)->toDateString(),
                'job_id' => $candidate->job_id,
                'interview_type_id' => $types[$row['interview_type']] ?? null,
                'created_by' => $owner,
            ]);
            $interview->interviewers()->syncWithoutDetaching($users->only($row['interviewers'])->values()->all());
            $interviews[$row['candidate_email'].'|'.$row['round']] = $interview->id;
        }

        foreach ($demo['interview_feedback'] as $row) {
            $interviewId = $interviews[$row['candidate_email'].'|'.$row['round']] ?? null;
            if ($interviewId) {
                InterviewFeedback::query()->firstOrCreate(
                    ['interview_id' => $interviewId, 'interviewer_id' => $users[$row['interviewer']] ?? null, 'comments' => $row['comments']],
                    Arr::except($row, ['candidate_email', 'round', 'interviewer']) + ['created_by' => $owner],
                );
            }
        }

        foreach ($demo['offer_templates'] as $row) {
            OfferTemplate::query()->firstOrCreate(['name' => $row['name']], Arr::except($row, 'variables') + ['created_by' => $owner]);
        }

        $templateId = OfferTemplate::query()->where('name', 'Standard Full-Time Offer')->value('id');

        foreach ($demo['offers'] as $row) {
            $candidate = $candidates[$row['candidate_email']] ?? null;
            if ($candidate) {
                Offer::query()->firstOrCreate(['candidate_id' => $candidate->id], [
                    ...Arr::except($row, ['candidate_email', 'job_code', 'approved']),
                    'job_id' => $jobs[$row['job_code']] ?? $candidate->job_id,
                    'offer_template_id' => $templateId,
                    'approved_by' => $row['approved'] ? $users['hr@example.com'] ?? null : null,
                    'created_by' => $owner,
                ]);
            }
        }
    }
}
