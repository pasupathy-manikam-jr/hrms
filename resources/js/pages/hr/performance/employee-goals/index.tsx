import { Head, router, useForm } from '@inertiajs/react';
import {
    ChartNoAxesColumnIncreasing,
    Eye,
    Plus,
    SquarePen,
    Tag,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell, IdBadge } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import goalRoutes from '@/routes/hr/performance/employee-goals';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type EmployeeOption = Option & { employee_id: string };

type Goal = {
    id: number;
    employee_id: number;
    goal_type_id: number;
    title: string;
    description: string | null;
    start_date: string;
    end_date: string;
    target: string | null;
    progress: number;
    status: string;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: {
            id: number;
            name: string;
            email: string;
            avatar: string | null;
        };
    };
    goal_type: Option;
};

const blank = {
    employee_id: '' as number | string,
    goal_type_id: '' as number | string,
    title: '',
    description: '',
    start_date: '',
    end_date: '',
    target: '',
    progress: 0 as number | string,
    status: 'not_started',
};

const label = (status: string) =>
    status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function EmployeeGoals({
    goals,
    employees,
    goalTypes,
    statuses,
    statusCounts,
    filters,
}: {
    goals: Paginated<Goal>;
    employees: EmployeeOption[];
    goalTypes: Option[];
    statuses: string[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = goalRoutes.index();
    const [editing, setEditing] = useState<Goal | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Goal | null>(null);
    const [viewing, setViewing] = useState<Goal | null>(null);
    const [progressFor, setProgressFor] = useState<Goal | null>(null);
    const progressForm = useForm({ progress: 0 as number | string });
    const { date } = useFormat();
    const form = useForm(blank);

    const openForm = (goal: Goal | null) => {
        setEditing(goal);
        form.clearErrors();
        form.setData(
            goal
                ? {
                      employee_id: goal.employee_id,
                      goal_type_id: goal.goal_type_id,
                      title: goal.title,
                      description: goal.description ?? '',
                      start_date: goal.start_date,
                      end_date: goal.end_date,
                      target: goal.target ?? '',
                      progress: goal.progress,
                      status: goal.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Goal>[] = [
        {
            key: 'title',
            label: 'Title',
            sortable: true,
            render: (g) => (
                <div className="grid justify-items-start gap-1">
                    <div className="font-medium">{g.title}</div>
                    <IdBadge>
                        <span className="flex items-center gap-1">
                            <Tag className="size-3" />
                            {g.goal_type.name}
                        </span>
                    </IdBadge>
                </div>
            ),
        },
        {
            key: 'employee',
            label: 'Employee',
            render: (g) => (
                <PersonCell
                    name={g.employee.user.name}
                    detail={g.employee.user.email}
                    src={g.employee.user.avatar}
                    gender={g.employee.gender}
                />
            ),
        },
        {
            key: 'start_date',
            label: 'Duration',
            sortable: true,
            render: (g) => (
                <div className="grid gap-1">
                    <DateCell value={g.start_date} />
                    <DateCell value={g.end_date} />
                </div>
            ),
        },
        {
            key: 'days',
            label: 'Days',
            render: (g) => (
                <span className="rounded-md border border-violet-200 bg-violet-50 px-2 py-0.5 text-xs font-medium whitespace-nowrap text-violet-700 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-300">
                    {Math.round(
                        (Date.parse(g.end_date) - Date.parse(g.start_date)) /
                            86_400_000,
                    )}
                    d
                </span>
            ),
        },
        {
            key: 'progress',
            label: 'Progress',
            render: (g) => (
                <div className="flex min-w-32 items-center gap-2">
                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-primary"
                            style={{ width: `${g.progress}%` }}
                        />
                    </div>
                    <span className="text-xs tabular-nums">{g.progress}%</span>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (g) => <StatusBadge status={g.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Employee Goals')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employee Goals"
                    description="Set and track performance goals for your employees."
                    action={
                        can('create-employee-goals') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Goal')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={goals}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            {employees.length > 0 && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="employee_id"
                                    label="All Employees"
                                    options={employees}
                                />
                            )}
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="goal_type_id"
                                label="All Goal Types"
                                options={goalTypes}
                            />
                        </>
                    }
                    actions={(goal) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(goal)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-employee-goals') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(goal)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Update Progress')}
                                        title={t('Update Progress')}
                                        onClick={() => {
                                            progressForm.setData(
                                                'progress',
                                                goal.progress,
                                            );
                                            progressForm.clearErrors();
                                            setProgressFor(goal);
                                        }}
                                    >
                                        <ChartNoAxesColumnIncreasing />
                                    </Button>
                                </>
                            )}
                            {can('delete-employee-goals') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(goal)}
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
                title={editing ? 'Edit Employee Goal' : 'Add New Employee Goal'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? goalRoutes.update(editing.id)
                            : goalRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="goal-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="goal-employee"
                            required
                            value={form.data.employee_id}
                            onChange={(e) =>
                                form.setData('employee_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Employee')}</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name} ({employee.employee_id})
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.employee_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="goal-type">
                            {t('Goal Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="goal-type"
                            required
                            value={form.data.goal_type_id}
                            onChange={(e) =>
                                form.setData('goal_type_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Goal Type')}</option>
                            {goalTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.goal_type_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="goal-title">
                            {t('Goal Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="goal-title"
                            placeholder={t(
                                'e.g. Improve Customer Satisfaction',
                            )}
                            required
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="goal-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="goal-description"
                            placeholder={t(
                                'e.g. Achieve a customer satisfaction score of 90% or above...',
                            )}
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
                        <Label htmlFor="goal-start">
                            {t('Start Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="goal-start"
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
                        <Label htmlFor="goal-end">
                            {t('End Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="goal-end"
                            type="date"
                            required
                            min={form.data.start_date || undefined}
                            value={form.data.end_date}
                            onChange={(e) =>
                                form.setData('end_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.end_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="goal-target">{t('Target')}</Label>
                        <Input
                            id="goal-target"
                            placeholder={t(
                                'e.g. Complete 5 projects, Achieve 95% accuracy, etc.',
                            )}
                            value={form.data.target}
                            onChange={(e) =>
                                form.setData('target', e.target.value)
                            }
                        />
                        <InputError message={form.errors.target} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="goal-progress">
                            {t('Progress (%)')}
                        </Label>
                        <Input
                            id="goal-progress"
                            type="number"
                            min={0}
                            max={100}
                            required
                            value={form.data.progress}
                            onChange={(e) =>
                                form.setData('progress', e.target.value)
                            }
                        />
                        <InputError message={form.errors.progress} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="goal-status">
                            {t('Status')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="goal-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {t(label(status))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={progressFor !== null}
                onOpenChange={(open) => !open && setProgressFor(null)}
                title="Update Goal Progress"
                description={progressFor?.title}
                processing={progressForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (progressFor) {
                        progressForm.submit(
                            goalRoutes.progress(progressFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setProgressFor(null),
                            },
                        );
                    }
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="goal-progress-value">
                        {t('Progress (%)')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="goal-progress-value"
                        type="number"
                        min={0}
                        max={100}
                        required
                        value={progressForm.data.progress}
                        onChange={(e) =>
                            progressForm.setData('progress', e.target.value)
                        }
                    />
                    <InputError message={progressForm.errors.progress} />
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Employee Goal Details')}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            {(
                                [
                                    ['Goal Title', viewing.title],
                                    ['Employee', viewing.employee.user.name],
                                    ['Goal Type', viewing.goal_type.name],
                                    ['Target', viewing.target],
                                    ['Start Date', date(viewing.start_date)],
                                    ['End Date', date(viewing.end_date)],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium">
                                        {value || '—'}
                                    </dd>
                                </div>
                            ))}
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.status} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Progress')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.progress}%
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Description')}
                                </dt>
                                <dd className="font-medium whitespace-pre-line">
                                    {viewing.description || '—'}
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This goal will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(goalRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

EmployeeGoals.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: goalRoutes.index() },
        { title: 'Employee Goals', href: goalRoutes.index() },
    ],
};
