<?php

namespace App\Models\Engagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $engagement_response_id
 * @property int $engagement_statement_id
 * @property int $rating 1 = strongly disagree … 4 = strongly agree
 */
#[Fillable(['engagement_response_id', 'engagement_statement_id', 'rating'])]
class EngagementAnswer extends Model
{
    public const RATINGS = [4 => 'Strongly Agree', 3 => 'Agree', 2 => 'Disagree', 1 => 'Strongly Disagree'];

    public $timestamps = false;
}
