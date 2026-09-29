import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Check,
    CircleCheck,
    CircleX,
    Clock,
    Eye,
    MessageSquare,
    Plus,
    SquarePen,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatCards } from '@/components/stat-cards';
import { StatusBadge } from '@/components/status-badge';
import {
    DateRangeFilter,
    FilterSelect,
    StatusTabs,
} from '@/components/table-filters';
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
import { PersonCell } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import regularizationRoutes from '@/routes/hr/attendance-regularizations';
import type { Paginated, TableFilters } from '@/types';

type EmployeeOption = { id: number; name: string; employee_id: string };

type Regularization = {
    id: number;
    employee_id: number;
    date: string;
    requested_clock_in: string;
    requested_clock_out: string | null;
    original_clock_in: string | null;
    original_clock_out: string | null;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    manager_comments: string | null;
    created_at: string;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | null;
        user: { id: number; name: string; avatar: string | null };
    };
    approver: { id: number; name: string } | null;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    employee_id: '' as number | string,
    date: '',
    requested_clock_in: '',
    requested_clock_out: '',
    reason: '',
};

function TimeLine({ value, tone }: { value: string | null; tone: string }) {
    const { time } = useFormat();

    return (
        <div className={cn('flex items-center gap-1 font-mono', tone)}>
            <Clock className="size-3.5" />
            {value ? time(value) : '--:--'}
        </div>
    );
}

export default function AttendanceRegularizations({
    regularizations,
    employees,
    statusCounts,
    filters,
}: {
    regularizations: Paginated<Regularization>;
    employees: EmployeeOption[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const can = useCan();
    const url = regularizationRoutes.index();
    const isStaff = can('manage-any-attendance-regularizations');
    const [editing, setEditing] = useState<Regularization | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Regularization | null>(null);
    const [viewing, setViewing] = useState<Regularization | null>(null);
    const [deciding, setDeciding] = useState<{
        request: Regularization;
        decision: 'approve' | 'reject';
    } | null>(null);
    const form = useForm(blank);
    const decisionForm = useForm({ manager_comments: '' });

    const span = (from: string | null, to: string | null) =>
        from ? `${time(from)} - ${to ? time(to) : '…'}` : '-';

    const openForm = (request: Regularization | null) => {
        setEditing(request);
        form.clearErrors();
        form.setData(
            request
                ? {
                      employee_id: request.employee_id,
                      date: request.date,
                      requested_clock_in: request.requested_clock_in.slice(
                          0,
                          5,
                      ),
                      requested_clock_out:
                          request.requested_clock_out?.slice(0, 5) ?? '',
                      reason: request.reason,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const openDecision = (
        request: Regularization,
        decision: 'approve' | 'reject',
    ) => {
        decisionForm.reset();
        decisionForm.clearErrors();
        setDeciding({ request, decision });
    };

    return (
        <>
            <Head title={t('Attendance Regularizations')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Attendance Regularizations"
                    description="Review and manage attendance regularization requests."
                    action={
                        can('create-attendance-regularizations') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Request')}
                            </Button>
                        )
                    }
                />

                <StatCards
                    stats={[
                        {
                            label: 'Total Requests',
                            value: statusCounts.all ?? 0,
                            note: 'All time',
                            icon: CalendarDays,
                            tone: 'bg-muted text-muted-foreground',
                        },
                        {
                            label: 'Pending',
                            value: statusCounts.pending ?? 0,
                            note: 'Needs Review',
                            icon: Clock,
                            tone: 'bg-amber-100 text-amber-600 dark:bg-amber-950',
                        },
                        {
                            label: 'Approved',
                            value: statusCounts.approved ?? 0,
                            note: t(':rate% rate', {
                                rate: statusCounts.all
                                    ? (
                                          (statusCounts.approved /
                                              statusCounts.all) *
                                          100
                                      ).toFixed(1)
                                    : '0.0',
                            }),
                            icon: CircleCheck,
                            tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950',
                        },
                        {
                            label: 'Rejected',
                            value: statusCounts.rejected ?? 0,
                            note: 'Declined',
                            icon: CircleX,
                            tone: 'bg-red-100 text-red-600 dark:bg-red-950',
                        },
                    ]}
                />

                <DataTable
                    cardsOnly
                    renderCard={(r, actions) => (
                        <div className="flex h-full flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm">
                            <div className="flex items-start justify-between gap-2">
                                <PersonCell
                                    name={r.employee.user.name}
                                    detail={r.employee.employee_id}
                                    src={r.employee.user.avatar}
                                    gender={r.employee.gender}
                                />
                                <StatusBadge status={r.status} />
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-sm whitespace-nowrap">
                                    {date(r.date)}
                                </span>
                                {actions}
                            </div>
                            <div className="flex items-center justify-between rounded-lg bg-muted/50 p-3 text-sm">
                                <div>
                                    <div className="mb-1 text-muted-foreground">
                                        {t('Original')}
                                    </div>
                                    <TimeLine
                                        value={r.original_clock_in}
                                        tone="text-red-600"
                                    />
                                    <TimeLine
                                        value={r.original_clock_out}
                                        tone="text-red-600"
                                    />
                                </div>
                                <ArrowRight className="size-4 text-muted-foreground rtl:rotate-180" />
                                <div className="text-end">
                                    <div className="mb-1 text-muted-foreground">
                                        {t('Requested')}
                                    </div>
                                    <TimeLine
                                        value={r.requested_clock_in}
                                        tone="text-emerald-600"
                                    />
                                    <TimeLine
                                        value={r.requested_clock_out}
                                        tone="text-emerald-600"
                                    />
                                </div>
                            </div>
                            <div className="flex-1 text-sm">
                                <div className="flex items-center gap-1.5 text-muted-foreground">
                                    <MessageSquare className="size-4" />
                                    {t('Reason')}
                                </div>
                                <p className="mt-1 line-clamp-3">{r.reason}</p>
                            </div>
                            <div className="flex items-center gap-1.5 border-t pt-3 text-xs text-muted-foreground">
                                <CalendarDays className="size-3.5" />
                                {t('Requested on')}: {date(r.created_at)}
                            </div>
                        </div>
                    )}
                    data={regularizations}
                    columns={[]}
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
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(request) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(request)}
                            >
                                <Eye />
                            </Button>
                            {request.status === 'pending' && (
                                <>
                                    {can(
                                        'approve-attendance-regularizations',
                                    ) && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Approve')}
                                            onClick={() =>
                                                openDecision(request, 'approve')
                                            }
                                        >
                                            <Check className="text-emerald-600" />
                                        </Button>
                                    )}
                                    {can(
                                        'reject-attendance-regularizations',
                                    ) && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Reject')}
                                            onClick={() =>
                                                openDecision(request, 'reject')
                                            }
                                        >
                                            <X className="text-destructive" />
                                        </Button>
                                    )}
                                    {can('edit-attendance-regularizations') && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edit')}
                                            onClick={() => openForm(request)}
                                        >
                                            <SquarePen />
                                        </Button>
                                    )}
                                    {can(
                                        'delete-attendance-regularizations',
                                    ) && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Delete')}
                                            onClick={() => setDeleting(request)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </>
                            )}
                        </>
                    )}
                />
            </div>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Regularization Request')}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <div className="grid gap-4 text-sm">
                            <div className="flex items-start justify-between gap-2">
                                <PersonCell
                                    name={viewing.employee.user.name}
                                    detail={viewing.employee.employee_id}
                                    src={viewing.employee.user.avatar}
                                    gender={viewing.employee.gender}
                                />
                                <StatusBadge status={viewing.status} />
                            </div>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                                {(
                                    [
                                        ['Date', date(viewing.date)],
                                        [
                                            'Requested on',
                                            date(viewing.created_at),
                                        ],
                                        [
                                            'Original',
                                            span(
                                                viewing.original_clock_in,
                                                viewing.original_clock_out,
                                            ),
                                        ],
                                        [
                                            'Requested',
                                            span(
                                                viewing.requested_clock_in,
                                                viewing.requested_clock_out,
                                            ),
                                        ],
                                        ['Reason', viewing.reason],
                                        ['Decided by', viewing.approver?.name],
                                        [
                                            'Manager Comments',
                                            viewing.manager_comments,
                                        ],
                                    ] as const
                                ).map(([label, value]) => (
                                    <div
                                        key={label}
                                        className={
                                            label === 'Reason' ||
                                            label === 'Manager Comments'
                                                ? 'col-span-2'
                                                : undefined
                                        }
                                    >
                                        <dt className="text-muted-foreground">
                                            {t(label)}
                                        </dt>
                                        <dd className="font-medium">
                                            {value || '-'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={
                    editing ? 'Edit Regularization' : 'Request Regularization'
                }
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? regularizationRoutes.update(editing.id)
                            : regularizationRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
                submitLabel={editing ? 'Save' : 'Submit'}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {isStaff && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="regularization-employee">
                                {t('Employee')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="regularization-employee"
                                required
                                value={form.data.employee_id}
                                onChange={(e) =>
                                    form.setData('employee_id', e.target.value)
                                }
                            >
                                <option value="">{t('Select Employee')}</option>
                                {employees.map((employee) => (
                                    <option
                                        key={employee.id}
                                        value={employee.id}
                                    >
                                        {employee.name} ({employee.employee_id})
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={form.errors.employee_id} />
                        </div>
                    )}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="regularization-date">
                            {t('Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="regularization-date"
                            type="date"
                            required
                            value={form.data.date}
                            onChange={(e) =>
                                form.setData('date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="regularization-in">
                            {t('Requested Clock In')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="regularization-in"
                            type="time"
                            required
                            value={form.data.requested_clock_in}
                            onChange={(e) =>
                                form.setData(
                                    'requested_clock_in',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.requested_clock_in} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="regularization-out">
                            {t('Requested Clock Out')}
                        </Label>
                        <Input
                            id="regularization-out"
                            type="time"
                            value={form.data.requested_clock_out}
                            onChange={(e) =>
                                form.setData(
                                    'requested_clock_out',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.requested_clock_out} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="regularization-reason">
                            {t('Reason')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="regularization-reason"
                            rows={3}
                            required
                            className={textareaClass}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={deciding !== null}
                onOpenChange={(open) => !open && setDeciding(null)}
                title={
                    deciding?.decision === 'reject'
                        ? 'Reject Regularization'
                        : 'Approve Regularization'
                }
                description={
                    deciding?.decision === 'approve'
                        ? 'Approving updates the attendance record for that day.'
                        : deciding?.request.employee.user.name
                }
                onSubmit={(e) => {
                    e.preventDefault();

                    if (deciding) {
                        decisionForm.submit(
                            regularizationRoutes[deciding.decision](
                                deciding.request.id,
                            ),
                            {
                                preserveScroll: true,
                                onSuccess: () => setDeciding(null),
                            },
                        );
                    }
                }}
                processing={decisionForm.processing}
                submitLabel={
                    deciding?.decision === 'reject' ? 'Reject' : 'Approve'
                }
            >
                <div className="grid gap-2">
                    <Label htmlFor="regularization-comments">
                        {t('Manager Comments')}
                    </Label>
                    <textarea
                        id="regularization-comments"
                        rows={3}
                        className={textareaClass}
                        value={decisionForm.data.manager_comments}
                        onChange={(e) =>
                            decisionForm.setData(
                                'manager_comments',
                                e.target.value,
                            )
                        }
                    />
                    <InputError
                        message={
                            decisionForm.errors.manager_comments ??
                            (decisionForm.errors as Record<string, string>)
                                .status
                        }
                    />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This regularization request will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(regularizationRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

AttendanceRegularizations.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: regularizationRoutes.index() },
        {
            title: 'Attendance Regularizations',
            href: regularizationRoutes.index(),
        },
    ],
};
