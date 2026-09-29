<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $branch_id
 * @property int|null $created_by
 */
#[Fillable(['name', 'description', 'branch_id', 'created_by'])]
class TrainingType extends Model
{
    use HasCreator;

    public const MODULE = 'training-types';

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /**
     * @return HasMany<TrainingProgram, $this>
     */
    public function trainingPrograms(): HasMany
    {
        return $this->hasMany(TrainingProgram::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'created_by' => 'integer'];
    }
}
