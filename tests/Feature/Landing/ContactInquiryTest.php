<?php

namespace Tests\Feature\Landing;

use App\Models\ContactMessage;
use Database\Seeders\Modules\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ContactInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inquiries_can_be_listed_searched_and_filtered(): void
    {
        $this->seed(LandingContentSeeder::class);
        $this->seed(LandingContentSeeder::class);
        $this->assertDatabaseCount('contact_messages', 8);

        $this->actingAs($this->userWithRole())
            ->get(route('contacts.index', ['search' => 'Demo Request']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('contacts/index')
                ->has('contacts.data', 1)
                ->where('contacts.data.0.status', 'contacted')
                ->where('statuses', ContactMessage::STATUSES));

        $this->get(route('contacts.index', ['status' => 'new']))
            ->assertInertia(fn ($page) => $page->has('contacts.data', 3)->where('filters.status', 'new'));
    }

    public function test_messages_from_the_landing_page_start_as_new(): void
    {
        $this->post(route('contact.store'), ['name' => 'Jane', 'email' => 'jane@example.com', 'subject' => 'Hi', 'message' => 'Hello']);

        $this->assertSame('new', ContactMessage::firstOrFail()->status);
    }

    public function test_status_can_be_updated_and_inquiries_deleted(): void
    {
        $contact = ContactMessage::create(['name' => 'Jane', 'email' => 'jane@example.com', 'subject' => 'Hi', 'message' => 'Hello']);
        $this->actingAs($this->userWithRole());

        $this->put(route('contacts.update-status', $contact), ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->put(route('contacts.update-status', $contact), ['status' => 'qualified'])->assertSessionHasNoErrors();
        $this->assertSame('qualified', $contact->fresh()->status);

        $this->delete(route('contacts.destroy', $contact));
        $this->assertModelMissing($contact);
    }

    public function test_reply_emails_the_sender_and_marks_the_message_contacted(): void
    {
        Event::fake([MessageSent::class]);
        $contact = ContactMessage::create(['name' => 'Jane', 'email' => 'jane@example.com', 'subject' => 'Hi', 'message' => 'Hello']);
        $this->actingAs($this->userWithRole());

        $this->post(route('contacts.reply', $contact), [])->assertSessionHasErrors(['subject', 'message']);
        $this->post(route('contacts.reply', $contact), ['subject' => 'Re: Hi', 'message' => 'Thanks for writing.'])->assertSessionHasNoErrors();

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->getTo()[0]->getAddress() === 'jane@example.com');
        $this->assertSame('contacted', $contact->fresh()->status);
    }

    public function test_employees_cannot_manage_inquiries(): void
    {
        $contact = ContactMessage::create(['name' => 'Jane', 'email' => 'jane@example.com', 'subject' => 'Hi', 'message' => 'Hello']);
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('contacts.index'))->assertForbidden();
        $this->put(route('contacts.update-status', $contact), ['status' => 'closed'])->assertForbidden();
        $this->post(route('contacts.reply', $contact), ['subject' => 'x', 'message' => 'y'])->assertForbidden();
        $this->delete(route('contacts.destroy', $contact))->assertForbidden();
        $this->assertModelExists($contact);
    }
}
