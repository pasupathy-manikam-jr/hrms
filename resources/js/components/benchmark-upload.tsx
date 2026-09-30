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
    status: 'imported' | 'replaced' | 'rejected' | 'skipped';
    errors: string[];
    warnings: string[];
};

/** True when a visit's flashed upload report (if any) saved every file — the dialog can close. */
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
    return (
        <ul className="grid gap-3">
            {results.map((result, index) => (
                <li
                    key={index}
                    className="grid gap-1 rounded-lg border p-3 text-sm"
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
                    {result.errors.length > 0 && (
                        <ul className="max-h-40 list-disc overflow-y-auto ps-5 text-destructive">
                            {result.errors.map((error) => (
                                <li key={error}>{error}</li>
                            ))}
                        </ul>
                    )}
                    {result.warnings.length > 0 && (
                        <ul className="max-h-32 list-disc overflow-y-auto ps-5 text-amber-600">
                            {result.warnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                            ))}
                        </ul>
                    )}
                </li>
            ))}
        </ul>
    );
}
