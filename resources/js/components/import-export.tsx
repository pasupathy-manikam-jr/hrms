import { router, useForm } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { Download, FileDown, FileUp } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { toUrl } from '@/lib/utils';

type Href = NonNullable<InertiaLinkProps['href']>;

type ImportReport = {
    imported: number;
    skipped: { row: number; errors: string[] }[];
};

/** Downloads the current list (the href should carry the page's filters) as CSV. */
export function ExportButton({ href }: { href: Href }) {
    const { t } = useTranslation();

    return (
        <Button variant="outline" asChild>
            <a href={toUrl(href)} download>
                <FileDown /> {t('Export')}
            </a>
        </Button>
    );
}

/**
 * The demo's "Import from CSV/Excel" dialog: sample template, file picker, notes, and
 * a report of skipped rows (flashed by the controller as `import`).
 */
export function ImportButton({
    title,
    action,
    templateHref,
    notes,
}: {
    title: string;
    action: { url: string; method: 'post' };
    templateHref: Href;
    notes?: ReactNode;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [report, setReport] = useState<ImportReport | null>(null);
    const form = useForm<{ file: File | null }>({ file: null });

    useEffect(
        () =>
            router.on('flash', (event) => {
                const result = (event as CustomEvent).detail?.flash?.import as
                    | ImportReport
                    | undefined;

                if (result) {
                    setReport(result);
                }
            }),
        [],
    );

    const close = (next: boolean) => {
        setOpen(next);

        if (!next) {
            form.reset();
            form.clearErrors();
            setReport(null);
        }
    };

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                <FileUp /> {t('Import')}
            </Button>
            <Dialog open={open} onOpenChange={close}>
                <DialogContent className="sm:max-w-xl">
                    <form
                        noValidate
                        className="grid gap-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.submit(action, {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            });
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>{t(title)}</DialogTitle>
                        </DialogHeader>

                        <a
                            href={toUrl(templateHref)}
                            download
                            className="flex items-center justify-between rounded-lg border p-3 text-sm hover:bg-muted/50"
                        >
                            {t('Download sample template for required format')}
                            <span className="flex size-8 items-center justify-center rounded-md border text-primary">
                                <Download className="size-4" />
                            </span>
                        </a>

                        <div className="grid gap-2">
                            <Label htmlFor="import-file">
                                {t('Select File')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="import-file"
                                type="file"
                                accept=".csv,text/csv"
                                required
                                onChange={(e) =>
                                    form.setData(
                                        'file',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.file} />
                        </div>

                        {notes && (
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                                <div className="mb-1 font-semibold">
                                    {t('Import Notes')}:
                                </div>
                                {notes}
                            </div>
                        )}

                        {report && (
                            <div className="grid gap-2 rounded-lg border p-3 text-sm">
                                <div className="font-semibold">
                                    {t(':imported imported, :skipped skipped', {
                                        imported: report.imported,
                                        skipped: report.skipped.length,
                                    })}
                                </div>
                                {report.skipped.length > 0 && (
                                    <ul className="max-h-48 list-disc overflow-y-auto ps-5 text-destructive">
                                        {report.skipped.map((row) => (
                                            <li key={row.row}>
                                                {t('Row :row', {
                                                    row: row.row,
                                                })}
                                                : {row.errors.join(' ')}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => close(false)}
                            >
                                {t(report ? 'Close' : 'Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing || !form.data.file}
                            >
                                {form.processing && <Spinner />}
                                {t('Import')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
