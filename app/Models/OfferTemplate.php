<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\OfferTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $template_content
 * @property list<string>|null $variables
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'template_content', 'variables', 'status', 'created_by'])]
class OfferTemplate extends Model
{
    /** @use HasFactory<OfferTemplateFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'offer-templates';

    public const STATUSES = ['active', 'inactive'];

    /** Matches {{name}} (the demo's syntax) and {name}. */
    public const PLACEHOLDER = '/\{\{?\s*([a-z0-9_]+)\s*\}?\}/i';

    protected static function booted(): void
    {
        static::saving(function (OfferTemplate $template) {
            preg_match_all(self::PLACEHOLDER, $template->template_content, $matches);
            $template->variables = array_values(array_unique($matches[1]));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['variables' => 'array', 'created_by' => 'integer'];
    }
}
