<?php

namespace App\Models\Engagement;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $closes_on
 * @property int $min_group_size
 */
#[Fillable(['name', 'description', 'status', 'closes_on', 'min_group_size', 'created_by'])]
class EngagementCampaign extends Model
{
    public const STATUSES = ['draft', 'open', 'closed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['closes_on' => 'date:Y-m-d', 'min_group_size' => 'integer'];
    }

    /**
     * @return BelongsToMany<EngagementStatement, $this>
     */
    public function statements(): BelongsToMany
    {
        return $this->belongsToMany(EngagementStatement::class, 'engagement_campaign_statement')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<EngagementResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(EngagementResponse::class);
    }

    /**
     * Open for answers: status is open and the closing date (if any) hasn't passed.
     */
    public function isAcceptingResponses(): bool
    {
        return $this->status === 'open' && ($this->closes_on === null || $this->closes_on->gte(today()));
    }

    /**
     * The open campaigns plus any the employee already answered, with whether they can answer each.
     *
     * @return Collection<int, array{id: int, name: string, description: string|null, status: string, closes_on: Carbon|null, statements_count: int<0, max>, answered_at: mixed, can_answer: bool}>
     */
    public static function listFor(?Employee $employee): Collection
    {
        $answered = $employee
            ? EngagementResponse::query()->where('employee_id', $employee->id)->pluck('created_at', 'engagement_campaign_id')
            : collect();

        return self::query()
            ->where(fn ($q) => $q->where('status', 'open')->orWhereIn('id', $answered->keys()))
            ->withCount('statements')->latest('id')->get()
            ->map(fn (EngagementCampaign $campaign) => [
                ...$campaign->only('id', 'name', 'description', 'status', 'closes_on', 'statements_count'),
                'answered_at' => $answered[$campaign->id] ?? null,
                'can_answer' => $employee !== null && ! $answered->has($campaign->id) && $campaign->isAcceptingResponses(),
            ]);
    }

    /**
     * The statements to rate, in order, and the rating scale.
     *
     * @return array{statements: Collection<int, array{id: int, text: string, element: string|null}>, ratings: Collection<int, array{value: int, label: string}>}
     */
    public function questionnaire(): array
    {
        return [
            'statements' => $this->statements()->with('element:id,name')->get()
                ->map(fn (EngagementStatement $statement) => ['id' => $statement->id, 'text' => $statement->text, 'element' => $statement->element?->name]),
            'ratings' => collect(EngagementAnswer::RATINGS)->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])->values(),
        ];
    }

    /**
     * Why this employee can't answer now (closed, or already answered), or null if they can.
     */
    public function refusalFor(Employee $employee): ?string
    {
        if (! $this->isAcceptingResponses()) {
            return __('This survey is not open for answers.');
        }

        if ($this->responses()->where('employee_id', $employee->id)->exists()) {
            return __('You have already answered this survey.');
        }

        return null;
    }

    /**
     * Ask every active statement in the bank, grouped by element in bank order, then the overall ones.
     */
    public function attachActiveStatements(): void
    {
        $statements = EngagementStatement::query()
            ->where('is_active', true)
            ->leftJoin('engagement_elements', 'engagement_elements.id', '=', 'engagement_statements.engagement_element_id')
            ->orderByRaw('engagement_elements.id is null')
            ->orderBy('engagement_elements.sort_order')
            ->orderBy('engagement_statements.sort_order')
            ->orderBy('engagement_statements.id')
            ->pluck('engagement_statements.id');

        $this->statements()->sync($statements->values()->mapWithKeys(fn ($id, int $index) => [(int) $id => ['sort_order' => $index + 1]])->all());
    }
}
