<?php

namespace App\Http\Middleware;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'permissions' => fn () => $request->user()?->permissionNames() ?? [],
            ],
            'globalSettings' => fn () => $this->globalSettings(),
            'locale' => app()->getLocale(),
            'locales' => config('app.locales'),
            // Changes whenever a translation file changes, so browsers refetch it.
            'translationsVersion' => fn () => is_file($path = lang_path(app()->getLocale().'.json')) ? (string) filemtime($path) : '',
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Display settings every page needs (formats, branding); never mail credentials.
     *
     * @return array<string, mixed>
     */
    private function globalSettings(): array
    {
        $settings = Setting::values();

        return [
            ...Arr::only($settings, [
                'dateFormat', 'timeFormat', 'calendarStartDay', 'footerText', 'themeColor', 'customColor',
                'defaultCurrency', 'decimalFormat', 'decimalSeparator', 'thousandsSeparator',
                'currencySymbolPosition', 'currencySymbolSpace', 'workingDays',
            ]),
            'primaryColor' => Setting::primaryColor(),
            'currencySymbol' => Currency::query()->where('code', $settings['defaultCurrency'])->value('symbol') ?? $settings['defaultCurrency'],
        ];
    }
}
