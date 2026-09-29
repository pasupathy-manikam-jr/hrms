<?php

namespace App\Models;

use App\Models\Concerns\StoresUploads;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property int|null $category_id
 * @property string $status
 * @property bool $requires_acknowledgment
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int $download_count
 */
#[Fillable([
    'title', 'description', 'category_id', 'version', 'effective_date', 'expiry_date', 'requires_acknowledgment', 'status',
    'file_path', 'file_name', 'file_type', 'file_size', 'uploaded_by', 'created_by',
])]
class HrDocument extends Model
{
    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'hr-documents';

    public const STATUSES = ['draft', 'under_review', 'approved', 'published', 'archived', 'expired'];

    /** Hide the storage path; clients only get the download route. */
    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'effective_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'requires_acknowledgment' => 'boolean',
            'file_size' => 'integer',
            'download_count' => 'integer',
        ];
    }

    /**
     * Document editors see everything; everyone else (employees) only published documents
     * and the ones they were asked to acknowledge.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('edit-hr-documents')) {
            $query->where(fn (Builder $q) => $q
                ->where('status', 'published')
                ->orWhereHas('acknowledgments', fn (Builder $a) => $a->where('user_id', $user->id)));
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<DocumentAcknowledgment, $this>
     */
    public function acknowledgments(): HasMany
    {
        return $this->hasMany(DocumentAcknowledgment::class, 'document_id');
    }
}
