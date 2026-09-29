<?php

namespace App\Http\Controllers;

use App\Models\AssetType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetTypeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('hr/asset-types/index', [
            'assetTypes' => TableQuery::paginate(AssetType::query()->withCount('assets'), $request, ['name', 'description'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AssetType::create($this->validated($request));

        return $this->done(__('Asset type created successfully.'));
    }

    public function update(Request $request, AssetType $assetType): RedirectResponse
    {
        $assetType->update($this->validated($request));

        return $this->done(__('Asset type updated successfully.'));
    }

    public function destroy(AssetType $assetType): RedirectResponse
    {
        $assetType->delete();

        return $this->done(__('Asset type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
