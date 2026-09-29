<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\InterviewRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_id
 * @property string $name
 * @property int $sequence_number
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['job_id', 'name', 'sequence_number', 'description', 'status', 'created_by'])]
class InterviewRound extends Model
{
    /** @use HasFactory<InterviewRoundFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'interview-rounds';

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['job_id' => 'integer', 'sequence_number' => 'integer', 'created_by' => 'integer'];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }
}
