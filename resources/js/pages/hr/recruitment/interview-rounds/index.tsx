import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Lock, LockOpen, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ViewDialog } from '@/components/view-dialog';
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import interviewRoundRoutes from '@/routes/hr/recruitment/interview-rounds';
import type { Paginated, TableFilters } from '@/types';

type JobOption = { id: number; title: string; job_code: string | null };

type InterviewRound = {
    id: number;
    job_id: number;
    name: string;
    sequence_number: number;
    description: string | null;
    status: 'active' | 'inactive';
    job: JobOption | null;
    created_at: string;
};

const blank = {
    job_id: '' as number | '',
    name: '',
    sequence_number: 1 as number | '',
    description: '',
    status: 'active' as InterviewRound['status'],
};

export default function InterviewRounds({
    interviewRounds,
    jobPostings,
    statusCounts,
    filters,
}: {
    interviewRounds: Paginated<InterviewRound>;
    jobPostings: JobOption[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<InterviewRound | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<InterviewRound | null>(null);
    const [viewing, setViewing] = useState<InterviewRound | null>(null);
    const form = useForm(blank);
    const url = interviewRoundRoutes.index();

    const openForm = (round: InterviewRound | null) => {
        setEditing(round);
        form.clearErrors();
        form.setData(
            round
                ? {
                      job_id: round.job_id,
                      name: round.name,
                      sequence_number: round.sequence_number,
                      description: round.description ?? '',
                      status: round.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? interviewRoundRoutes.update(editing.id)
                : interviewRoundRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<InterviewRound>[] = [
        {
            key: 'job',
            label: 'Job',
            render: (row) =>
                row.job ? (
                    <div className="grid justify-items-start gap-1">
                        <span className="font-medium">{row.job.title}</span>
                        {row.job.job_code && (
                            <IdBadge>{row.job.job_code}</IdBadge>
                        )}
                    </div>
                ) : (
                    '—'
                ),
        },
        {
            key: 'sequence_number',
            label: 'Sequence',
            sortable: true,
            render: (row) => <IdBadge>{row.sequence_number}</IdBadge>,
        },
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'description',
            label: 'Description',
            render: (row) => (
                <span className="line-clamp-2 max-w-sm text-muted-foreground">
                    {row.description}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (row) => <DateCell value={row.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Interview Rounds')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Interview Rounds"
                    description="Define the interview stages for each job posting."
                    action={
                        can('create-interview-rounds') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Interview Round')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={interviewRounds}
                    columns={columns}
                    filters={filters}
                    url={url}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    toolbar={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="job_id"
                            label="All Jobs"
                            options={jobPostings.map((j) => ({
                                id: j.id,
                                name: j.title,
                            }))}
                        />
                    }
                    actions={(round) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(round)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-interview-rounds') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(round)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            round.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            round.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                interviewRoundRoutes.toggleStatus(
                                                    round.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {round.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-interview-rounds') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(round)}
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
                title={editing ? 'Edit Interview Round' : 'Add Interview Round'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="round-job">
                            {t('Job Posting')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="round-job"
                            required
                            value={form.data.job_id}
                            onChange={(e) =>
                                form.setData(
                                    'job_id',
                                    e.target.value ? +e.target.value : '',
                                )
                            }
                        >
                            <option value="">{t('Select Job Posting')}</option>
                            {jobPostings.map((j) => (
                                <option key={j.id} value={j.id}>
                                    {j.title}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.job_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="round-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="round-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="round-sequence">
                            {t('Sequence Number')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="round-sequence"
                            type="number"
                            min={1}
                            required
                            value={form.data.sequence_number}
                            onChange={(e) =>
                                form.setData(
                                    'sequence_number',
                                    e.target.value ? +e.target.value : '',
                                )
                            }
                        />
                        <InputError message={form.errors.sequence_number} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="round-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="round-description"
                            rows={3}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="round-status">{t('Status')}</Label>
                        <SelectField
                            id="round-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as InterviewRound['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                </div>
            </FormDialog>

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Interview Round Details"
                fields={
                    viewing
                        ? [
                              ['Name', viewing.name],
                              ['Sequence Number', viewing.sequence_number],
                              ['Job', viewing.job?.title],
                              [
                                  'Status',
                                  <StatusBadge
                                      key="status"
                                      status={viewing.status}
                                  />,
                              ],
                              ['Description', viewing.description, true],
                          ]
                        : []
                }
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This interview round will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(interviewRoundRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

InterviewRounds.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: interviewRoundRoutes.index() },
        { title: 'Interview Rounds', href: interviewRoundRoutes.index() },
    ],
};
