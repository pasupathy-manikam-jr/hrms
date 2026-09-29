<?php

namespace Tests\Feature\System;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_currencies_are_listed_with_the_default_flagged()
    {
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$']);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€']);
        Setting::put(['defaultCurrency' => 'EUR']);

        $this->actingAs($this->userWithRole())
            ->get(route('currencies.index', ['search' => 'EUR']))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('currencies/index')
                ->has('currencies.data', 1)
                ->where('currencies.data.0.is_default', true));
    }

    public function test_currencies_can_be_created_updated_and_deleted()
    {
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$']);
        $this->actingAs($this->userWithRole());

        $this->post(route('currencies.store'), ['name' => '', 'code' => 'usd', 'symbol' => ''])->assertSessionHasErrors(['name', 'code', 'symbol']);
        $this->post(route('currencies.store'), ['name' => 'Kenyan Shilling', 'code' => 'kes', 'symbol' => 'KSh'])->assertSessionHasNoErrors();

        $currency = Currency::where('code', 'KES')->firstOrFail();

        $this->put(route('currencies.update', $currency), ['name' => 'Kenya Shilling', 'code' => 'KES', 'symbol' => 'KSh', 'description' => 'East Africa'])->assertSessionHasNoErrors();
        $this->assertSame('East Africa', $currency->fresh()->description);

        $this->delete(route('currencies.destroy', $currency));
        $this->assertModelMissing($currency);
    }

    public function test_the_default_currency_cannot_be_deleted_or_recoded()
    {
        $default = Currency::create(['name' => 'Malaysian Ringgit', 'code' => 'MYR', 'symbol' => 'RM']);
        $this->assertSame('MYR', Setting::get('defaultCurrency'));
        $this->actingAs($this->userWithRole());

        $this->delete(route('currencies.destroy', $default))
            ->assertSessionHas('inertia.flash_data.toast.message', 'The default currency cannot be deleted.');
        $this->assertModelExists($default);

        $this->put(route('currencies.update', $default), ['name' => 'Ringgit', 'code' => 'MYX', 'symbol' => 'RM'])->assertSessionHasErrors('code');
        $this->assertSame('MYR', $default->fresh()->code);
    }

    public function test_hr_and_employees_cannot_manage_currencies()
    {
        $currency = Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€']);

        foreach (['hr', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('currencies.index'))->assertForbidden();
            $this->post(route('currencies.store'), ['name' => 'X'])->assertForbidden();
            $this->put(route('currencies.update', $currency), ['name' => 'X'])->assertForbidden();
            $this->delete(route('currencies.destroy', $currency))->assertForbidden();
        }

        $this->assertModelExists($currency);
    }
}
