<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class AssetImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_streams_the_filtered_list()
    {
        Asset::factory()->create(['name' => 'Kept Laptop', 'asset_code' => 'KEEP1', 'condition' => 'new']);
        Asset::factory()->create(['name' => 'Other Chair', 'asset_code' => 'OTHER1', 'condition' => 'poor']);

        $csv = $this->actingAs($this->userWithRole())->get(route('hr.assets.export', ['condition' => 'new']))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".'Name,', $csv);
        $this->assertStringContainsString('Kept Laptop', $csv);
        $this->assertStringNotContainsString('Other Chair', $csv);
    }

    public function test_depreciation_report_exports_as_csv()
    {
        Asset::factory()->create(['name' => 'Old Printer']);

        $csv = $this->actingAs($this->userWithRole())->get(route('hr.assets.export-depreciation-csv'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Old Printer', $csv);
        $this->assertStringContainsString('Straight Line', $csv);
    }

    public function test_template_can_be_downloaded()
    {
        $this->actingAs($this->userWithRole())->get(route('hr.assets.download.template'))
            ->assertOk()->assertDownload('assets-import-template.csv');
    }

    public function test_import_creates_valid_rows_and_reports_bad_ones_by_row_number()
    {
        AssetType::factory()->create(['name' => 'Laptops']);
        Asset::factory()->create(['asset_code' => 'TAKEN']);

        $rows = implode("\n", [
            'Name,Asset Type,Serial Number,Asset Code,Purchase Date,Purchase Cost,Salvage Value,Useful Life Years,Status,Condition,Location,Description',
            'ThinkPad X1,laptops,SN1,LAP9,2025-02-01,5000,500,4,Available,New,HQ,',
            'Duplicate,Laptops,,TAKEN,2025-02-01,100,,,,,,',
            'No Type,Spaceships,,,2025-02-01,100,,,,,,',
        ]);

        $this->actingAs($this->userWithRole())
            ->post(route('hr.assets.import'), ['file' => UploadedFile::fake()->createWithContent('assets.csv', $rows)])
            ->assertRedirect();

        $asset = Asset::query()->where('asset_code', 'LAP9')->sole();
        $this->assertSame(['ThinkPad X1', 'Laptops', 'available', 'new', '500.00'], [$asset->name, $asset->assetType->name, $asset->status, $asset->condition, $asset->salvage_value]);
        $this->assertSame(2, Asset::query()->count());

        $report = session(SessionKey::FLASH_DATA)['import'];
        $this->assertSame(1, $report['imported']);
        $this->assertSame([3, 4], array_column($report['skipped'], 'row'));
        $this->assertStringContainsString('asset code', strtolower(implode(' ', $report['skipped'][0]['errors'])));
        $this->assertStringContainsString('asset type', strtolower(implode(' ', $report['skipped'][1]['errors'])));
    }

    public function test_import_and_export_need_permission()
    {
        $this->actingAs($this->userWithRole('hr'));
        $this->get(route('hr.assets.export'))->assertForbidden();
        $this->post(route('hr.assets.import'), ['file' => UploadedFile::fake()->createWithContent('assets.csv', "Name\nX")])->assertForbidden();
    }
}
