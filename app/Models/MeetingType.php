<?php

namespace App\Models;

use Database\Factories\MeetingTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'color', 'default_duration', 'status'])]
class MeetingType extends Model
{
    /** @use HasFactory<MeetingTypeFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return HasMany<Meeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'type_id');
    }

    protected function casts(): array
    {
        return ['default_duration' => 'integer'];
    }
}
