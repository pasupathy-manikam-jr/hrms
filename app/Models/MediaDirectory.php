<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A media library folder (flat, like the demo's).
 *
 * @property int $id
 * @property string $name
 * @property int|null $created_by
 */
#[Fillable(['name', 'created_by'])]
class MediaDirectory extends Model
{
    use HasCreator;

    public const MODULE = 'media-directories';

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'directory_id');
    }
}
