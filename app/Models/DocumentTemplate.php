<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $template_content
 * @property bool $is_default
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'description', 'category_id', 'template_content', 'is_default', 'status', 'created_by'])]
class DocumentTemplate extends Model
{
    use HasCreator;

    public const MODULE = 'document-templates';

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['category_id' => 'integer', 'is_default' => 'boolean', 'created_by' => 'integer'];
    }

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }
}
