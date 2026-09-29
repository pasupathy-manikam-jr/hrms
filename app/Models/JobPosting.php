<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Carbon\CarbonImmutable;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $job_code
 * @property string $title
 * @property bool $is_published
 * @property CarbonImmutable|null $publish_date
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable([
    'job_code', 'title', 'job_category_id', 'job_type_id', 'location_id', 'branch_id', 'department_id',
    'positions', 'min_experience', 'max_experience', 'min_salary', 'max_salary', 'description', 'requirements',
    'benefits', 'skills', 'start_date', 'application_deadline', 'priority', 'is_featured', 'is_published',
    'publish_date', 'status', 'created_by',
])]
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'job-postings';

    public const STATUSES = ['Draft', 'Published', 'Closed'];

    public const PRIORITIES = ['Low', 'Medium', 'High'];

    protected static function booted(): void
    {
        static::saving(function (JobPosting $posting) {
            $posting->is_published = $posting->status === 'Published';
            if ($posting->is_published) {
                $posting->publish_date ??= now();
            }
        });

        static::created(function (JobPosting $posting) {
            if (! $posting->job_code) {
                $posting->updateQuietly(['job_code' => sprintf('JOB-%05d', $posting->id)]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'start_date' => 'date:Y-m-d',
            'application_deadline' => 'date:Y-m-d',
            'publish_date' => 'date:Y-m-d',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'positions' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'job_category_id');
    }

    /**
     * @return BelongsTo<JobType, $this>
     */
    public function jobType(): BelongsTo
    {
        return $this->belongsTo(JobType::class);
    }

    /**
     * @return BelongsTo<JobLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(JobLocation::class, 'location_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Candidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'job_id');
    }
}
