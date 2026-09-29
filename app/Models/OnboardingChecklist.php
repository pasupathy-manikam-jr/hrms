<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'description', 'is_default', 'status', 'created_by'])]
class OnboardingChecklist extends Model
{
    use HasCreator;

    public const MODULE = 'onboarding-checklists';

    public const STATUSES = ['active', 'inactive'];

    protected static function booted(): void
    {
        // Only one checklist is the default.
        static::saved(function (self $checklist) {
            if ($checklist->is_default) {
                static::query()->whereKeyNot($checklist->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return HasMany<ChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'checklist_id');
    }
}
