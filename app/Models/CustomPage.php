<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'content', 'meta_title', 'meta_description', 'is_active', 'sort_order'])]
class CustomPage extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * Active pages for the public header and footer links.
     *
     * @return list<array{title: string, slug: string}>
     */
    public static function links(): array
    {
        /** @var list<array{title: string, slug: string}> */
        return static::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['title', 'slug'])->toArray();
    }
}
