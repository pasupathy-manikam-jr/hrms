<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $subject
 * @property string $content
 */
#[Fillable(['parent_id', 'lang', 'subject', 'content'])]
class EmailTemplateLang extends Model
{
    /**
     * @return BelongsTo<EmailTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'parent_id');
    }

    protected function casts(): array
    {
        return ['parent_id' => 'integer'];
    }
}
