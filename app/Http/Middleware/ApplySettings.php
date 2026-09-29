<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySettings
{
    /**
     * Apply the company settings (name, timezone, default language, SMTP) to this request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = Setting::values();

        config([
            'app.name' => $settings['titleText'],
            'app.timezone' => $settings['defaultTimezone'],
        ]);
        date_default_timezone_set($settings['defaultTimezone']);
        app()->setLocale($settings['defaultLanguage']);

        if ($settings['mailHost'] !== '') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $settings['mailHost'],
                'mail.mailers.smtp.port' => $settings['mailPort'],
                'mail.mailers.smtp.username' => $settings['mailUsername'],
                'mail.mailers.smtp.password' => $settings['mailPassword'],
                'mail.mailers.smtp.scheme' => $settings['mailEncryption'] === 'ssl' ? 'smtps' : null,
                'mail.from.address' => $settings['mailFromAddress'],
                'mail.from.name' => $settings['mailFromName'],
            ]);
        }

        return $next($request);
    }
}
