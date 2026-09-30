import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    Download,
    Eye,
    FileUp,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import {
    allImported,
    UploadReport,
    useUploadReport,
    WorkbookPicker,
} from '@/components/benchmark-upload';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import cycleRoutes from '@/routes/benchmark/cycles';
import participantRoutes from '@/routes/benchmark/participants';
import type { Paginated, TableFilters } from '@/types';

type Participant = {
    id: number;
    company_name: string;
    industry: string;
    state: string;
    employee_band: string;
    ownership_type: string | null;
    file_name: string | null;
    warnings: string[] | null;
    salary_rows_count: number;
    salary_rows_sum_headcount: number | null;
    created_at: string;
};

type Cycle = { id: number; name: string; status: string };

const options = (values: string[]) =>
    values.map((value) => ({ id: value, name: value }));

export default function SurveyParticipants({
    participants,
    filters,
    cycles,
    cycle,
    lookups,
}: {
    participants: Paginated<Participant>;
    filters: TableFilters;
    cycles: Cycle[];
    cycle: (Cycle & { min_companies: number }) | null;
    lookups: { industries: string[]; states: string[]; bands: string[] };
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [uploadOpen, setUploadOpen] = useState(false);
    const [results, setResults] = useUploadReport();
    const [deleting, setDeleting] = useState<Participant | null>(null);
    const form = useForm<{
        cycle_id: number | '';
        files: File[];
        replace: boolean;
    }>({ cycle_id: cycle?.id ?? '', files: [], replace: false });
    const url = participantRoutes.index();
    // Keep the shown cycle in every filter/sort/search link.
    const current = cycle ? { ...filters, cycle: String(cycle.id) } : filters;

    const closeUpload = (open: boolean) => {
        setUploadOpen(open);

        if (!open) {
            form.reset();
            form.clearErrors();
            setResults(null);
        }
    };

    const columns: Column<Participant>[] = [
        {
            key: 'company_name',
            label: 'Company',
            sortable: true,
            render: (p) => (
                <div>
                    <div className="font-medium">{p.company_name}</div>
                    <div className="text-muted-foreground">{p.industry}</div>
                </div>
            ),
        },
        {
            key: 'state',
            label: 'State',
            sortable: true,
            render: (p) => p.state,
        },
        {
            key: 'employee_band',
            label: 'Employees',
            render: (p) => p.employee_band,
        },
        {
            key: 'roles',
            label: 'Roles Reported',
            render: (p) => (
                <span>
                    {p.salary_rows_count}
                    <span className="text-muted-foreground">
                        {' '}
                        · {p.salary_rows_sum_headcount ?? 0} {t('staff')}
                    </span>
                </span>
            ),
        },
        {
            key: 'warnings',
            label: 'Warnings',
            render: (p) =>
                p.warnings?.length ? (
                    <span className="flex items-center gap-1 text-amber-600">
                        <TriangleAlert className="size-4" />
                        {p.warnings.length}
                    </span>
                ) : (
                    '-'
                ),
        },
        {
            key: 'created_at',
            label: 'Uploaded At',
            sortable: true,
            render: (p) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(p.created_at)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Participants')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Participants"
                    description="Companies whose completed survey workbooks have been uploaded. Each company's answers are kept separately."
                    action={
                        can('upload-benchmark-survey') &&
                        cycle && (
                            <Button onClick={() => setUploadOpen(true)}>
                                <FileUp /> {t('Upload Workbooks')}
                            </Button>
                        )
                    }
                />

                {!cycle ? (
                    <div className="rounded-xl border bg-card p-10 text-center text-muted-foreground">
                        <Building2 className="mx-auto mb-3 size-10" />
                        {t('Create a survey cycle first.')}{' '}
                        <Link
                            href={cycleRoutes.index()}
                            className="text-primary hover:underline"
                        >
                            {t('Survey Cycles')}
                        </Link>
                    </div>
                ) : (
                    <DataTable
                        data={participants}
                        columns={columns}
                        filters={current}
                        url={url}
                        toolbar={
                            <>
                                <FilterSelect
                                    url={url}
                                    filters={current}
                                    name="cycle"
                                    label="Cycle"
                                    options={cycles}
                                />
                                <FilterSelect
                                    url={url}
                                    filters={current}
                                    name="industry"
                                    label="All Industries"
                                    options={options(lookups.industries)}
                                />
                                <FilterSelect
                                    url={url}
                                    filters={current}
                                    name="state"
                                    label="All States"
                                    options={options(lookups.states)}
                                />
                                <FilterSelect
                                    url={url}
                                    filters={current}
                                    name="employee_band"
                                    label="All Sizes"
                                    options={options(lookups.bands)}
                                />
                            </>
                        }
                        actions={(p) => (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    asChild
                                >
                                    <Link href={participantRoutes.show(p.id)}>
                                        <Eye />
                                    </Link>
                                </Button>
                                {p.file_name && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Download')}
                                        asChild
                                    >
                                        <a
                                            href={participantRoutes.download.url(
                                                p.id,
                                            )}
                                            download
                                        >
                                            <Download />
                                        </a>
                                    </Button>
                                )}
                                {can('delete-benchmark-survey') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(p)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </>
                        )}
                    />
                )}
            </div>

            <Dialog open={uploadOpen} onOpenChange={closeUpload}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                    <form
                        noValidate
                        className="grid gap-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(participantRoutes.upload.url(), {
                                forceFormData: true,
                                preserveScroll: true,
                                // Stay open only to show why a workbook was rejected or skipped.
                                onSuccess: (page) =>
                                    allImported(page.flash)
                                        ? closeUpload(false)
                                        : form.setData('files', []),
                            });
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>
                                {t('Upload Completed Workbooks')} —{' '}
                                {cycle?.name}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label htmlFor="upload-files">
                                {t('Workbooks (.xlsx)')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <WorkbookPicker
                                id="upload-files"
                                files={form.data.files}
                                onChange={(files) =>
                                    form.setData('files', files)
                                }
                            />
                            <InputError
                                message={
                                    form.errors.files ??
                                    Object.entries(form.errors).find(([key]) =>
                                        key.startsWith('files.'),
                                    )?.[1]
                                }
                            />
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.replace}
                                onCheckedChange={(checked) =>
                                    form.setData('replace', checked === true)
                                }
                            />
                            {t(
                                'Replace existing submissions from the same company',
                            )}
                        </label>

                        <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                            {t(
                                'Each file is checked against the cycle template. Files with errors are not imported; fix them and upload again. Warnings are imported and flagged.',
                            )}
                        </div>

                        {results && <UploadReport results={results} />}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeUpload(false)}
                            >
                                {t(results ? 'Close' : 'Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    form.data.files.length === 0
                                }
                            >
                                {form.processing && <Spinner />}
                                {form.data.files.length > 1
                                    ? t('Upload :n files', {
                                          n: form.data.files.length,
                                      })
                                    : t('Upload')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This company's submission and uploaded file will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(participantRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

SurveyParticipants.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Benchmark Survey', href: participantRoutes.index() },
        { title: 'Participants', href: participantRoutes.index() },
    ],
};
