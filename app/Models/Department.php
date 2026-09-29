<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $branch_id
 * @property string|null $description
 * @property string $status
 */
#[Fillable(['name', 'branch_id', 'description', 'status'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['branch_id' => 'integer'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<Designation, $this>
     */
    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }
}
