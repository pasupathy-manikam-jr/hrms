<?php

namespace App\Models\Concerns;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A record about an employee (award, promotion, warning...) with demo-style scoping:
 * "manage-any-x" sees everything, "manage-own-x" only records about the signed-in user.
 * The using model defines MODULE, the permission suffix (e.g. "promotions").
 */
trait BelongsToEmployee
{
    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-'.static::MODULE)) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->can('manage-own-'.static::MODULE) ? $user->id : 0));
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Employee picker options for the form and filter; empty for manage-own users.
     *
     * @return Collection<int, array{id: int, name: string, employee_id: string}>
     */
    public static function employeeOptions(User $user): Collection
    {
        if (! $user->can('manage-any-'.static::MODULE)) {
            return collect();
        }

        return Employee::query()->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
            ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id]);
    }
}
