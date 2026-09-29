<?php

namespace Database\Seeders\Modules;

use App\Models\EmployeeGoal;
use App\Models\EmployeeReview;
use App\Models\GoalType;
use App\Models\PerformanceIndicator;
use App\Models\PerformanceIndicatorCategory;
use App\Models\ReviewCycle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class PerformanceSeeder extends Seeder
{
    /**
     * Seed the demo's indicators, goal types, review cycles, goals and reviews.
     */
    public function run(): void
    {
        $demo = File::json(database_path('demo/performance.json'), JSON_THROW_ON_ERROR);
        $owner = User::query()->where('email', 'company@example.com')->value('id');

        foreach (['indicator_categories' => PerformanceIndicatorCategory::class, 'goal_types' => GoalType::class, 'review_cycles' => ReviewCycle::class] as $key => $model) {
            foreach ($demo[$key] as $row) {
                $model::query()->firstOrCreate(['name' => $row['name']], $row + ['created_by' => $owner]);
            }
        }

        $categories = PerformanceIndicatorCategory::query()->pluck('id', 'name');

        foreach ($demo['indicators'] as $row) {
            PerformanceIndicator::query()->firstOrCreate(['name' => $row['name']], [
                ...Arr::except($row, 'category'),
                'category_id' => $categories[$row['category']],
                'created_by' => $owner,
            ]);
        }

        // Employees are matched by their user's email; the demo's "Manager" reviewer maps to our HR account.
        $employees = User::query()->join('employees', 'employees.user_id', '=', 'users.id')->pluck('employees.id', 'users.email');
        $users = User::query()->pluck('id', 'email');
        $goalTypes = GoalType::query()->pluck('id', 'name');
        $cycles = ReviewCycle::query()->pluck('id', 'name');
        $indicators = PerformanceIndicator::query()->pluck('id', 'name');

        foreach ($demo['employee_goals'] as $row) {
            if (! isset($employees[$row['employee']])) {
                continue;
            }

            EmployeeGoal::query()->firstOrCreate(['employee_id' => $employees[$row['employee']], 'title' => $row['title']], [
                ...Arr::except($row, ['employee', 'goal_type']),
                'goal_type_id' => $goalTypes[$row['goal_type']],
                'created_by' => $owner,
            ]);
        }

        foreach ($demo['employee_reviews'] as $row) {
            if (! isset($employees[$row['employee']])) {
                continue;
            }

            $review = EmployeeReview::query()->firstOrCreate([
                'employee_id' => $employees[$row['employee']],
                'review_cycle_id' => $cycles[$row['review_cycle']],
                'review_date' => $row['review_date'],
            ], [
                ...Arr::except($row, ['employee', 'reviewer', 'review_cycle', 'ratings']),
                'reviewer_id' => $users[$row['reviewer']] ?? null,
                'created_by' => $owner,
            ]);

            if ($review->wasRecentlyCreated) {
                $ratings = [];

                foreach ($row['ratings'] as $rating) {
                    $ratings[$indicators[$rating['indicator']]] = ['rating' => $rating['rating'], 'comments' => $rating['comments']];
                }

                $review->syncRatings($ratings);
            }
        }
    }
}
