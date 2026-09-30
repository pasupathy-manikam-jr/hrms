<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Go back to the previous page with a toast notification.
     */
    protected function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }

    protected function done(string $message): RedirectResponse
    {
        return $this->toast('success', $message);
    }

    /**
     * Flash a Benchmark Survey upload report (shown per file by the page) and toast the totals.
     *
     * @param  list<array{status: string}>  $report
     */
    protected function uploadReport(array $report): RedirectResponse
    {
        Inertia::flash('benchmarkUpload', $report);
        $saved = collect($report)->whereIn('status', ['imported', 'replaced'])->count();

        return $this->toast($saved === count($report) ? 'success' : 'warning', __(':saved of :total files imported.', ['saved' => $saved, 'total' => count($report)]));
    }
}
