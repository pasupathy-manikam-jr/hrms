<?php

namespace Tests\Feature\Landing;

use App\Models\LandingPageSetting;
use Database\Seeders\Modules\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders_the_seeded_content(): void
    {
        $this->seed(LandingContentSeeder::class);
        $this->seed(LandingContentSeeder::class); // idempotent

        $this->assertDatabaseCount('landing_page_settings', 1);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('welcome')
                ->where('landing.sections.hero.title', 'Simplify HR Management Effortlessly')
                ->has('landing.sections.features.features_list', 6)
                ->has('landing.sections.faq.faqs', 7)
                ->has('landing.sections.testimonials.testimonials', 6)
                ->has('landing.sections.team.members', 4)
                ->where('landing.section_order.0', 'hero')
                ->where('landing.section_visibility.footer', true)
                ->has('customPages', 6));
    }

    public function test_welcome_page_falls_back_to_defaults_before_anything_is_saved(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn ($page) => $page
                ->where('landing.sections.contact.email', 'support@hrm.com.my')
                ->has('customPages', 0));
    }

    public function test_settings_can_be_saved_reordered_and_hidden(): void
    {
        $this->actingAs($this->userWithRole());

        $this->get(route('landing-page.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('landing-page/settings')
                ->where('settings.sections.hero.title', 'Simplify HR Management Effortlessly')
                ->has('icons'));

        $data = LandingPageSetting::defaults();
        $data['sections']['hero']['title'] = '<script>alert(1)</script> Hello';
        $data['sections']['faq']['faqs'] = [['question' => 'Q?', 'answer' => 'A.', 'extra' => 'dropped']];
        $data['sections']['footer']['injected'] = 'dropped';
        $data['section_order'] = array_reverse($data['section_order']);
        $data['section_visibility']['team'] = false;

        $this->post(route('landing-page.settings.update'), $data)->assertSessionHasNoErrors();

        $saved = LandingPageSetting::content();
        $this->assertSame('<script>alert(1)</script> Hello', $saved['sections']['hero']['title']);
        $this->assertSame([['question' => 'Q?', 'answer' => 'A.']], $saved['sections']['faq']['faqs']);
        $this->assertArrayNotHasKey('injected', $saved['sections']['footer']);
        $this->assertSame('contact', $saved['section_order'][0]);
        $this->assertFalse($saved['section_visibility']['team']);

        // Stored as text: the public page ships it inside escaped JSON, never as markup.
        $this->get(route('home'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertInertia(fn ($page) => $page->where('landing.sections.hero.title', '<script>alert(1)</script> Hello'));
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs($this->userWithRole());

        $data = LandingPageSetting::defaults();
        $data['section_order'] = ['hero', 'hero', 'bogus'];
        unset($data['section_visibility']['footer']);
        $data['sections']['features']['features_list'][0]['icon'] = 'skull';
        $data['sections']['team']['members'][0]['email'] = 'not-an-email';
        $data['sections']['hero']['title'] = str_repeat('x', 2001);
        $data['sections']['faq']['faqs'][0]['question'] = ['array'];

        $this->post(route('landing-page.settings.update'), $data)->assertSessionHasErrors([
            'section_order',
            'section_order.1',
            'section_order.2',
            'section_visibility',
            'sections.features.features_list.0.icon',
            'sections.team.members.0.email',
            'sections.hero.title',
            'sections.faq.faqs.0.question',
        ]);

        $this->assertDatabaseCount('landing_page_settings', 0);
    }

    public function test_employees_cannot_manage_the_landing_page(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('landing-page.settings'))->assertForbidden();
        $this->post(route('landing-page.settings.update'), LandingPageSetting::defaults())->assertForbidden();
        $this->assertDatabaseCount('landing_page_settings', 0);
    }
}
