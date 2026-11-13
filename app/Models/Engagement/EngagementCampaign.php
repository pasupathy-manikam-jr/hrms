<?php

namespace App\Models\Engagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $closes_on
 * @property int $min_group_size
 */
#[Fillable(['name', 'description', 'status', 'closes_on', 'min_group_size', 'created_by'])]
class EngagementCampaign extends Model
{
    public const STATUSES = ['draft', 'open', 'closed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['closes_on' => 'date:Y-m-d', 'min_group_size' => 'integer'];
    }

    /**
     * @return BelongsToMany<EngagementStatement, $this>
     */
    public function statements(): BelongsToMany
    {
        return $this->belongsToMany(EngagementStatement::class, 'engagement_campaign_statement')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<EngagementResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(EngagementResponse::class);
    }

    /**
     * Open for answers: status is open and the closing date (if any) hasn't passed.
     */
    public function isAcceptingResponses(): bool
    {
        return $this->status === 'open' && ($this->closes_on === null || $this->closes_on->gte(today()));
    }

    /**
     * Ask every active statement in the bank, grouped by element in bank order, then the overall ones.
     */
    public function attachActiveStatements(): void
    {
        $statements = EngagementStatement::query()
            ->where('is_active', true)
            ->leftJoin('engagement_elements', 'engagement_elements.id', '=', 'engagement_statements.engagement_element_id')
            ->orderByRaw('engagement_elements.id is null')
            ->orderBy('engagement_elements.sort_order')
            ->orderBy('engagement_statements.sort_order')
            ->orderBy('engagement_statements.id')
            ->pluck('engagement_statements.id');

        $this->statements()->sync($statements->values()->mapWithKeys(fn ($id, int $index) => [(int) $id => ['sort_order' => $index + 1]])->all());
    }
}
