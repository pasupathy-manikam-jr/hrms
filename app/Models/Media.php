<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\StoresUploads;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file in the media library, stored privately and served only through permission-checked routes.
 *
 * @property int $id
 * @property string $name
 * @property int|null $directory_id
 * @property string|null $file_path
 * @property string $file_name
 * @property string|null $file_type
 * @property int $file_size
 * @property int|null $created_by
 */
#[Fillable(['name', 'directory_id', 'file_path', 'file_name', 'file_type', 'file_size', 'created_by'])]
class Media extends Model
{
    use HasCreator;
    use StoresUploads;

    public const MODULE = 'media';

    public const UPLOAD_DIRECTORY = 'media';

    protected $table = 'media';

    /** Hide the storage path; clients only get the preview / download routes. */
    protected $hidden = ['file_path'];

    /** @var list<string> */
    protected $appends = ['is_image'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'directory_id' => 'integer',
            'file_size' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * Whether the page may show an inline preview.
     *
     * @return Attribute<bool, never>
     */
    protected function isImage(): Attribute
    {
        return Attribute::get(fn () => $this->hasPreview());
    }

    /**
     * @return BelongsTo<MediaDirectory, $this>
     */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(MediaDirectory::class, 'directory_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
