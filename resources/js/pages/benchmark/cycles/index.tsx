import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Download, Plus, SquarePen, Trash2 } from 'lucide-react';
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
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import cycleRoutes from '@/routes/benchmark/cycles';
import participantRoutes from '@/routes/benchmark/participants';
import type { Paginated, TableFilters } from '@/types';

type Cycle = {
    id: number;
    name: string;
    status: 'open' | 'closed';
    min_companies: number;
    template_name: string | null;
    participants_count: number;
    jobs_count: number;
    created_at: string;
};

const blank = {
    name: '',
    status: 'open' as Cycle['status'],
    min_companies: '3',
    template: null as File | null,
    files: [] as File[],
};

export default function SurveyCycles({
    cycles,
    filters,
}: {
    cycles: Paginated<Cycle>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Cycle | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Cycle | null>(null);
    const form = useForm(blank);
    const [results, setResults] = useUploadReport();

    const openForm = (cycle: Cycle | null) => {
        setEditing(cycle);
        setResults(null);
        form.clearErrors();
        form.setData(
            cycle
                ? {
                      name: cycle.name,
                      status: cycle.status,
                      min_companies: String(cycle.min_companies),
                      template: null,
                      files: [],
                  }
                : blank,
        );
        setFormOpen(true);
    };

    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
    const submit = () =>
        form.post(
            editing
                ? cycleRoutes.update.form(editing.id).action
                : cycleRoutes.store().url,
            {
                forceFormData: true,
                preserveScroll: true,
                // Stay open only to show why a workbook was rejected or skipped.
                onSuccess: (page) =>
                    allImported(page.flash)
                        ? setFormOpen(false)
                        : form.setData('files', []),
            },
        );

    const columns: Column<Cycle>[] = [
        {
            key: 'name',
            label: 'Cycle',
            sortable: true,
            render: (c) => (
                <div>
                    <div className="font-medium">{c.name}</div>
                    <div className="text-muted-foreground">
                        {c.template_name}
                    </div>
                </div>
            ),
        },
        {
            key: 'participants',
            label: 'Companies',
            render: (c) => (
                <Link
                    href={participantRoutes.index({ query: { cycle: c.id } })}
                    className="font-medium text-primary hover:underline"
                >
                    {c.participants_count}
                </Link>
            ),
        },
        { key: 'jobs', label: 'Catalogue Jobs', render: (c) => c.jobs_count },
        {
            key: 'min_companies',
            label: 'Min. Companies per Figure',
            render: (c) => c.min_companies,
        },
        {
            key: 'status',
            label: 'Status',
            render: (c) => <StatusBadge status={c.status} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (c) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(c.created_at)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Survey Cycles')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Survey Cycles"
                    description="Each survey edition with its blank template, which supplies the job catalogue and answer lists."
                    action={
                        can('upload-benchmark-survey') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Cycle')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={cycles}
                    columns={columns}
                    filters={filters}
                    url={cycleRoutes.index()}
                    actions={(cycle) => (
                        <>
                            {cycle.template_name && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Download template')}
                                    asChild
                                >
                                    <a
                                        href={cycleRoutes.template.url(
                                            cycle.id,
                                        )}
                                        download
                                    >
                                        <Download />
                                    </a>
                                </Button>
                            )}
                            {can('upload-benchmark-survey') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(cycle)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-benchmark-survey') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(cycle)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Cycle' : 'Add Cycle'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="cycle-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="cycle-name"
                            placeholder="2025/2026"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cycle-status">{t('Status')}</Label>
                        <SelectField
                            id="cycle-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Cycle['status'],
                                )
                            }
                        >
                            <option value="open">{t('Open')}</option>
                            <option value="closed">{t('Closed')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cycle-min">
                            {t('Min. Companies per Figure')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="cycle-min"
                            type="number"
                            min={1}
                            value={form.data.min_companies}
                            onChange={(e) =>
                                form.setData('min_companies', e.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'A figure is hidden unless at least this many companies contribute to it.',
                            )}
                        </p>
                        <InputError message={form.errors.min_companies} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cycle-template">
                            {t('Blank Survey Template (optional)')}
                        </Label>
                        <Input
                            id="cycle-template"
                            type="file"
                            accept=".xlsx"
                            onChange={(e) =>
                                form.setData(
                                    'template',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {editing?.template_name
                                ? t('Leave empty to keep :name.', {
                                      name: editing.template_name,
                                  })
                                : t(
                                      'Not needed with completed workbooks, which carry the same job catalogue and lists. Adding it also cleans out remarks left as the template’s guidance text.',
                                  )}
                        </p>
                        <InputError message={form.errors.template} />
                    </div>
                    {!editing && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="cycle-files">
                                {t('Completed Workbooks')}
                            </Label>
                            <WorkbookPicker
                                id="cycle-files"
                                files={form.data.files}
                                onChange={(files) =>
                                    form.setData('files', files)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'The companies’ filled-in workbooks (or at least the blank template above). More can be uploaded later from Participants.',
                                )}
                            </p>
                            <InputError
                                message={
                                    form.errors.files ??
                                    Object.entries(form.errors).find(([key]) =>
                                        key.startsWith('files.'),
                                    )?.[1]
                                }
                            />
                        </div>
                    )}
                    {results && (
                        <div className="sm:col-span-2">
                            <UploadReport results={results} />
                        </div>
                    )}
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This cycle and every company submission in it will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(cycleRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

SurveyCycles.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Benchmark Survey', href: cycleRoutes.index() },
        { title: 'Survey Cycles', href: cycleRoutes.index() },
    ],
};
