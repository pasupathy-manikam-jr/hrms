<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Demo-style "manage-any-x" vs "manage-own-x" scoping on a created_by column.
 * The using model defines MODULE, the permission suffix (e.g. "job-postings").
 */
trait HasCreator
{
    protected static function bootHasCreator(): void
    {
        static::creating(function (self $model) {
            $model->created_by ??= Auth::user()?->id;
        });
    }

    /**
     * Records the user may see: all with manage-any-x, otherwise only their own.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-'.static::MODULE)) {
            $query->where($this->qualifyColumn('created_by'), $user->id);
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->can('manage-any-'.static::MODULE) || $this->created_by === $user->id;
    }
}
