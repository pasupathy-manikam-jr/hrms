<?php

namespace Tests\Feature;

use App\Models\IpRestriction;
use App\Models\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    public function test_only_settings_managers_can_open_settings()
    {
        $this->actingAs($this->userWithRole('company'))->get(route('settings'))->assertOk();
        $this->actingAs($this->userWithRole('hr'))->get(route('settings'))->assertForbidden();
    }

    public function test_system_settings_are_saved_with_their_types_and_applied()
    {
        $this->actingAs($this->userWithRole())
            ->put(route('settings.system'), [
                'defaultLanguage' => 'ms',
                'dateFormat' => 'd/m/Y',
                'timeFormat' => 'g:i A',
                'calendarStartDay' => 'monday',
                'defaultTimezone' => 'Asia/Kuching',
                'landingPageEnabled' => '0',
                'ipRestrictionEnabled' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Setting::get('landingPageEnabled'));
        $this->assertSame('d/m/Y', Setting::get('dateFormat'));

        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('globalSettings.dateFormat', 'd/m/Y')
            ->where('locale', 'ms'));
        $this->assertSame('Asia/Kuching', config('app.timezone'));
    }

    public function test_landing_page_can_be_disabled()
    {
        $this->get('/')->assertOk();

        Setting::put(['landingPageEnabled' => false]);

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_brand_settings_validate_and_change_the_primary_color()
    {
        $this->actingAs($this->userWithRole());

        $this->put(route('settings.brand'), ['titleText' => 'Acme HR', 'themeColor' => 'pink', 'customColor' => 'nope'])
            ->assertSessionHasErrors(['themeColor', 'customColor']);

        $this->put(route('settings.brand'), ['titleText' => 'Acme HR', 'footerText' => '© Acme', 'themeColor' => 'custom', 'customColor' => '#123456'])
            ->assertSessionHasNoErrors();

        $this->assertSame('#123456', Setting::primaryColor());
        $this->get(route('dashboard'))->assertSee('--primary: #123456', false)
            ->assertInertia(fn ($page) => $page->where('name', 'Acme HR'));
    }

    public function test_mail_password_is_encrypted_kept_when_blank_and_never_shared()
    {
        $this->actingAs($this->userWithRole());
        $email = ['mailHost' => 'smtp.example.com', 'mailPort' => 587, 'mailEncryption' => 'tls', 'mailFromAddress' => 'hr@example.com', 'mailFromName' => 'HR'];

        $this->put(route('settings.email'), [...$email, 'mailPassword' => 's3cret'])->assertSessionHasNoErrors();
        $this->put(route('settings.email'), [...$email, 'mailPassword' => ''])->assertSessionHasNoErrors();

        $this->assertSame('s3cret', Setting::get('mailPassword'));
        $this->assertStringNotContainsString('s3cret', (string) DB::table('settings')->where('key', 'mailPassword')->value('value'));
        $this->get(route('settings'))->assertDontSee('s3cret')
            ->assertInertia(fn ($page) => $page->missing('settings.mailPassword')->where('mailPasswordSet', true));
    }

    public function test_test_email_is_sent()
    {
        $this->actingAs($this->userWithRole())
            ->post(route('settings.email.test'), ['email' => 'to@example.com'])
            ->assertSessionHasNoErrors();

        // phpunit.xml uses the array mailer, which keeps sent messages in memory.
        $sent = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('to@example.com', $sent->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_working_days_are_saved()
    {
        $this->actingAs($this->userWithRole())
            ->put(route('settings.working-days'), ['workingDays' => [6, 1, 2]])
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 2, 6], Setting::get('workingDays'));
    }

    public function test_ip_restriction_blocks_other_addresses_but_not_settings_managers()
    {
        Setting::put(['ipRestrictionEnabled' => true]);
        IpRestriction::create(['ip_address' => '10.9.9.9']);

        $employee = $this->userWithRole('employee');
        $this->actingAs($employee)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($employee)->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->get(route('dashboard'))->assertOk();

        $this->actingAs($this->userWithRole('company'))->get(route('dashboard'))->assertOk();
    }

    public function test_ip_addresses_are_validated_added_and_removed()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('settings.ip-restrictions.store'), ['ip_address' => 'not-an-ip'])->assertSessionHasErrors('ip_address');
        $this->post(route('settings.ip-restrictions.store'), ['ip_address' => '8.8.8.8'])->assertSessionHasNoErrors();

        $ip = IpRestriction::where('ip_address', '8.8.8.8')->firstOrFail();
        $this->delete(route('settings.ip-restrictions.destroy', $ip))->assertRedirect();
        $this->assertModelMissing($ip);
    }
}
