<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_message_is_stored()
    {
        $this->post(route('contact.store'), [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'subject' => 'Demo',
            'message' => 'Hello',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', ['email' => 'jane@example.com', 'subject' => 'Demo']);
    }

    public function test_contact_message_requires_fields()
    {
        $this->post(route('contact.store'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_newsletter_subscription_is_idempotent()
    {
        $this->post(route('newsletter.store'), ['email' => 'a@example.com'])->assertRedirect();
        $this->post(route('newsletter.store'), ['email' => 'a@example.com'])->assertRedirect();

        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }
}
