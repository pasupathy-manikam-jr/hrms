<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_required
 * @property string|null $description
 * @property int|null $created_by
 */
#[Fillable(['name', 'is_required', 'description', 'created_by'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'document-types';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'created_by' => 'integer'];
    }
}
