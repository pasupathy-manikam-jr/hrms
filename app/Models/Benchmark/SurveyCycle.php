<?php

namespace App\Models\Benchmark;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One edition of the survey (e.g. 2025/2026): its blank template, dropdown lists and job catalogue,
 * and the companies whose completed workbooks were uploaded.
 *
 * @property int $id
 * @property string $name
 * @property string $status
 * @property int $min_companies
 * @property string|null $template_path
 * @property string|null $template_name
 * @property array<string, list<string>>|null $lookups
 */
#[Fillable(['name', 'status', 'min_companies', 'template_path', 'template_name', 'lookups', 'created_by'])]
class SurveyCycle extends Model
{
    public const STATUSES = ['open', 'closed'];

    /** @var list<string> */
    protected $hidden = ['template_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['lookups' => 'array', 'min_companies' => 'integer'];
    }

    /**
     * @return HasMany<SurveyParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(SurveyParticipant::class);
    }

    /**
     * @return HasMany<BenchmarkJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(BenchmarkJob::class);
    }

    /**
     * One of the template's dropdown lists (e.g. "IndustryList").
     *
     * @return list<string>
     */
    public function lookup(string $name): array
    {
        return $this->lookups[$name] ?? [];
    }
}
