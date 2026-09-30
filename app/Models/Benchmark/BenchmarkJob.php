<?php

namespace App\Models\Benchmark;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A standard job title from the survey's Job Catalogue.
 *
 * @property int $id
 * @property string $code
 * @property string $industry
 * @property string $job_family
 * @property string $title
 * @property string|null $typical_level
 */
#[Fillable([
    'survey_cycle_id', 'code', 'industry', 'job_family', 'title', 'typical_level', 'summary',
    'responsibilities', 'requirements', 'masco_group', 'masco_reference',
])]
class BenchmarkJob extends Model
{
    public $timestamps = false;
}
