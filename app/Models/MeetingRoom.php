<?php

namespace App\Models;

use Database\Factories\MeetingRoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'type', 'location', 'capacity', 'equipment', 'booking_url', 'status'])]
class MeetingRoom extends Model
{
    /** @use HasFactory<MeetingRoomFactory> */
    use HasFactory;

    public const TYPES = ['Physical', 'Virtual'];

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return HasMany<Meeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'room_id');
    }

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'equipment' => 'array'];
    }
}
