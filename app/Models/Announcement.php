<?php

namespace App\Models;

use App\Models\Concerns\StoresUploads;
use Carbon\CarbonImmutable;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property bool $is_company_wide
 * @property string|null $file_path
 * @property string|null $file_name
 */
#[Fillable(['title', 'category', 'description', 'content', 'start_date', 'end_date', 'is_featured', 'is_high_priority', 'is_company_wide', 'created_by', 'file_path', 'file_name', 'file_type', 'file_size'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'announcements';

    public const CATEGORIES = ['Company News', 'Policy Updates', 'HR Updates', 'Benefits', 'IT Updates', 'Events', 'Training', 'Finance'];

    public const STATUSES = ['active', 'upcoming', 'expired'];

    /** @var list<string> */
    protected $appends = ['status'];

    /** @var list<string> */
    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_featured' => 'boolean',
            'is_high_priority' => 'boolean',
            'is_company_wide' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Users who have opened the announcement.
     *
     * @return BelongsToMany<User, $this>
     */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_views')->withTimestamps();
    }

    /**
     * The employees it is addressed to: everyone when company-wide, else those in a target department or branch.
     *
     * @return Builder<Employee>
     */
    public function audience(): Builder
    {
        $this->loadMissing('departments:id', 'branches:id');

        return Employee::query()->when(! $this->is_company_wide, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->whereIn('department_id', $this->departments->modelKeys())
            ->orWhereIn('branch_id', $this->branches->modelKeys())));
    }

    /**
     * Active, upcoming or expired, from today's date against the start/end dates.
     *
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->start_date->isAfter(today()) => 'upcoming',
            $this->end_date !== null && $this->end_date->isBefore(today()) => 'expired',
            default => 'active',
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeWithStatus(Builder $query, string $status): void
    {
        $today = today()->toDateString();

        match ($status) {
            'upcoming' => $query->whereDate('start_date', '>', $today),
            'expired' => $query->whereDate('end_date', '<', $today),
            default => $query->whereDate('start_date', '<=', $today)
                ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today)),
        };
    }

    /**
     * Announcements a user may see: everything with manage-any-announcements; otherwise
     * company-wide ones, their own, and those targeted at their department or branch.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('manage-any-announcements')) {
            return;
        }

        $employee = $user->employee;

        $query->where(fn (Builder $q) => $q
            ->where('is_company_wide', true)
            ->orWhere('created_by', $user->id)
            ->when($employee?->department_id, fn (Builder $q, int $id) => $q->orWhereHas('departments', fn (Builder $d) => $d->whereKey($id)))
            ->when($employee?->branch_id, fn (Builder $q, int $id) => $q->orWhereHas('branches', fn (Builder $b) => $b->whereKey($id))));
    }
}
