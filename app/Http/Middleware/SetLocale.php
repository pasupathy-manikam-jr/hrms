<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Use the signed-in user's language, else the guest's session choice.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()->lang ?? $request->session()->get('locale');

        if (is_string($locale) && array_key_exists($locale, config('app.locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
