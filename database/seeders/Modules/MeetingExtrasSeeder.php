<?php

namespace Database\Seeders\Modules;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\MeetingMinute;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class MeetingExtrasSeeder extends Seeder
{
    /**
     * Seed the demo's attendee RSVP/attendance details and meeting minutes onto the meetings
     * MeetingSeeder created. Demo people are matched by email; unknown attendees are skipped
     * and unknown recorders fall back to the company user.
     */
    public function run(): void
    {
        $users = User::query()->pluck('id', 'email');
        $company = $users['company@example.com'] ?? User::query()->value('id');
        $meetings = Meeting::query()->get(['id', 'title', 'meeting_date'])
            ->mapWithKeys(fn (Meeting $m) => [$m->title.'|'.$m->meeting_date->toDateString() => $m->id]);

        foreach ($this->demo('meeting-attendees') as $row) {
            $meetingId = $meetings[$row['meeting'].'|'.$row['meeting_date']] ?? null;
            $userId = $users[$row['user']] ?? null;
            if ($meetingId && $userId) {
                MeetingAttendee::query()->updateOrCreate(
                    ['meeting_id' => $meetingId, 'user_id' => $userId],
                    collect($row)->only(['type', 'rsvp_status', 'attendance_status', 'rsvp_date', 'decline_reason'])->all(),
                );
            }
        }

        foreach ($this->demo('meeting-minutes') as $row) {
            $meetingId = $meetings[$row['meeting'].'|'.$row['meeting_date']] ?? null;
            if ($meetingId) {
                MeetingMinute::query()->firstOrCreate(
                    ['meeting_id' => $meetingId, 'topic' => $row['topic']],
                    [...collect($row)->only(['content', 'type', 'recorded_at'])->all(), 'recorded_by' => $users[$row['recorder']] ?? $company],
                );
            }
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
