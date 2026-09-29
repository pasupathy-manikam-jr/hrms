<?php

namespace Tests\Feature\Hr;

use App\Models\AssetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_sorted()
    {
        AssetType::factory()->count(3)->create();
        AssetType::factory()->create(['name' => 'zzz Zeppelins']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.asset-types.index', ['search' => 'Zeppelin']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/asset-types/index')
                ->has('assetTypes.data', 1)
                ->where('assetTypes.data.0.name', 'zzz Zeppelins'));

        $this->get(route('hr.asset-types.index', ['sort_field' => 'name', 'sort_direction' => 'desc']))
            ->assertInertia(fn ($page) => $page->has('assetTypes.data', 4)->where('assetTypes.data.0.name', 'zzz Zeppelins'));
    }

    public function test_asset_types_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.asset-types.store'), ['name' => ''])->assertSessionHasErrors(['name']);
        $this->post(route('hr.asset-types.store'), ['name' => 'Tools', 'description' => 'Hand tools'])->assertSessionHasNoErrors();

        $assetType = AssetType::where('name', 'Tools')->firstOrFail();

        $this->put(route('hr.asset-types.update', $assetType), ['name' => 'Power Tools'])->assertSessionHasNoErrors();
        $this->assertSame('Power Tools', $assetType->fresh()->name);

        $this->delete(route('hr.asset-types.destroy', $assetType));
        $this->assertModelMissing($assetType);
    }

    public function test_employees_can_view_but_not_change_asset_types()
    {
        $assetType = AssetType::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.asset-types.index'))->assertOk();
        $this->post(route('hr.asset-types.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.asset-types.update', $assetType), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.asset-types.destroy', $assetType))->assertForbidden();
        $this->assertModelExists($assetType);
    }
}
