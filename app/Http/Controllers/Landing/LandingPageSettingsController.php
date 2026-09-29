<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\LandingPageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageSettingsController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('landing-page/settings', [
            'settings' => LandingPageSetting::content(),
            'icons' => LandingPageSetting::ICONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        LandingPageSetting::store(LandingPageSetting::normalize($request->validate(LandingPageSetting::rules())));

        return $this->done(__('Landing page settings saved successfully.'));
    }
}
