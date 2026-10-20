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
        $clean = collect($report)->whereIn('status', ['imported', 'replaced'])->count();
        $toFix = collect($report)->where('status', 'needs_fixing')->count();
        $message = __(':saved of :total files imported.', ['saved' => $clean + $toFix, 'total' => count($report)])
            .($toFix > 0 ? ' '.__(':count need fixing before they count in the analytics.', ['count' => $toFix]) : '');

        return $this->toast($clean === count($report) ? 'success' : 'warning', $message);
    }
}
