<?php

namespace Database\Seeders\Modules;

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingRoom;
use App\Models\MeetingType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class MeetingSeeder extends Seeder
{
    /**
     * Seed the demo's meeting types, rooms, meetings (with attendees) and action items.
     * Demo people are matched to our users by email; unknown organizers/assignees fall back
     * to the company user and unknown attendees are skipped.
     */
    public function run(): void
    {
        $types = collect($this->demo('meeting-types'))
            ->mapWithKeys(fn (array $type) => [$type['name'] => MeetingType::query()->firstOrCreate(['name' => $type['name']], $type)->id]);
        $rooms = collect($this->demo('meeting-rooms'))
            ->mapWithKeys(fn (array $room) => [$room['name'] => MeetingRoom::query()->firstOrCreate(['name' => $room['name']], $room)->id]);

        $users = User::query()->pluck('id', 'email');
        $company = $users['company@example.com'] ?? User::query()->value('id');

        foreach ($this->demo('meetings') as $row) {
            $meeting = Meeting::query()->firstOrCreate(
                ['title' => $row['title'], 'meeting_date' => $row['meeting_date'], 'start_time' => $row['start_time']],
                [
                    ...collect($row)->except(['type', 'room', 'organizer', 'attendees'])->all(),
                    'type_id' => $types[$row['type']] ?? null,
                    'room_id' => $rooms[$row['room']] ?? null,
                    'organizer_id' => $users[$row['organizer']] ?? $company,
                ],
            );
            $meeting->attendees()->sync($users->only($row['attendees'])->values());
        }

        foreach ($this->demo('action-items') as $row) {
            $meetingId = Meeting::query()->where('title', $row['meeting'])->whereDate('meeting_date', $row['meeting_date'])->value('id');

            ActionItem::query()->firstOrCreate(
                ['meeting_id' => $meetingId, 'title' => $row['title']],
                [...collect($row)->except(['meeting', 'meeting_date', 'assignee'])->all(), 'assigned_to' => $users[$row['assignee']] ?? $company],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demo(string $module): array
    {
        return File::json(database_path("demo/{$module}.json"), JSON_THROW_ON_ERROR);
    }
}
