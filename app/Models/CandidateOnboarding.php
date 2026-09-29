<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Status and progress are derived from the tasks, never stored.
 *
 * @property int $id
 * @property int $candidate_id
 * @property int|null $checklist_id
 * @property Carbon $start_date
 * @property int|null $buddy_employee_id
 * @property int|null $created_by
 * @property-read string $status
 * @property-read int $progress
 */
#[Fillable(['candidate_id', 'checklist_id', 'start_date', 'buddy_employee_id', 'created_by'])]
class CandidateOnboarding extends Model
{
    use HasCreator;

    public const MODULE = 'candidate-onboarding';

    public const STATUSES = ['Pending', 'In Progress', 'Completed'];

    /**
     * Start onboarding: copy the checklist's items into this onboarding's own tasks.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function start(array $attributes): self
    {
        return DB::transaction(function () use ($attributes) {
            $onboarding = self::create($attributes);
            $items = ChecklistItem::query()->where('checklist_id', $onboarding->checklist_id)->where('status', 'active')->orderBy('sort_order')->orderBy('due_day')->get();

            $onboarding->tasks()->createMany($items->map(fn (ChecklistItem $item) => [
                ...$item->only(['task_name', 'description', 'category', 'assigned_to_role', 'due_day', 'is_required', 'sort_order']),
                'due_date' => $onboarding->start_date->copy()->addDays($item->due_day),
            ]));

            return $onboarding;
        });
    }

    protected static function booted(): void
    {
        // Moving the start date moves every task's due date with it.
        static::updated(function (self $onboarding) {
            if ($onboarding->wasChanged('start_date')) {
                $onboarding->tasks->each(fn (CandidateOnboardingTask $task) => $task->update(['due_date' => $onboarding->start_date->copy()->addDays($task->due_day)]));
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeWithStatus(Builder $query, string $status): void
    {
        $done = fn (Builder $task) => $task->where('status', 'completed');
        $open = fn (Builder $task) => $task->where('status', '!=', 'completed');

        match ($status) {
            'Pending' => $query->whereDoesntHave('tasks', $done),
            'In Progress' => $query->whereHas('tasks', $done)->whereHas('tasks', $open),
            'Completed' => $query->whereHas('tasks', $done)->whereDoesntHave('tasks', $open),
            default => null,
        };
    }

    /**
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(function () {
            $done = $this->tasks->where('status', 'completed')->count();

            return match (true) {
                $done === 0 => 'Pending',
                $done === $this->tasks->count() => 'Completed',
                default => 'In Progress',
            };
        });
    }

    /**
     * @return Attribute<int, never>
     */
    protected function progress(): Attribute
    {
        return Attribute::get(fn () => $this->tasks->isEmpty()
            ? 0
            : (int) round($this->tasks->where('status', 'completed')->count() * 100 / $this->tasks->count()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'candidate_id' => 'integer',
            'checklist_id' => 'integer',
            'buddy_employee_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<OnboardingChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(OnboardingChecklist::class, 'checklist_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function buddy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'buddy_employee_id');
    }

    /**
     * @return HasMany<CandidateOnboardingTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CandidateOnboardingTask::class)->orderBy('sort_order')->orderBy('due_date')->orderBy('id');
    }
}
