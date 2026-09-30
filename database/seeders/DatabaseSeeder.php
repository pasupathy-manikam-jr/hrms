<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Module seeders in dependency order; each loads the WorkDo demo's records
     * from database/demo/*.json. Modules not built yet are skipped.
     *
     * @var list<class-string<Seeder>|string>
     */
    private const MODULES = [
        'Database\\Seeders\\Modules\\BranchSeeder',
        'Database\\Seeders\\Modules\\DepartmentSeeder',
        'Database\\Seeders\\Modules\\DesignationSeeder',
        'Database\\Seeders\\Modules\\EmployeeSeeder',
        'Database\\Seeders\\Modules\\OrgChartSeeder',
        'Database\\Seeders\\Modules\\ShiftSeeder',
        'Database\\Seeders\\Modules\\AttendanceSeeder',
        'Database\\Seeders\\Modules\\LeaveSeeder',
        'Database\\Seeders\\Modules\\AnnouncementSeeder',
        'Database\\Seeders\\Modules\\MeetingSeeder',
        'Database\\Seeders\\Modules\\RecruitmentSeeder',
        'Database\\Seeders\\Modules\\PayrollSeeder',
        'Database\\Seeders\\Modules\\AssetSeeder',
        'Database\\Seeders\\Modules\\OrganizationExtrasSeeder',
        'Database\\Seeders\\Modules\\LifecycleSeeder',
        'Database\\Seeders\\Modules\\LifecycleExitSeeder',
        'Database\\Seeders\\Modules\\PerformanceSeeder',
        'Database\\Seeders\\Modules\\TrainingSeeder',
        'Database\\Seeders\\Modules\\InterviewSeeder',
        'Database\\Seeders\\Modules\\OnboardingSeeder',
        'Database\\Seeders\\Modules\\DocumentSeeder',
        'Database\\Seeders\\Modules\\SystemUserSeeder',
        'Database\\Seeders\\Modules\\LandingContentSeeder',
        'Database\\Seeders\\Modules\\TimeTrackingSeeder',
        'Database\\Seeders\\Modules\\MediaSeeder',
        'Database\\Seeders\\Modules\\MeetingExtrasSeeder',
        'Database\\Seeders\\Modules\\SystemExtrasSeeder',
        'Database\\Seeders\\Modules\\BenchmarkSurveySeeder',
        // Must stay last: earlier seeders match people by the demo's original names and emails.
        'Database\\Seeders\\Modules\\MalaysianNamesSeeder',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([RolesSeeder::class, SettingsSeeder::class]);

        // Demo accounts shown on the login screen when APP_DEMO=true.
        foreach (['Company' => 'company', 'HR' => 'hr', 'Employee' => 'employee'] as $name => $role) {
            User::factory()->create([
                'name' => $name, // renamed by MalaysianNamesSeeder
                'email' => "{$role}@example.com",
                'password' => 'Zx123456',
            ])->assignRole($role);
        }

        $this->call(array_values(array_filter(self::MODULES, 'class_exists')));
    }
}
