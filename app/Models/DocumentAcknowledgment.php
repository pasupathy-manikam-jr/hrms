<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's acknowledgment of an HR document. Stored status is pending, acknowledged or exempted;
 * a pending one past its due date reads as "overdue".
 *
 * @property int $id
 * @property int $document_id
 * @property int $user_id
 * @property string $status
 * @property CarbonInterface|null $due_date
 * @property CarbonInterface|null $acknowledged_at
 */
#[Fillable(['document_id', 'user_id', 'status', 'due_date', 'acknowledged_at', 'acknowledgment_note', 'ip_address', 'user_agent', 'assigned_by'])]
class DocumentAcknowledgment extends Model
{
    public const MODULE = 'document-acknowledgments';

    /** Statuses a user can set; "overdue" is derived. */
    public const STORED_STATUSES = ['pending', 'acknowledged', 'exempted'];

    public const STATUSES = ['pending', 'acknowledged', 'overdue', 'exempted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'user_id' => 'integer',
            'due_date' => 'date:Y-m-d',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (string $value) => $value === 'pending' && $this->due_date?->isBefore(today()) ? 'overdue' : $value);
    }

    /**
     * Filter by effective status (overdue = pending and past due).
     *
     * @param  Builder<self>  $query
     */
    public function scopeWhereStatus(Builder $query, string $status): void
    {
        match ($status) {
            'overdue' => $query->where('status', 'pending')->whereDate('due_date', '<', today()),
            'pending' => $query->where('status', 'pending')->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', today())),
            default => $query->where('status', $status),
        };
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-'.self::MODULE)) {
            $query->where('user_id', $user->can('manage-own-'.self::MODULE) ? $user->id : 0);
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * @return BelongsTo<HrDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
