<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\EmailTemplate;
use App\Models\IpRestriction;
use App\Models\Setting;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CompanySettingsController extends Controller
{
    /** PHP date formats offered in System Settings. */
    private const DATE_FORMATS = ['Y-m-d', 'd-m-Y', 'm-d-Y', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'd.m.Y', 'M j, Y', 'd M, Y', 'F j, Y', 'j F Y', 'D, M j, Y'];

    private const TIME_FORMATS = ['H:i', 'H:i:s', 'G:i', 'g:i A', 'g:i a', 'h:i A', 'h:i a'];

    public function show(): Response
    {
        $now = now();

        return Inertia::render('company-settings', [
            'settings' => Setting::public(),
            'mailPasswordSet' => Setting::get('mailPassword') !== '',
            'currencies' => Currency::query()->orderBy('name')->get(['code', 'name', 'symbol']),
            'ipRestrictions' => IpRestriction::query()->latest()->get(['id', 'ip_address']),
            'timezones' => DateTimeZone::listIdentifiers(),
            'dateFormats' => collect(self::DATE_FORMATS)->mapWithKeys(fn ($f) => [$f => $now->format($f)]),
            'timeFormats' => collect(self::TIME_FORMATS)->mapWithKeys(fn ($f) => [$f => $now->format($f)]),
        ]);
    }

    public function updateSystem(Request $request): RedirectResponse
    {
        return $this->save($request->validate([
            'defaultLanguage' => ['required', Rule::in(array_keys(config('app.locales')))],
            'dateFormat' => ['required', Rule::in(self::DATE_FORMATS)],
            'timeFormat' => ['required', Rule::in(self::TIME_FORMATS)],
            'calendarStartDay' => ['required', Rule::in(['sunday', 'monday'])],
            'defaultTimezone' => ['required', 'timezone:all'],
            'landingPageEnabled' => ['required', 'boolean'],
            'ipRestrictionEnabled' => ['required', 'boolean'],
        ]), 'System settings saved.');
    }

    public function updateBrand(Request $request): RedirectResponse
    {
        return $this->save($request->validate([
            'titleText' => ['required', 'string', 'max:60'],
            'footerText' => ['nullable', 'string', 'max:255'],
            'themeColor' => ['required', Rule::in([...array_keys(Setting::THEME_COLORS), 'custom'])],
            'customColor' => ['required', 'hex_color'],
        ]), 'Brand settings saved.');
    }

    public function updateCurrency(Request $request): RedirectResponse
    {
        return $this->save($request->validate([
            'defaultCurrency' => ['required', Rule::exists(Currency::class, 'code')],
            'decimalFormat' => ['required', 'integer', 'between:0,4'],
            'decimalSeparator' => ['required', Rule::in(['.', ','])],
            'thousandsSeparator' => ['required', Rule::in([',', '.', ' ', ''])],
            'currencySymbolPosition' => ['required', Rule::in(['before', 'after'])],
            'currencySymbolSpace' => ['required', 'boolean'],
        ]), 'Currency settings saved.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mailHost' => ['required', 'string', 'max:255'],
            'mailPort' => ['required', 'integer', 'between:1,65535'],
            'mailUsername' => ['nullable', 'string', 'max:255'],
            // Left blank to keep the saved password.
            'mailPassword' => ['nullable', 'string', 'max:255'],
            'mailEncryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'mailFromAddress' => ['required', 'email', 'max:255'],
            'mailFromName' => ['required', 'string', 'max:255'],
        ]);

        if (($validated['mailPassword'] ?? '') === '') {
            unset($validated['mailPassword']);
        }

        return $this->save(array_map(fn ($value) => $value ?? '', $validated), 'Email settings saved.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $to = $request->validate(['email' => ['required', 'email']])['email'];

        $mail = EmailTemplate::compose('Test Email', ['app_name' => config('app.name'), 'app_url' => url('/'), 'email' => $to]);

        try {
            if ($mail) {
                Mail::html($mail['content'], fn ($message) => $message->to($to)->subject($mail['subject']));
            } else {
                Mail::raw(__('This is a test email from :app.', ['app' => config('app.name')]), fn ($message) => $message
                    ->to($to)
                    ->subject(__('Test email')));
            }
        } catch (Throwable $e) {
            return $this->toast('error', __('Could not send the test email: :error', ['error' => $e->getMessage()]));
        }

        return $this->toast('success', __('Test email sent to :email.', ['email' => $to]));
    }

    public function updateWorkingDays(Request $request): RedirectResponse
    {
        $request->validate([
            'workingDays' => ['present', 'array'],
            'workingDays.*' => ['integer', 'between:0,6', 'distinct'],
        ]);

        $days = $request->collect('workingDays')->map(fn ($day) => (int) $day)->sort()->values()->all();

        return $this->save(['workingDays' => $days], 'Working days saved.');
    }

    public function storeIpRestriction(Request $request): RedirectResponse
    {
        IpRestriction::create($request->validate([
            'ip_address' => ['required', 'ip', Rule::unique(IpRestriction::class)],
        ]));

        return $this->toast('success', __('IP address added.'));
    }

    public function destroyIpRestriction(IpRestriction $ipRestriction): RedirectResponse
    {
        $ipRestriction->delete();

        return $this->toast('success', __('IP address removed.'));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function save(array $values, string $message): RedirectResponse
    {
        Setting::put($values);

        return $this->done(__($message));
    }
}
