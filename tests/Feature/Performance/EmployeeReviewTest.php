<?php

namespace Tests\Feature\Performance;

use App\Models\Employee;
use App\Models\EmployeeReview;
use App\Models\PerformanceIndicator;
use App\Models\PerformanceIndicatorCategory;
use App\Models\ReviewCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_filtered()
    {
        EmployeeReview::factory()->count(2)->create();
        $review = EmployeeReview::factory()->create(['status' => 'completed', 'review_date' => '2026-05-10']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.performance.employee-reviews.index', ['status' => 'completed']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/performance/employee-reviews/index')
                ->has('reviews.data', 1)
                ->where('reviews.data.0.review_cycle.name', $review->reviewCycle->name)
                ->where('statusCounts', ['all' => 3, 'scheduled' => 2, 'in_progress' => 0, 'completed' => 1])
                ->has('employees', 3)
                ->has('reviewCycles', 3));

        $this->get(route('hr.performance.employee-reviews.index', ['date_from' => '2026-05-01', 'date_to' => '2026-05-31']))
            ->assertInertia(fn ($page) => $page->has('reviews.data', 1)->where('reviews.data.0.id', $review->id));

        $this->get(route('hr.performance.employee-reviews.index', ['search' => $review->employee->user->name]))
            ->assertInertia(fn ($page) => $page->has('reviews.data', 1));
    }

    public function test_overall_rating_is_computed_from_indicator_ratings_not_the_client()
    {
        $employee = Employee::factory()->create();
        $cycle = ReviewCycle::factory()->create();
        [$a, $b, $c] = PerformanceIndicator::factory()->count(3)->create();
        $this->actingAs($this->userWithRole('hr'));

        $payload = ['employee_id' => $employee->id, 'review_cycle_id' => $cycle->id, 'review_date' => '2026-04-01', 'status' => 'completed', 'overall_rating' => 1];

        $this->post(route('hr.performance.employee-reviews.store'), $payload)->assertSessionHasErrors('ratings');
        $this->post(route('hr.performance.employee-reviews.store'), [...$payload, 'ratings' => [
            ['performance_indicator_id' => $a->id, 'rating' => 6],
            ['performance_indicator_id' => $a->id, 'rating' => 4.25],
        ]])->assertSessionHasErrors(['ratings.0.rating', 'ratings.1.rating', 'ratings.0.performance_indicator_id']);

        $this->post(route('hr.performance.employee-reviews.store'), [...$payload, 'ratings' => [
            ['performance_indicator_id' => $a->id, 'rating' => 4, 'comments' => 'Solid'],
            ['performance_indicator_id' => $b->id, 'rating' => 3.5],
            ['performance_indicator_id' => $c->id, 'rating' => 4.5],
        ]])->assertSessionHasNoErrors();

        $review = EmployeeReview::sole();
        $this->assertSame(4.0, $review->overall_rating);
        $this->assertSame(now()->toDateString(), $review->completion_date?->toDateString());
        $this->assertSame('Solid', $review->ratings()->where('performance_indicator_id', $a->id)->value('comments'));

        // Dropping a rating and changing another recomputes the average; the client value is ignored again.
        $this->put(route('hr.performance.employee-reviews.update', $review), [...$payload, 'status' => 'in_progress', 'overall_rating' => 5, 'ratings' => [
            ['performance_indicator_id' => $a->id, 'rating' => 2],
            ['performance_indicator_id' => $b->id, 'rating' => 3.5],
        ]])->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame(2.75, $review->overall_rating);
        $this->assertNull($review->completion_date);
        $this->assertSame(2, $review->ratings()->count());

        $this->put(route('hr.performance.employee-reviews.update', $review), [...$payload, 'status' => 'scheduled', 'ratings' => []])->assertSessionHasNoErrors();
        $this->assertNull($review->fresh()->overall_rating);

        $this->delete(route('hr.performance.employee-reviews.destroy', $review));
        $this->assertModelMissing($review);
        $this->assertDatabaseCount('employee_review_ratings', 0);
    }

    public function test_employees_only_see_their_own_reviews_and_cannot_change_them()
    {
        $user = $this->userWithRole('employee');
        $mine = EmployeeReview::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        $other = EmployeeReview::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.performance.employee-reviews.index'))
            ->assertInertia(fn ($page) => $page
                ->has('reviews.data', 1)
                ->where('reviews.data.0.id', $mine->id)
                ->where('employees', [])
                ->where('reviewers', []));

        $this->get(route('hr.performance.employee-reviews.index', ['employee_id' => $other->employee_id]))
            ->assertInertia(fn ($page) => $page->has('reviews.data', 0));

        $this->post(route('hr.performance.employee-reviews.store'), [])->assertForbidden();
        $this->put(route('hr.performance.employee-reviews.update', $mine), [])->assertForbidden();
        $this->put(route('hr.performance.employee-reviews.change-status', $mine), ['status' => 'completed'])->assertForbidden();
        $this->get(route('hr.performance.employee-reviews.conduct', $mine))->assertForbidden();
        $this->get(route('hr.performance.employee-reviews.create'))->assertForbidden();
        $this->delete(route('hr.performance.employee-reviews.destroy', $other))->assertForbidden();
        $this->assertModelExists($other);
    }

    public function test_status_can_be_changed_by_editors()
    {
        $review = EmployeeReview::factory()->create(['status' => 'scheduled']);
        $this->actingAs($this->userWithRole('hr'));

        $this->put(route('hr.performance.employee-reviews.change-status', $review), ['status' => 'done'])->assertSessionHasErrors('status');
        $this->put(route('hr.performance.employee-reviews.change-status', $review), ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $review->fresh()->status);
    }

    public function test_schedule_page_then_conduct_page_completes_the_review()
    {
        $employee = Employee::factory()->create();
        $cycle = ReviewCycle::factory()->create();
        [$a, $b] = PerformanceIndicator::factory()->count(2)->create();
        PerformanceIndicator::factory()->create(['status' => 'inactive']);
        $this->actingAs($this->userWithRole('hr'));

        $this->get(route('hr.performance.employee-reviews.create'))
            ->assertInertia(fn ($page) => $page->component('hr/performance/employee-reviews/create')->has('reviewCycles', 1));

        $this->post(route('hr.performance.employee-reviews.store'), [
            'employee_id' => $employee->id, 'review_cycle_id' => $cycle->id, 'review_date' => '2026-04-01', 'status' => 'scheduled',
        ])->assertRedirect(route('hr.performance.employee-reviews.index'));
        $review = EmployeeReview::sole();

        $this->get(route('hr.performance.employee-reviews.conduct', $review))
            ->assertInertia(fn ($page) => $page->component('hr/performance/employee-reviews/conduct')->has('indicators', 2));

        $this->put(route('hr.performance.employee-reviews.submit-conduct', $review), ['ratings' => [
            ['performance_indicator_id' => $a->id, 'rating' => 7],
        ]])->assertSessionHasErrors('ratings.0.rating');

        $this->put(route('hr.performance.employee-reviews.submit-conduct', $review), [
            'comments' => 'Strong quarter',
            'ratings' => [
                ['performance_indicator_id' => $a->id, 'rating' => 4, 'comments' => 'On time'],
                ['performance_indicator_id' => $b->id, 'rating' => 3],
            ],
        ])->assertRedirect(route('hr.performance.employee-reviews.show', $review));

        $review->refresh();
        $this->assertSame(['completed', 3.5, 'Strong quarter'], [$review->status, $review->overall_rating, $review->comments]);

        // A completed review can't be conducted again.
        $this->get(route('hr.performance.employee-reviews.conduct', $review))->assertForbidden();
    }

    public function test_manage_own_editors_cannot_touch_other_employees_reviews()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-employee-reviews', 'manage-own-employee-reviews', 'delete-employee-reviews']);
        $other = EmployeeReview::factory()->create();

        $this->actingAs($user)->delete(route('hr.performance.employee-reviews.destroy', $other))->assertNotFound();
        $this->assertModelExists($other);
    }

    public function test_show_page_has_ratings_by_category_and_is_scoped()
    {
        $review = EmployeeReview::factory()->create(['status' => 'completed']);
        $category = PerformanceIndicatorCategory::factory()->create(['name' => 'Job Performance']);
        [$a, $b] = PerformanceIndicator::factory()->count(2)->create(['category_id' => $category->id]);
        $review->syncRatings([$a->id => ['rating' => 4], $b->id => ['rating' => 3, 'comments' => 'Solid']]);
        $this->withoutVite();

        $this->actingAs($this->userWithRole())
            ->get(route('hr.performance.employee-reviews.show', $review))
            ->assertInertia(fn ($page) => $page
                ->component('hr/performance/employee-reviews/show')
                ->where('review.id', $review->id)
                ->where('review.overall_rating', 3.5)
                ->has('review.ratings', 2)
                ->where('review.ratings.0.indicator.category.name', 'Job Performance')
                ->has('review.employee.user.name'));

        // Employees only see their own reviews.
        $user = $this->userWithRole('employee');
        $mine = EmployeeReview::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        $this->actingAs($user);
        $this->get(route('hr.performance.employee-reviews.show', $mine))->assertOk();
        $this->get(route('hr.performance.employee-reviews.show', $review))->assertNotFound();
    }
}
