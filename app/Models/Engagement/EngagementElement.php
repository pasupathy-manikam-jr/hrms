<?php

namespace App\Models\Engagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the organisational factors engagement is measured on (e.g. Leadership); its score is the
 * favourable share of answers to its statements.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 */
#[Fillable(['name', 'description', 'sort_order'])]
class EngagementElement extends Model
{
    /**
     * @return HasMany<EngagementStatement, $this>
     */
    public function statements(): HasMany
    {
        return $this->hasMany(EngagementStatement::class);
    }
}
