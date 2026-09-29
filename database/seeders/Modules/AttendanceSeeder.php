<?php

namespace Database\Seeders\Modules;

use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class AttendanceSeeder extends Seeder
{
    /**
     * Seed the demo's attendance policies, employee shift assignments and two months of attendance records.
     */
    public function run(): void
    {
        foreach (File::json(database_path('demo/attendance-policies.json'), JSON_THROW_ON_ERROR) as $policy) {
            AttendancePolicy::query()->firstOrCreate(['name' => $policy['name']], $policy);
        }

        $shifts = Shift::query()->pluck('id', 'name');
        $employees = Employee::query()->pluck('id', 'employee_id');

        foreach (File::json(database_path('demo/employee-shifts.json'), JSON_THROW_ON_ERROR) as $code => $shift) {
            Employee::query()->where('employee_id', $code)->whereNull('shift_id')->update(['shift_id' => $shifts[$shift] ?? null]);
        }

        // Records dated after today would block clocking in on those days, so leave them out.
        $today = AttendanceRecord::now()->toDateString();
        $rows = collect(File::json(database_path('demo/attendance-records.json'), JSON_THROW_ON_ERROR))
            ->filter(fn (array $row) => isset($employees[$row['employee']]) && $row['date'] <= $today)
            ->map(fn (array $row) => [...Arr::except($row, 'employee'), 'employee_id' => $employees[$row['employee']]])
            ->values();

        foreach ($rows->chunk(200) as $chunk) {
            AttendanceRecord::query()->upsert($chunk->all(), ['employee_id', 'date'], []);
        }
    }
}
