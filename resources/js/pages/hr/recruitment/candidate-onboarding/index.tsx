import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import onboardingRoutes from '@/routes/hr/recruitment/candidate-onboarding';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Task = {
    id: number;
    task_name: string;
    description: string | null;
    category: string;
    assigned_to_role: string | null;
    due_date: string;
    is_required: boolean;
    status: 'pending' | 'completed';
    completed_at: string | null;
};

type Onboarding = {
    id: number;
    candidate_id: number;
    checklist_id: number | null;
    start_date: string;
    buddy_employee_id: number | null;
    status: 'Pending' | 'In Progress' | 'Completed';
    progress: number;
    candidate: {
        id: number;
        first_name: string;
        last_name: string;
        email: string | null;
    };
    checklist: Option | null;
    buddy: {
        id: number;
        employee_id: string;
        gender: string | null;
        user: Option & { email: string; avatar: string | null };
    } | null;
    created_at: string;
    tasks: Task[];
};

type Checklist = Option & { is_default: boolean; items_count: number };

export default function CandidateOnboarding({
    candidateOnboarding,
    statusCounts,
    candidates,
    checklists,
    buddyEmployees,
    filters,
}: {
    candidateOnboarding: Paginated<Onboarding>;
    statusCounts: Record<string, number>;
    candidates: Option[];
    checklists: Checklist[];
    buddyEmployees: (Option & { employee_id: string })[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();

    const can = useCan();
    const url = onboardingRoutes.index();
    const blank = {
        candidate_id: '' as number | string,
        checklist_id: (checklists.find((c) => c.is_default)?.id ?? '') as
            | number
            | string,
        start_date: '',
        buddy_employee_id: '' as number | string,
    };
    const [editing, setEditing] = useState<Onboarding | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Onboarding | null>(null);
    const form = useForm(blank);

    const name = (o: Onboarding) =>
        `${o.candidate.first_name} ${o.candidate.last_name}`;

    const openForm = (onboarding: Onboarding | null) => {
        setEditing(onboarding);
        form.clearErrors();
        form.setData(
            onboarding
                ? {
                      candidate_id: onboarding.candidate_id,
                      checklist_id: onboarding.checklist_id ?? '',
                      start_date: onboarding.start_date,
                      buddy_employee_id: onboarding.buddy_employee_id ?? '',
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Onboarding>[] = [
        {
            key: 'candidate',
            label: 'Employee',
            render: (row) => (
                <PersonCell name={name(row)} detail={row.candidate.email} />
            ),
        },
        {
            key: 'checklist',
            label: 'Checklist',
            render: (row) => row.checklist?.name ?? '—',
        },
        {
            key: 'start_date',
            label: 'Start Date',
            sortable: true,
            render: (row) => <DateCell value={row.start_date} />,
        },
        {
            key: 'buddy',
            label: 'Buddy',
            render: (row) =>
                row.buddy ? (
                    <PersonCell
                        name={row.buddy.user.name}
                        detail={row.buddy.user.email}
                        src={row.buddy.user.avatar}
                        gender={row.buddy.gender as 'male' | 'female' | null}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <div className="grid justify-items-start gap-1">
                    <StatusBadge status={row.status} />
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {t(':progress% done', { progress: row.progress })}
                    </span>
                </div>
            ),
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (row) => <DateCell value={row.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Candidate Onboarding')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Candidate Onboarding"
                    description="Onboard hired candidates with a checklist and track their tasks."
                    action={
                        can('create-candidate-onboarding') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Start Onboarding')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={candidateOnboarding}
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
                            name="candidate_id"
                            label="All Employees"
                            options={candidates}
                        />
                    }
                    actions={(onboarding) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link
                                    href={onboardingRoutes.show(onboarding.id)}
                                >
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-candidate-onboarding') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(onboarding)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-candidate-onboarding') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(onboarding)}
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
                title={editing ? 'Edit Onboarding' : 'Start Onboarding'}
                description={
                    editing
                        ? 'Changing the start date moves every task due date with it.'
                        : "The checklist's items are copied into this candidate's tasks."
                }
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? onboardingRoutes.update(editing.id)
                            : onboardingRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="onboarding-candidate">
                            {t('Hired Candidate')}
                            <span className="text-destructive">*</span>
                        </Label>
                        {editing ? (
                            <Input
                                id="onboarding-candidate"
                                disabled
                                value={name(editing)}
                            />
                        ) : (
                            <SelectField
                                id="onboarding-candidate"
                                required
                                value={form.data.candidate_id}
                                onChange={(e) =>
                                    form.setData('candidate_id', e.target.value)
                                }
                            >
                                <option value="">
                                    {t('Select Candidate')}
                                </option>
                                {candidates.map((candidate) => (
                                    <option
                                        key={candidate.id}
                                        value={candidate.id}
                                    >
                                        {candidate.name}
                                    </option>
                                ))}
                            </SelectField>
                        )}
                        <InputError message={form.errors.candidate_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="onboarding-checklist">
                            {t('Checklist')}
                            <span className="text-destructive">*</span>
                        </Label>
                        {editing ? (
                            <Input
                                id="onboarding-checklist"
                                disabled
                                value={editing.checklist?.name ?? '—'}
                            />
                        ) : (
                            <SelectField
                                id="onboarding-checklist"
                                required
                                value={form.data.checklist_id}
                                onChange={(e) =>
                                    form.setData('checklist_id', e.target.value)
                                }
                            >
                                <option value="">
                                    {t('Select Checklist')}
                                </option>
                                {checklists.map((checklist) => (
                                    <option
                                        key={checklist.id}
                                        value={checklist.id}
                                    >
                                        {checklist.name} (
                                        {t(':count items', {
                                            count: checklist.items_count,
                                        })}
                                        )
                                    </option>
                                ))}
                            </SelectField>
                        )}
                        <InputError message={form.errors.checklist_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="onboarding-start">
                            {t('Start Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="onboarding-start"
                            type="date"
                            required
                            value={form.data.start_date}
                            onChange={(e) =>
                                form.setData('start_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.start_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="onboarding-buddy">
                            {t('Buddy Employee')}
                        </Label>
                        <SelectField
                            id="onboarding-buddy"
                            value={form.data.buddy_employee_id}
                            onChange={(e) =>
                                form.setData(
                                    'buddy_employee_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('No buddy')}</option>
                            {buddyEmployees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name} ({employee.employee_id})
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.buddy_employee_id} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This onboarding and its tasks will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(onboardingRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

CandidateOnboarding.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: onboardingRoutes.index() },
        { title: 'Candidate Onboarding', href: onboardingRoutes.index() },
    ],
};
