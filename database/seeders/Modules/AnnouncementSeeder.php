<?php

namespace Database\Seeders\Modules;

use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\Concerns\AttachesSampleDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AnnouncementSeeder extends Seeder
{
    use AttachesSampleDocuments;

    /**
     * Seed the demo's announcements, targeting departments and branches by name, each with an
     * attachment and some of its audience having read it.
     */
    public function run(): void
    {
        $branches = Branch::query()->pluck('id', 'name');
        $author = User::query()->where('email', 'company@example.com')->value('id');

        /** @var list<array{departments: list<array{name: string, branch: string}>, branches: list<string>, title: string}> $rows */
        $rows = File::json(database_path('demo/announcements.json'), JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            ['departments' => $departments, 'branches' => $branchNames] = $row;
            unset($row['departments'], $row['branches']);

            $announcement = Announcement::query()->firstOrCreate(['title' => $row['title']], [...$row, 'created_by' => $author]);

            $departmentIds = [];
            foreach ($departments as $department) {
                $departmentIds[] = Department::query()
                    ->where('name', $department['name'])
                    ->where('branch_id', $branches[$department['branch']] ?? null)
                    ->value('id');
            }

            $announcement->departments()->sync(array_filter($departmentIds));
            $announcement->branches()->sync($branches->only($branchNames)->values());

            if ($announcement->file_path === null) {
                $this->attachSampleDocument($announcement, Announcement::UPLOAD_DIRECTORY, Str::slug($announcement->title).'.pdf', [
                    $announcement->title,
                    $announcement->category.'  ·  '.$announcement->start_date->toDateString(),
                    '',
                    (string) $announcement->description,
                ]);
            }

            // About two in five of the audience have opened it.
            $readers = $announcement->audience()->orderBy('id')->pluck('user_id');
            $announcement->viewers()->syncWithoutDetaching($readers->take((int) ceil($readers->count() * 0.4))->all());
        }
    }
}
