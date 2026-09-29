<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $question
 * @property string $type
 * @property list<string>|null $options
 * @property bool $required
 * @property int $sort_order
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['question', 'type', 'options', 'required', 'sort_order', 'status', 'created_by'])]
class CustomQuestion extends Model
{
    use HasCreator;

    public const MODULE = 'custom-questions';

    public const TYPES = ['text', 'textarea', 'number', 'email', 'date', 'select', 'radio', 'checkbox'];

    /** Field types that offer a fixed list of options. */
    public const OPTION_TYPES = ['select', 'radio', 'checkbox'];

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'sort_order' => 'integer',
            'created_by' => 'integer',
        ];
    }
}
