<?php

namespace Database\Seeders\Modules;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class EmployeeSeeder extends Seeder
{
    /**
     * Seed the demo's employees: a users row (role employee, password Zx123456) plus its profile.
     * The demo's "Employee" person becomes employee@example.com's profile.
     */
    public function run(): void
    {
        foreach (File::json(database_path('demo/employees.json'), JSON_THROW_ON_ERROR) as $row) {
            $branch = Branch::query()->where('name', $row['branch'])->first();
            $department = $branch ? Department::query()->where(['branch_id' => $branch->id, 'name' => $row['department']])->first() : null;

            if (! $department) {
                continue;
            }

            // A few demo employees hold a designation from another branch's department; keep ours consistent.
            $designation = Designation::query()->firstOrCreate(
                ['department_id' => $department->id, 'name' => $row['designation']],
                ['status' => 'active'],
            );

            $user = User::query()->firstOrCreate(['email' => $row['email']], ['name' => $row['name'], 'password' => 'Zx123456']);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

            if (! $user->hasRole('employee')) {
                $user->assignRole('employee');
            }

            $employee = Employee::query()->firstOrCreate(['user_id' => $user->id], [
                ...Arr::except($row, ['name', 'email', 'branch', 'department', 'designation']),
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'designation_id' => $designation->id,
            ]);

            // Employees seeded before MyKad numbers existed pick theirs up.
            if ($employee->id_number === null) {
                $employee->update(Arr::only($row, ['id_type', 'id_number']));
            }
        }
    }
}
