<?php

namespace App\Models\Benchmark;

use App\Models\User;
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
use Throwable;

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
 * @property list<string>|null $errors open (not accepted) errors; while set, the submission is left out of the analytics
 * @property list<array{type: string, message: string, target: string|null, rule: string|null}>|null $issues every finding, with what it is about
 * @property array<string, array{value: string, message: string, rule: string|null}>|null $raw_answers answers that failed a check, as typed ("target.field" => value)
 * @property list<string>|null $accepted_issues keys of findings accepted as they stand (see SurveyWorkbook::issueKey())
 * @property CarbonInterface|null $consent_date
 * @property bool|null $consented
 * @property CarbonInterface|null $edited_at
 */
#[Fillable([
    'survey_cycle_id', 'company_name', 'company_key', 'industry', 'state', 'employee_band', 'revenue_band',
    'ownership_type', 'locations', 'listed_status', 'unionised', 'authorised_name', 'authorised_designation',
    'consented', 'consent_date', 'file_path', 'file_name', 'errors', 'warnings', 'issues', 'accepted_issues', 'raw_answers', 'edited_at', 'edited_by', 'uploaded_by',
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
        return ['errors' => 'array', 'warnings' => 'array', 'issues' => 'array', 'accepted_issues' => 'array', 'raw_answers' => 'array', 'consented' => 'boolean', 'edited_at' => 'datetime', 'consent_date' => 'date:Y-m-d', 'locations' => 'integer'];
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
     * Reads, checks and saves completed workbooks into the cycle. A workbook with errors is still saved, with its
     * errors, so it can be followed up; it stays out of the analytics until a clean re-upload replaces it. A company
     * already in the cycle is skipped unless $replace. Returns one report entry per file.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{file: string, company: string|null, status: string, errors: list<string>, warnings: list<string>}>
     */
    public static function importWorkbooks(SurveyCycle $cycle, array $files, bool $replace, ?int $userId): array
    {
        $catalogue = $cycle->catalogue();
        $reader = new SurveyWorkbook;
        $report = [];

        foreach ($files as $file) {
            $data = $reader->read($file->getRealPath(), $cycle, $catalogue);
            $company = $data['participant']['company_name'] ?? null;
            $entry = ['file' => $file->getClientOriginalName(), 'company' => $company, 'errors' => $data['errors'], 'warnings' => $data['warnings']];

            // Only a file that isn't the survey workbook, or has no company name, can't be kept for follow-up.
            if ($company === null) {
                $report[] = [...$entry, 'status' => 'rejected'];

                continue;
            }

            $existing = self::query()->where('survey_cycle_id', $cycle->id)->where('company_key', self::keyFor($company))->first(['id', 'errors']);
            $exists = $existing !== null;

            // A submission still waiting for fixes is always replaced by the company's next file.
            if ($exists && ! $replace && $existing->errors === null) {
                $report[] = [...$entry, 'status' => 'skipped', 'errors' => [__('This company has already been uploaded to this cycle. Tick "Replace existing submissions" to overwrite it.')]];

                continue;
            }

            $path = $file->store(self::UPLOAD_DIRECTORY, 'local') ?: throw new \RuntimeException('Could not store the workbook.');
            try {
                self::record($cycle, $data, ['file_path' => $path, 'file_name' => $file->getClientOriginalName(), 'uploaded_by' => $userId]);
            } catch (Throwable $e) {
                // One unsavable file (e.g. an answer too long for its column) must not stop the rest of the batch.
                report($e);
                Storage::disk('local')->delete($path);
                $report[] = [...$entry, 'status' => 'rejected', 'errors' => [...$data['errors'], __('The workbook could not be saved; check for unusually long or malformed answers.')]];

                continue;
            }
            $report[] = [...$entry, 'status' => match (true) {
                $data['errors'] !== [] => 'needs_fixing',
                $exists => 'replaced',
                default => 'imported',
            }];
        }

        return $report;
    }

    /**
     * Saves one company's answers (as read by SurveyWorkbook) into the cycle, replacing that company's
     * previous submission if there is one.
     *
     * @param  array{participant: array<string, mixed>, salaryRows: array<int, array<string, mixed>>, benefits: array<int, array<string, mixed>>, attrition: array<string, mixed>, issues?: list<array{type: string, message: string, target: string|null, rule: string|null}>, raw?: array<string, array{value: string, message: string, rule: string|null}>, errors?: array<int, string>, warnings: array<int, string>}  $data
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
                'issues' => $data['issues'] ?? [],
                'accepted_issues' => null,
                'raw_answers' => ($data['raw'] ?? []) ?: null,
                'errors' => ($data['errors'] ?? []) ?: null,
                'warnings' => $data['warnings'],
            ]);

            $participant->salaryRows()->createMany($data['salaryRows']);
            $participant->benefits()->createMany($data['benefits']);
            $participant->attrition()->create($data['attrition']);

            return $participant;
        });
    }

    /**
     * Records an online correction and re-checks the whole submission from its saved answers, so it becomes
     * ready for the analytics once nothing is left to fix.
     */
    public function corrected(?int $userId): void
    {
        $this->update([
            'issues' => (new SurveyWorkbook)->check($this, $this->cycle->catalogue())['issues'],
            'edited_at' => now(),
            'edited_by' => $userId,
        ]);
        $this->refreshOpenIssues();
    }

    /**
     * Accepts (or un-accepts) findings as they stand. Only keys of this submission's acceptable findings count.
     *
     * @param  list<string>  $keys
     */
    public function accept(array $keys, bool $accepted, ?int $userId): void
    {
        $keys = array_intersect($keys, array_filter(array_map(SurveyWorkbook::issueKey(...), $this->issues ?? [])));
        $current = $this->accepted_issues ?? [];
        $this->update([
            'accepted_issues' => array_values($accepted ? array_unique([...$current, ...$keys]) : array_diff($current, $keys)) ?: null,
            'edited_at' => now(),
            'edited_by' => $userId,
        ]);
        $this->refreshOpenIssues();
    }

    /**
     * Errors and warnings are the findings not accepted; with no errors open, the submission counts in the analytics.
     */
    private function refreshOpenIssues(): void
    {
        $open = SurveyWorkbook::summary($this->issues ?? [], $this->accepted_issues ?? []);
        $this->update(['errors' => $open['errors'] ?: null, 'warnings' => $open['warnings']]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
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
