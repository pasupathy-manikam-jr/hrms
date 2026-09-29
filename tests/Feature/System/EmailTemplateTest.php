<?php

namespace Tests\Feature\System;

use App\Models\EmailTemplate;
use Database\Seeders\Modules\SystemExtrasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(SystemExtrasSeeder::class);
    }

    public function test_seeded_templates_are_listed_and_searchable()
    {
        $this->actingAs($this->userWithRole())
            ->get(route('email-templates.index', ['search' => 'Award']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('email-templates/index')
                ->has('templates.data', 1)
                ->where('templates.data.0.name', 'New Award')
                ->where('templates.data.0.email_template_langs_count', 16)
                ->where('templates.data.0.email_template_langs.0.lang', 'en'));
    }

    public function test_editor_shows_languages_and_placeholders()
    {
        $template = EmailTemplate::where('name', 'New Award')->firstOrFail();

        $this->actingAs($this->userWithRole())
            ->get(route('email-templates.show', $template))
            ->assertInertia(fn ($page) => $page
                ->component('email-templates/show')
                ->has('template.email_template_langs', 16)
                ->has('languages', count(config('app.locales')))
                ->where('placeholders', ['award_type', 'employee_name', 'award_date', 'description', 'app_name']));
    }

    public function test_a_translation_can_be_updated_or_added()
    {
        $template = EmailTemplate::where('name', 'Test Email')->firstOrFail();
        $this->actingAs($this->userWithRole());

        $this->put(route('email-templates.update', $template), ['lang' => 'xx', 'subject' => '', 'content' => ''])
            ->assertSessionHasErrors(['lang', 'subject', 'content']);

        $this->put(route('email-templates.update', $template), ['lang' => 'ms', 'from' => 'HR', 'subject' => 'Ujian {app_name}', 'content' => '<p>Hai {email}</p>'])
            ->assertSessionHasNoErrors();

        $this->assertSame('HR', $template->fresh()->from);
        $this->assertSame(
            ['subject' => 'Ujian HRM & Co', 'content' => '<p>Hai a&lt;b@x.com</p>'],
            EmailTemplate::compose('Test Email', ['app_name' => 'HRM & Co', 'email' => 'a<b@x.com'], 'ms'),
        );
        // Languages without a translation fall back to English.
        $this->assertStringStartsWith('Test email from', EmailTemplate::compose('Test Email', [], 'zh')['subject']);
    }

    public function test_preview_fills_placeholders_with_escaped_sample_values()
    {
        $this->actingAs($this->userWithRole())
            ->postJson(route('email-templates.preview'), ['subject' => 'Hi {employee_name}', 'content' => '<p>{app_name}: {award_type}</p>'])
            ->assertOk()
            ->assertExactJson(['subject' => 'Hi [Employee Name]', 'content' => '<p>'.e(config('app.name')).': [Award Type]</p>']);
    }

    public function test_the_settings_test_email_uses_its_template()
    {
        $this->actingAs($this->userWithRole())
            ->post(route('settings.email.test'), ['email' => 'to@example.com'])
            ->assertSessionHasNoErrors();

        $message = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame('Test email from '.config('app.name'), $message->getSubject());
        $this->assertStringContainsString('sent to to@example.com', $message->getHtmlBody());
    }

    public function test_users_without_email_settings_permission_are_forbidden()
    {
        $template = EmailTemplate::firstOrFail();
        $this->actingAs($this->userWithRole('hr'));

        $this->get(route('email-templates.index'))->assertForbidden();
        $this->get(route('email-templates.show', $template))->assertForbidden();
        $this->put(route('email-templates.update', $template), ['lang' => 'en', 'subject' => 'x', 'content' => 'x'])->assertForbidden();
        $this->postJson(route('email-templates.preview'), ['content' => 'x'])->assertForbidden();
    }
}
