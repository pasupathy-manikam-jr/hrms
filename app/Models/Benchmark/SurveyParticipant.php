<?php

namespace App\Models\Benchmark;

use App\Support\Benchmark\SurveyWorkbook;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One company's completed workbook for a cycle. Its answers are kept separately per company and only
 * pooled (never shown individually) in the analytics.
 *
 * @property int $id
 * @property int $survey_cycle_id
 * @property string $company_name
 * @property string $company_key
 * @property string $industry
 * @property string $state
 * @property string $employee_band
 * @property string|null $file_path
 * @property string|null $file_name
 * @property list<string>|null $warnings
 * @property CarbonInterface|null $consent_date
 */
#[Fillable([
    'survey_cycle_id', 'company_name', 'company_key', 'industry', 'state', 'employee_band', 'revenue_band',
    'ownership_type', 'locations', 'listed_status', 'unionised', 'authorised_name', 'authorised_designation',
    'consent_date', 'file_path', 'file_name', 'warnings', 'uploaded_by',
])]
class SurveyParticipant extends Model
{
    public const UPLOAD_DIRECTORY = 'benchmark/submissions';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    protected static function booted(): void
    {
        static::deleted(fn (self $participant) => $participant->file_path && Storage::disk('local')->delete($participant->file_path));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['warnings' => 'array', 'consent_date' => 'date:Y-m-d', 'locations' => 'integer'];
    }

    /**
     * The name used to recognise the same company across uploads: lower case, without legal suffixes.
     */
    public static function keyFor(string $companyName): string
    {
        $name = Str::of($companyName)->lower()
            ->replaceMatches('/\((m|malaysia)\)/', ' ')
            ->replaceMatches('/\b(sdn\.?\s*bhd\.?|berhad|bhd\.?|plc|ltd\.?|limited|inc\.?)\b/', ' ')
            ->replaceMatches('/[^a-z0-9]+/', ' ');

        return trim((string) $name);
    }

    /**
     * Reads, checks and saves completed workbooks into the cycle. Files with errors are rejected; a company
     * already in the cycle is skipped unless $replace. Returns one report entry per file.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{file: string, company: string|null, status: string, errors: list<string>, warnings: list<string>}>
     */
    public static function importWorkbooks(SurveyCycle $cycle, array $files, bool $replace, ?int $userId): array
    {
        $catalogue = $cycle->jobs()->get()->keyBy(fn (BenchmarkJob $job) => mb_strtolower($job->title))->map->toArray()->all();
        $reader = new SurveyWorkbook;
        $report = [];

        foreach ($files as $file) {
            $data = $reader->read($file->getRealPath(), $cycle, $catalogue);
            $company = $data['participant']['company_name'] ?? null;
            $entry = ['file' => $file->getClientOriginalName(), 'company' => $company, 'errors' => $data['errors'], 'warnings' => $data['warnings']];

            if ($data['errors'] !== []) {
                $report[] = [...$entry, 'status' => 'rejected'];

                continue;
            }

            $exists = self::query()->where('survey_cycle_id', $cycle->id)->where('company_key', self::keyFor((string) $company))->exists();

            if ($exists && ! $replace) {
                $report[] = [...$entry, 'status' => 'skipped', 'errors' => [__('This company has already been uploaded to this cycle. Tick "Replace existing submissions" to overwrite it.')]];

                continue;
            }

            self::record($cycle, $data, [
                'file_path' => $file->store(self::UPLOAD_DIRECTORY, 'local') ?: throw new \RuntimeException('Could not store the workbook.'),
                'file_name' => $file->getClientOriginalName(),
                'uploaded_by' => $userId,
            ]);
            $report[] = [...$entry, 'status' => $exists ? 'replaced' : 'imported'];
        }

        return $report;
    }

    /**
     * Saves one company's answers (as read by SurveyWorkbook) into the cycle, replacing that company's
     * previous submission if there is one.
     *
     * @param  array{participant: array<string, mixed>, salaryRows: array<int, array<string, mixed>>, benefits: array<int, array<string, mixed>>, attrition: array<string, mixed>, warnings: array<int, string>}  $data
     * @param  array{file_path?: string|null, file_name?: string|null, uploaded_by?: int|null}  $file
     */
    public static function record(SurveyCycle $cycle, array $data, array $file = []): self
    {
        return DB::transaction(function () use ($cycle, $data, $file) {
            $key = self::keyFor((string) $data['participant']['company_name']);
            $previous = self::query()->where('survey_cycle_id', $cycle->id)->where('company_key', $key)->first();
            $previous?->delete();

            $participant = self::create([
                ...$data['participant'],
                ...$file,
                'survey_cycle_id' => $cycle->id,
                'company_key' => $key,
                'warnings' => $data['warnings'],
            ]);

            $participant->salaryRows()->createMany($data['salaryRows']);
            $participant->benefits()->createMany($data['benefits']);
            $participant->attrition()->create($data['attrition']);

            return $participant;
        });
    }

    /**
     * @return BelongsTo<SurveyCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SurveyCycle::class, 'survey_cycle_id');
    }

    /**
     * @return HasMany<SurveySalaryRow, $this>
     */
    public function salaryRows(): HasMany
    {
        return $this->hasMany(SurveySalaryRow::class);
    }

    /**
     * @return HasMany<SurveyBenefit, $this>
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(SurveyBenefit::class);
    }

    /**
     * @return HasOne<SurveyAttrition, $this>
     */
    public function attrition(): HasOne
    {
        return $this->hasOne(SurveyAttrition::class);
    }
}
