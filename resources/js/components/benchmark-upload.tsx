import { router } from '@inertiajs/react';
import { FileUp, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

/** One file's outcome, flashed by the server as `benchmarkUpload`. */
export type UploadResult = {
    file: string;
    company: string | null;
    status: 'imported' | 'replaced' | 'needs_fixing' | 'rejected' | 'skipped';
    errors: string[];
    warnings: string[];
};

/** True when a visit's flashed upload report (if any) saved every file without errors — the dialog can close. */
export function allImported(flash: Record<string, unknown>) {
    const report = flash.benchmarkUpload as UploadResult[] | undefined;

    return (report ?? []).every(
        (result) =>
            result.status === 'imported' || result.status === 'replaced',
    );
}

/** The last upload report flashed by the server (null until one arrives, or after reset). */
export function useUploadReport() {
    const [report, setReport] = useState<UploadResult[] | null>(null);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const flashed = (event as CustomEvent).detail?.flash
                    ?.benchmarkUpload as UploadResult[] | undefined;

                if (flashed) {
                    setReport(flashed);
                }
            }),
        [],
    );

    return [report, setReport] as const;
}

/**
 * Drop zone + list of chosen workbooks. Picks and drops add to the list (a second pick
 * doesn't replace the first); each file can be removed.
 */
export function WorkbookPicker({
    id,
    files,
    onChange,
}: {
    id: string;
    files: File[];
    onChange: (files: File[]) => void;
}) {
    const { t } = useTranslation();

    const add = (picked: FileList | null) => {
        const names = new Set(files.map((file) => file.name));

        onChange([
            ...files,
            ...Array.from(picked ?? []).filter(
                (file) =>
                    file.name.toLowerCase().endsWith('.xlsx') &&
                    !names.has(file.name),
            ),
        ]);
    };

    return (
        <>
            <label
                htmlFor={id}
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                    e.preventDefault();
                    add(e.dataTransfer.files);
                }}
                className="flex cursor-pointer flex-col items-center gap-1 rounded-lg border-2 border-dashed p-6 text-center text-sm text-muted-foreground hover:bg-muted/50"
            >
                <FileUp className="size-6 text-primary" />
                <span className="font-medium text-foreground">
                    {t('Drop workbooks here or click to choose')}
                </span>
                {t(
                    'You can add several files, in one go (⌘/Ctrl-click) or one after another.',
                )}
            </label>
            <input
                id={id}
                type="file"
                accept=".xlsx"
                multiple
                className="sr-only"
                onChange={(e) => {
                    add(e.target.files);
                    e.target.value = '';
                }}
            />
            {files.length > 0 && (
                <ul className="grid gap-1 text-sm">
                    {files.map((file) => (
                        <li
                            key={file.name}
                            className="flex items-center justify-between gap-2 rounded-md border px-3 py-1.5"
                        >
                            <span className="truncate">{file.name}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                aria-label={t('Remove')}
                                onClick={() =>
                                    onChange(
                                        files.filter((other) => other !== file),
                                    )
                                }
                            >
                                <X />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

/** Per-file outcome of an upload: company, status, errors and warnings. */
export function UploadReport({ results }: { results: UploadResult[] }) {
    const { t } = useTranslation();
    const count = (status: UploadResult['status']) =>
        results.filter((result) => result.status === status).length;
    const toFix = count('needs_fixing');
    const notSaved = count('rejected') + count('skipped');

    return (
        <div className="grid min-w-0 gap-3">
            <p className="text-sm font-medium">
                {t(':saved of :total files saved.', {
                    saved: results.length - notSaved,
                    total: results.length,
                })}{' '}
                {toFix > 0 &&
                    t(
                        ':n need fixing: their issues are kept on each company’s Issues tab and in Participants → Export Issues.',
                        { n: toFix },
                    )}
            </p>
            <ul className="grid min-w-0 gap-2">
                {results.map((result, index) => (
                    <li
                        key={index}
                        className="grid min-w-0 gap-1 rounded-lg border p-3 text-sm [overflow-wrap:anywhere]"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div className="min-w-0">
                                <div className="truncate font-medium">
                                    {result.company ?? result.file}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {result.file}
                                </div>
                            </div>
                            <StatusBadge status={result.status} />
                        </div>
                        {result.errors.length + result.warnings.length > 0 && (
                            // A file that wasn't saved shows why at once; a saved one keeps its list folded.
                            <details
                                open={
                                    result.status === 'rejected' ||
                                    result.status === 'skipped'
                                }
                            >
                                <summary className="cursor-pointer text-muted-foreground select-none">
                                    {result.errors.length > 0 && (
                                        <span className="text-destructive">
                                            {t(':n errors', {
                                                n: result.errors.length,
                                            })}
                                        </span>
                                    )}
                                    {result.errors.length > 0 &&
                                        result.warnings.length > 0 &&
                                        ' · '}
                                    {result.warnings.length > 0 && (
                                        <span className="text-amber-600">
                                            {t(':n warnings', {
                                                n: result.warnings.length,
                                            })}
                                        </span>
                                    )}
                                </summary>
                                <ol className="mt-1 max-h-60 list-decimal overflow-y-auto ps-5">
                                    {result.errors.map((error, i) => (
                                        <li
                                            key={`e${i}`}
                                            className="text-destructive"
                                        >
                                            {error}
                                        </li>
                                    ))}
                                    {result.warnings.map((warning, i) => (
                                        <li
                                            key={`w${i}`}
                                            className="text-amber-600"
                                        >
                                            {warning}
                                        </li>
                                    ))}
                                </ol>
                            </details>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
