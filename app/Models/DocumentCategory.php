<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_mandatory
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'description', 'color', 'icon', 'is_mandatory', 'status', 'created_by'])]
class DocumentCategory extends Model
{
    use HasCreator;

    public const MODULE = 'document-categories';

    public const STATUSES = ['active', 'inactive'];

    /** Tile icons offered on the form (lucide icon names). */
    public const ICONS = ['Folder', 'FileText', 'Shield', 'User', 'TrendingUp', 'Award', 'Scale', 'Heart', 'Banknote', 'Briefcase', 'GraduationCap', 'Building2'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'created_by' => 'integer'];
    }

    /**
     * @return HasMany<HrDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(HrDocument::class, 'category_id');
    }
}
