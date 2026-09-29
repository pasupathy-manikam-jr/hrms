<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Setting;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(Request $request): Response
    {
        $default = Setting::get('defaultCurrency');
        $currencies = TableQuery::paginate(Currency::query(), $request, ['name', 'code', 'symbol'], ['name', 'code', 'symbol', 'created_at']);

        foreach ($currencies->items() as $currency) {
            $currency->setAttribute('is_default', $currency->code === $default);
        }

        return Inertia::render('currencies/index', [
            'currencies' => $currencies,
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Currency::create($this->validated($request));

        return $this->done(__('Currency created successfully.'));
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $data = $this->validated($request, $currency);

        if ($currency->code === Setting::get('defaultCurrency') && $data['code'] !== $currency->code) {
            throw ValidationException::withMessages(['code' => __('The default currency code cannot be changed.')]);
        }

        $currency->update($data);

        return $this->done(__('Currency updated successfully.'));
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->code === Setting::get('defaultCurrency')) {
            return $this->toast('error', __('The default currency cannot be deleted.'));
        }

        $currency->delete();

        return $this->done(__('Currency deleted successfully.'));
    }

    /**
     * @return array{name: string, code: string, symbol: string, description: string|null}
     */
    private function validated(Request $request, ?Currency $currency = null): array
    {
        $request->merge(['code' => strtoupper($request->string('code')->toString())]);

        /** @var array{name: string, code: string, symbol: string, description: string|null} */
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha', 'size:3', Rule::unique('currencies')->ignore($currency)],
            'symbol' => ['required', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
