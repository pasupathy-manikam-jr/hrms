<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\GoalTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'description', 'status', 'created_by'])]
class GoalType extends Model
{
    /** @use HasFactory<GoalTypeFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'goal-types';

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_by' => 'integer'];
    }
}
