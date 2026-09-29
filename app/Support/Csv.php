<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export/import for list pages (opens in Excel; UTF-8 with a BOM so names survive).
 */
class Csv
{
    /**
     * Stream rows as a CSV download.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w') ?: throw new RuntimeException('Cannot open the output stream.');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, escape: '');

            foreach ($rows as $row) {
                // A leading =, +, - or @ would run as a formula in Excel; quote it as text.
                fputcsv($out, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value, $row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Rows of an uploaded CSV keyed by snake_case header ("Employee ID" → employee_id),
     * with the spreadsheet row number (header = row 1) as the key. Blank lines are skipped.
     *
     * @return LazyCollection<int, array<string, string>>
     */
    public static function read(UploadedFile $file): LazyCollection
    {
        return LazyCollection::make(function () use ($file) {
            $handle = fopen($file->getRealPath(), 'r') ?: throw new RuntimeException('Cannot read the uploaded file.');
            $headers = null;
            $line = 0;

            while (($values = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                if ($headers === null) {
                    $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $values[0]);
                    $headers = array_map(fn ($header) => Str::snake(trim((string) $header)), $values);

                    continue;
                }

                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                yield $line => array_combine($headers, array_map(
                    fn ($value) => trim((string) $value),
                    array_pad(array_slice($values, 0, count($headers)), count($headers), ''),
                ));
            }

            fclose($handle);
        });
    }

    /**
     * Validation rules for an uploaded CSV.
     *
     * @return list<string>
     */
    public static function uploadRules(): array
    {
        return ['required', 'file', 'mimes:csv,txt', 'max:2048'];
    }
}
