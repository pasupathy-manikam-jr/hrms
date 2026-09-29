<?php

namespace App\Http\Middleware;

use App\Models\IpRestriction;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictIp
{
    /**
     * When IP restriction is on, signed-in users (other than settings managers,
     * so they can't lock themselves out) must come from an allowed address.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && Setting::get('ipRestrictionEnabled')
            && ! $request->routeIs('logout')
            && $user->cannot('manage-settings')
            && IpRestriction::query()->where('ip_address', $request->ip())->doesntExist()
        ) {
            abort(403, __('Access from your IP address is not allowed.'));
        }

        return $next($request);
    }
}
