<?php

namespace App\Models\Engagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A score band (1 = lowest … 5 = highest) mapped to a level of need, with the action it calls for.
 *
 * @property int $id
 * @property int $level
 * @property string $name
 * @property string $min_score
 * @property string|null $need
 * @property string|null $profile
 * @property string|null $action
 */
#[Fillable(['level', 'name', 'min_score', 'need', 'profile', 'action'])]
class EngagementTier extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => 'integer', 'min_score' => 'decimal:2'];
    }
}
