<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Carbon\CarbonImmutable;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $candidate_id
 * @property int $job_id
 * @property int|null $offer_template_id
 * @property string $salary
 * @property string|null $bonus
 * @property string|null $benefits
 * @property CarbonImmutable $offer_date
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $expiration_date
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable([
    'candidate_id', 'job_id', 'offer_template_id', 'offer_date', 'salary', 'bonus', 'benefits', 'start_date',
    'expiration_date', 'status', 'response_date', 'decline_reason', 'approved_by', 'created_by',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'offers';

    public const STATUSES = ['Draft', 'Sent', 'Accepted', 'Negotiating', 'Declined', 'Expired'];

    /** Final statuses: the demo hides Edit / Update Status once reached. */
    public const CLOSED = ['Accepted', 'Declined'];

    /** Candidate pipeline status each offer response leads to (as in the demo data). */
    public const CANDIDATE_STATUS = ['Accepted' => 'Hired', 'Declined' => 'Rejected', 'Expired' => 'Rejected'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offer_date' => 'date:Y-m-d',
            'start_date' => 'date:Y-m-d',
            'expiration_date' => 'date:Y-m-d',
            'response_date' => 'date:Y-m-d',
            'salary' => 'decimal:2',
            'bonus' => 'decimal:2',
            'candidate_id' => 'integer',
            'job_id' => 'integer',
            'offer_template_id' => 'integer',
            'approved_by' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }

    /**
     * @return BelongsTo<OfferTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OfferTemplate::class, 'offer_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
