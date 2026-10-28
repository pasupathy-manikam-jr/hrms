import { Head, router, useForm } from '@inertiajs/react';
import { Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import jobRoutes from '@/routes/benchmark/jobs';
import type { Paginated, TableFilters } from '@/types';

type Job = {
    id: number;
    code: string;
    industry: string;
    job_family: string;
    title: string;
    typical_level: string | null;
    summary: string | null;
    responsibilities: string | null;
    requirements: string | null;
    masco_group: string | null;
    masco_reference: string | null;
};

/** Form fields: [key, label, suggestions list, required, multi-line]. */
const FIELDS: [
    keyof Job,
    string,
    'industries' | 'families' | 'levels' | null,
    boolean,
    boolean,
][] = [
    ['code', 'Code', null, true, false],
    ['title', 'Standard Job Title', null, true, false],
    ['industry', 'Industry', 'industries', true, false],
    ['job_family', 'Job Family', 'families', true, false],
    ['typical_level', 'Typical Level', 'levels', false, false],
    ['masco_group', 'MASCO Group', null, false, false],
    ['masco_reference', 'MASCO Reference', null, false, false],
    ['summary', 'Summary', null, false, true],
    ['responsibilities', 'Responsibilities', null, false, true],
    ['requirements', 'Requirements', null, false, true],
];

const blank = Object.fromEntries(FIELDS.map(([key]) => [key, ''])) as Record<
    string,
    string
>;

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const options = (values: string[]) =>
    values.map((value) => ({ id: value, name: value }));

export default function BenchmarkJobs({
    jobs,
    filters,
    cycles,
    cycle,
    industries,
    families,
    levels,
}: {
    jobs: Paginated<Job>;
    filters: TableFilters;
    cycles: { id: number; name: string }[];
    cycle: { id: number; name: string } | null;
    industries: string[];
    families: string[];
    levels: string[];
}) {
    const { t } = useTranslation();
    const can = useCan();
    const lists = { industries, families, levels };
    const [editing, setEditing] = useState<Job | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Job | null>(null);
    const form = useForm<Record<string, string>>(blank);

    const openForm = (job: Job | null) => {
        setEditing(job);
        form.clearErrors();
        form.setData(
            job
                ? (Object.fromEntries(
                      FIELDS.map(([key]) => [key, job[key] ?? '']),
                  ) as Record<string, string>)
                : blank,
        );
        setFormOpen(true);
    };
    const saveOptions = {
        preserveScroll: true,
        onSuccess: () => setFormOpen(false),
    };
    const url = jobRoutes.index();
    const current = cycle ? { ...filters, cycle: String(cycle.id) } : filters;

    const columns: Column<Job>[] = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (j) => (
                <span className="rounded-md border border-blue-200 px-2 py-0.5 text-xs font-medium text-blue-700">
                    {j.code}
                </span>
            ),
        },
        {
            key: 'title',
            label: 'Standard Job Title',
            sortable: true,
            render: (j) => (
                <div className="max-w-md">
                    <div className="font-medium">{j.title}</div>
                    <div className="line-clamp-2 text-xs text-muted-foreground">
                        {j.summary}
                    </div>
                </div>
            ),
        },
        {
            key: 'job_family',
            label: 'Job Family',
            sortable: true,
            render: (j) => j.job_family,
        },
        {
            key: 'industry',
            label: 'Industry',
            sortable: true,
            render: (j) => j.industry,
        },
        {
            key: 'typical_level',
            label: 'Typical Level',
            render: (j) => j.typical_level,
        },
        {
            key: 'masco_reference',
            label: 'MASCO',
            render: (j) => j.masco_reference,
        },
    ];
    const canEdit = can('upload-benchmark-survey');
    const canDelete = can('delete-benchmark-survey');

    return (
        <>
            <Head title={t('Job Catalogue')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Job Catalogue"
                    description="The standard job titles companies map their roles to, from the cycle template. Changes carry through to the submissions that use a job; uploading a new template for the cycle replaces the catalogue."
                    action={
                        canEdit &&
                        cycle && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Job')}
                            </Button>
                        )
                    }
                />
                <DataTable
                    data={jobs}
                    columns={columns}
                    filters={current}
                    url={url}
                    actions={
                        canEdit || canDelete
                            ? (j) => (
                                  <>
                                      {canEdit && (
                                          <Button
                                              variant="ghost"
                                              size="icon"
                                              aria-label={t('Edit')}
                                              onClick={() => openForm(j)}
                                          >
                                              <SquarePen />
                                          </Button>
                                      )}
                                      {canDelete && (
                                          <Button
                                              variant="ghost"
                                              size="icon"
                                              aria-label={t('Delete')}
                                              onClick={() => setDeleting(j)}
                                          >
                                              <Trash2 />
                                          </Button>
                                      )}
                                  </>
                              )
                            : undefined
                    }
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
                                options={options(industries)}
                            />
                            <FilterSelect
                                url={url}
                                filters={current}
                                name="job_family"
                                label="All Job Families"
                                options={options(families)}
                            />
                        </>
                    }
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Job' : 'Add Job'}
                processing={form.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (editing) {
                        form.put(jobRoutes.update.url(editing.id), saveOptions);
                    } else {
                        form.transform((data) => ({
                            ...data,
                            survey_cycle_id: cycle?.id ?? '',
                        }));
                        form.post(jobRoutes.store.url(), saveOptions);
                    }
                }}
            >
                {Object.entries(lists).map(([name, values]) => (
                    <datalist key={name} id={`job-${name}`}>
                        {values.map((value) => (
                            <option key={value} value={value} />
                        ))}
                    </datalist>
                ))}
                <div className="grid gap-4 sm:grid-cols-2">
                    {FIELDS.map(([key, label, list, required, multiline]) => (
                        <div
                            key={key}
                            className={
                                multiline || key === 'title'
                                    ? 'grid gap-2 sm:col-span-2'
                                    : 'grid gap-2'
                            }
                        >
                            <Label htmlFor={`job-${key}`}>
                                {t(label)}
                                {required && (
                                    <span className="text-destructive">*</span>
                                )}
                            </Label>
                            {multiline ? (
                                <textarea
                                    id={`job-${key}`}
                                    rows={3}
                                    className={textareaClass}
                                    value={form.data[key]}
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                />
                            ) : (
                                <Input
                                    id={`job-${key}`}
                                    list={list ? `job-${list}` : undefined}
                                    value={form.data[key]}
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                />
                            )}
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This job will be removed from the cycle's catalogue. A job that submissions use can't be deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(jobRoutes.destroy.url(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

BenchmarkJobs.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Benchmark Survey', href: jobRoutes.index() },
        { title: 'Job Catalogue', href: jobRoutes.index() },
    ],
};
