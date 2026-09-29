<?php

namespace Tests\Feature\System;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_a_successful_login_is_recorded()
    {
        $user = User::factory()->create();
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        $this->assertDatabaseCount('login_histories', 0);

        $this->withHeader('User-Agent', $iphone)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id, 'ip' => '203.0.113.9', 'user_agent' => $iphone, 'browser' => 'Safari', 'os' => 'iOS', 'device' => 'Mobile',
        ]);
    }

    public function test_user_agents_are_parsed()
    {
        $this->assertSame(
            ['browser' => 'Edge', 'os' => 'Windows', 'device' => 'Desktop'],
            LoginHistory::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0'),
        );
        $this->assertSame(
            ['browser' => 'Chrome', 'os' => 'Android', 'device' => 'Mobile'],
            LoginHistory::parse('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36'),
        );
        $this->assertSame(
            ['browser' => 'Firefox', 'os' => 'macOS', 'device' => 'Desktop'],
            LoginHistory::parse('Mozilla/5.0 (Macintosh; Intel Mac OS X 14.1; rv:121.0) Gecko/20100101 Firefox/121.0'),
        );
        $this->assertSame(['browser' => 'Unknown', 'os' => 'Unknown', 'device' => 'Unknown'], LoginHistory::parse('curl/8.4'));
    }

    public function test_company_sees_everyone_and_can_filter_by_user_and_date()
    {
        $company = $this->userWithRole('company');
        $other = User::factory()->create();
        LoginHistory::record($company, '10.0.0.1', 'Firefox/1');
        LoginHistory::record($other, '10.0.0.2', 'Firefox/1');
        LoginHistory::record($other, '10.0.0.3', 'Firefox/1')->update(['logged_in_at' => now()->subDays(3)]);

        $this->actingAs($company)
            ->get(route('login-history.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('login-history/index')->has('loginHistory.data', 3)->has('users', 2));

        $this->get(route('login-history.index', ['user_id' => $other->id, 'date' => today()->toDateString()]))
            ->assertInertia(fn ($page) => $page->has('loginHistory.data', 1)->where('loginHistory.data.0.ip', '10.0.0.2'));
        $this->get(route('login-history.index', ['search' => '10.0.0.3']))
            ->assertInertia(fn ($page) => $page->has('loginHistory.data', 1));
        $this->get(route('login-history.index', ['date' => 'yesterday']))->assertSessionHasErrors('date');
    }

    public function test_other_users_only_see_their_own_logins()
    {
        $employee = $this->userWithRole('employee');
        LoginHistory::record($employee, '10.0.0.1', null);
        $other = User::factory()->create();
        LoginHistory::record($other, '10.0.0.2', null);

        $this->actingAs($employee)
            ->get(route('login-history.index', ['user_id' => $other->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('loginHistory.data', 1)
                ->where('loginHistory.data.0.user_id', $employee->id)
                ->where('users', []));
    }

    public function test_records_older_than_90_days_are_pruned()
    {
        $user = User::factory()->create();
        $old = LoginHistory::record($user, null, null);
        $old->update(['logged_in_at' => now()->subDays(91)]);
        $recent = LoginHistory::record($user, null, null);

        $this->artisan('model:prune', ['--model' => [LoginHistory::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
