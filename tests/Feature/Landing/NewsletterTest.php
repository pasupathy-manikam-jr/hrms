<?php

namespace Tests\Feature\Landing;

use App\Models\NewsletterSubscriber;
use Database\Seeders\Modules\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribers_can_be_listed_and_searched(): void
    {
        $this->seed(LandingContentSeeder::class);
        $this->seed(LandingContentSeeder::class);
        $this->assertDatabaseCount('newsletter_subscribers', 20);

        $this->actingAs($this->userWithRole())
            ->get(route('newsletters.index', ['search' => 'azman.yusof']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('newsletters/index')
                ->has('newsletters.data', 1)
                ->where('newsletters.data.0.email', 'azman.yusof@example.com.my'));
    }

    public function test_subscribers_can_be_deleted(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'a@example.com']);
        $this->actingAs($this->userWithRole());

        $this->delete(route('newsletters.destroy', $subscriber));
        $this->assertModelMissing($subscriber);
    }

    public function test_employees_cannot_manage_subscribers(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'a@example.com']);
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('newsletters.index'))->assertForbidden();
        $this->delete(route('newsletters.destroy', $subscriber))->assertForbidden();
        $this->assertModelExists($subscriber);
    }
}
