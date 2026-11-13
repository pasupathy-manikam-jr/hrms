<?php

namespace App\Models\Engagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $engagement_element_id
 * @property string $text
 * @property bool $reverse_scored
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['engagement_element_id', 'text', 'reverse_scored', 'is_active', 'sort_order'])]
class EngagementStatement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['engagement_element_id' => 'integer', 'reverse_scored' => 'boolean', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<EngagementElement, $this>
     */
    public function element(): BelongsTo
    {
        return $this->belongsTo(EngagementElement::class, 'engagement_element_id');
    }

    /**
     * @return BelongsToMany<EngagementCampaign, $this>
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(EngagementCampaign::class, 'engagement_campaign_statement');
    }

    /**
     * @return HasMany<EngagementAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EngagementAnswer::class);
    }
}
