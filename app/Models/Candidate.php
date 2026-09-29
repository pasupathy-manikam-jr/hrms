<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_id
 * @property string $first_name
 * @property string $last_name
 * @property string $status
 * @property Carbon|null $application_date
 * @property int|null $created_by
 */
#[Fillable([
    'job_id', 'source_id', 'first_name', 'last_name', 'email', 'phone', 'gender', 'date_of_birth', 'address',
    'city', 'state', 'zip_code', 'country', 'current_company', 'current_position', 'experience_years',
    'current_salary', 'expected_salary', 'final_salary', 'notice_period', 'skills', 'education', 'portfolio_url',
    'linkedin_url', 'status', 'application_date', 'created_by',
])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'candidates';

    public const STATUSES = ['New', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected'];

    public const GENDERS = ['male', 'female', 'other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'application_date' => 'date:Y-m-d',
            'experience_years' => 'integer',
            'job_id' => 'integer',
            'source_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }

    /**
     * @return BelongsTo<CandidateSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CandidateSource::class, 'source_id');
    }
}
