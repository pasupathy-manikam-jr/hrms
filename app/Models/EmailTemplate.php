<?php

namespace App\Models;

use App\Support\TemplateRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'from'])]
class EmailTemplate extends Model
{
    /**
     * The named template's subject and HTML body in the current locale (falling back to English),
     * filled with $values; null when the template or both translations are missing.
     *
     * @param  array<string, string|int|float|null>  $values
     * @return array{subject: string, content: string}|null
     */
    public static function compose(string $name, array $values, ?string $lang = null): ?array
    {
        $lang ??= app()->getLocale();
        $translation = EmailTemplateLang::query()
            ->whereHas('template', fn ($q) => $q->where('name', $name))
            ->whereIn('lang', [$lang, 'en'])
            ->orderByRaw('lang = ? desc', [$lang])
            ->first();

        return $translation === null ? null : [
            // The subject is plain text, so undo the HTML escaping of the filled values.
            'subject' => html_entity_decode(TemplateRenderer::render($translation->subject, $values), ENT_QUOTES),
            'content' => TemplateRenderer::render($translation->content, $values),
        ];
    }

    /**
     * @return HasMany<EmailTemplateLang, $this>
     */
    public function emailTemplateLangs(): HasMany
    {
        return $this->hasMany(EmailTemplateLang::class, 'parent_id');
    }
}
