import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleCheckBig,
    CircleX,
    Eye,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { ExportButton } from '@/components/import-export';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import {
    applyFilters,
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
import { addDays, pad, parseYmd, ymd } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import leaveApplicationRoutes from '@/routes/hr/leave-applications';
import type { Paginated, TableFilters } from '@/types';

type LeaveTypeOption = {
    id: number;
    name: string;
    color: string;
    is_paid?: boolean;
};
type EmployeeOption = { id: number; name: string; employee_id: string };

type LeaveApplication = {
    id: number;
    employee_id: number;
    leave_type_id: number;
    start_date: string;
    end_date: string;
    total_days: number;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected';
    manager_comments: string | null;
    approved_at: string | null;
    created_at: string;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | null;
        user: {
            id: number;
            name: string;
            email: string;
            avatar: string | null;
        };
    };
    leave_type: LeaveTypeOption;
    leave_policy: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
};

type CalendarRow = {
    id: number;
    name: string;
    avatar: string | null;
    gender: 'male' | 'female' | null;
    designation: string | null;
};

type Review = { application: LeaveApplication; action: 'approve' | 'reject' };

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    employee_id: '' as number | string,
    leave_type_id: '' as number | string,
    start_date: '',
    end_date: '',
    reason: '',
};

function LeaveTypeName({ type }: { type: LeaveTypeOption }) {
    return (
        <span className="flex items-center gap-2">
            <span
                className="size-3 shrink-0 rounded-full"
                style={{ backgroundColor: type.color }}
            />
            {type.name}
        </span>
    );
}

/** The demo's week view: one row per employee, approved leave drawn as bars in its leave type's colour. */
function LeaveWeek({
    weekStart,
    rows,
    leaves,
    leaveTypes,
    employees,
    filters,
    onView,
}: {
    weekStart: string;
    rows: CalendarRow[];
    leaves: LeaveApplication[];
    leaveTypes: LeaveTypeOption[];
    employees: EmployeeOption[];
    filters: TableFilters;
    onView: (application: LeaveApplication) => void;
}) {
    const { t } = useTranslation();
    const url = leaveApplicationRoutes.index();
    const monday = parseYmd(weekStart);
    const days = Array.from({ length: 7 }, (_, i) => ymd(addDays(monday, i)));
    const today = ymd(new Date());
    const goToWeek = (offset: number) =>
        applyFilters(url, filters, {
            week_start: ymd(addDays(monday, offset * 7)),
        });
    const columns = 'grid grid-cols-[16rem_repeat(7,minmax(7.5rem,1fr))]';

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4 shadow-sm">
                <div className="flex items-center gap-4">
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label={t('Previous week')}
                        onClick={() => goToWeek(-1)}
                    >
                        <ChevronLeft />
                    </Button>
                    <span className="min-w-36 text-center text-lg font-semibold">
                        {monday.toLocaleDateString(undefined, {
                            month: 'long',
                            year: 'numeric',
                        })}
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label={t('Next week')}
                        onClick={() => goToWeek(1)}
                    >
                        <ChevronRight />
                    </Button>
                </div>
                {employees.length > 0 && (
                    <label className="flex items-center gap-3 text-sm">
                        {t('Employee')}
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="calendar_employee_id"
                            label="All Employees"
                            options={employees}
                        />
                    </label>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-4 text-xs font-medium shadow-sm">
                <span className="me-1 text-sm">{t('Legend')}:</span>
                <span className="flex items-center gap-1.5 rounded-md border px-2 py-1">
                    <span className="size-2 rounded-full border border-emerald-500 bg-emerald-100" />
                    {t('Today')}
                </span>
                {leaveTypes.map((type) => (
                    <span
                        key={type.id}
                        className="flex items-center gap-1.5 rounded-md border px-2 py-1"
                        style={{ color: type.color }}
                    >
                        <span
                            className="size-2 rounded-full"
                            style={{ backgroundColor: type.color }}
                        />
                        {type.name}
                    </span>
                ))}
            </div>

            <div className="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <div className="min-w-max">
                    <div
                        className={cn(
                            columns,
                            'border-b bg-muted/60 text-sm text-muted-foreground',
                        )}
                    >
                        <div className="px-4 py-3 font-medium">
                            {t('Employee')}
                        </div>
                        {days.map((day) => {
                            const d = parseYmd(day);

                            return (
                                <div
                                    key={day}
                                    className={cn(
                                        'border-s py-2 text-center',
                                        day === today &&
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                                    )}
                                >
                                    <div className="font-semibold">
                                        {pad(d.getDate())}
                                    </div>
                                    <div className="text-xs">
                                        {d.toLocaleDateString(undefined, {
                                            weekday: 'short',
                                        })}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    {rows.map((row) => (
                        <div
                            key={row.id}
                            className={cn(columns, 'border-b last:border-b-0')}
                        >
                            <div className="row-start-1 px-4 py-3">
                                <PersonCell
                                    name={row.name}
                                    detail={row.designation}
                                    src={row.avatar}
                                    gender={row.gender}
                                />
                            </div>
                            {days.map((day, i) => (
                                <div
                                    key={day}
                                    style={{ gridColumn: i + 2 }}
                                    className={cn(
                                        'row-start-1 border-s',
                                        day === today &&
                                            'bg-emerald-50/60 dark:bg-emerald-950/40',
                                    )}
                                />
                            ))}
                            {leaves
                                .filter((leave) => leave.employee_id === row.id)
                                .map((leave) => {
                                    // Clamp the leave to this week's columns.
                                    const from = Math.max(
                                        0,
                                        days.findIndex(
                                            (d) => d >= leave.start_date,
                                        ),
                                    );
                                    const last = days.findLastIndex(
                                        (d) => d <= leave.end_date,
                                    );
                                    const color = leave.leave_type.color;

                                    return (
                                        <button
                                            key={leave.id}
                                            type="button"
                                            onClick={() => onView(leave)}
                                            style={{
                                                gridColumn: `${from + 2} / ${last + 3}`,
                                                color,
                                                borderColor: `${color}66`,
                                                // Tint over the card colour so the today column doesn't show through.
                                                backgroundImage: `linear-gradient(${color}1a, ${color}1a)`,
                                            }}
                                            className="row-start-1 m-1 rounded-lg border bg-card px-3 py-2 text-start hover:opacity-80"
                                        >
                                            <div className="truncate text-sm font-semibold">
                                                {leave.leave_type.name}
                                            </div>
                                            <div className="truncate text-xs">
                                                {t(
                                                    leave.leave_type.is_paid
                                                        ? 'Paid Leave'
                                                        : 'Unpaid Leave',
                                                )}
                                            </div>
                                        </button>
                                    );
                                })}
                        </div>
                    ))}
                    {rows.length === 0 && (
                        <div className="px-4 py-12 text-center text-muted-foreground">
                            {t('No employees found')}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

export default function LeaveApplications({
    weekStart,
    calendarRows,
    calendarLeaves,
    leaveApplications,
    employees,
    leaveTypes,
    statusCounts,
    filters,
}: {
    weekStart: string;
    calendarRows: CalendarRow[];
    calendarLeaves: LeaveApplication[];
    leaveApplications: Paginated<LeaveApplication>;
    employees: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const url = leaveApplicationRoutes.index();
    const canApplyForOthers = can('manage-any-leave-applications');
    const [editing, setEditing] = useState<LeaveApplication | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [viewing, setViewing] = useState<LeaveApplication | null>(null);
    const [deleting, setDeleting] = useState<LeaveApplication | null>(null);
    const [reviewing, setReviewing] = useState<Review | null>(null);
    const form = useForm(blank);
    const reviewForm = useForm({ manager_comments: '' });

    const openForm = (application: LeaveApplication | null) => {
        setEditing(application);
        form.clearErrors();
        form.setData(
            application
                ? {
                      employee_id: application.employee_id,
                      leave_type_id: application.leave_type_id,
                      start_date: application.start_date,
                      end_date: application.end_date,
                      reason: application.reason ?? '',
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const openReview = (review: Review) => {
        reviewForm.reset();
        reviewForm.clearErrors();
        setReviewing(review);
    };

    const columns: Column<LeaveApplication>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (a) => (
                <PersonCell
                    name={a.employee.user.name}
                    detail={a.employee.user.email}
                    src={a.employee.user.avatar}
                    gender={a.employee.gender}
                />
            ),
        },
        {
            key: 'leave_type',
            label: 'Leave Type',
            render: (a) => <LeaveTypeName type={a.leave_type} />,
        },
        {
            key: 'start_date',
            label: 'Start Date',
            sortable: true,
            render: (a) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(a.start_date)}
                </span>
            ),
        },
        {
            key: 'end_date',
            label: 'End Date',
            sortable: true,
            render: (a) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(a.end_date)}
                </span>
            ),
        },
        {
            key: 'total_days',
            label: 'Days',
            sortable: true,
            render: (a) => a.total_days,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (a) => <StatusBadge status={a.status} />,
        },
        {
            key: 'created_at',
            label: 'Applied On',
            sortable: true,
            render: (a) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(a.created_at)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Leave Applications')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Leave Applications"
                    description="View and manage leave applications."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {can('export-leave-applications') && (
                                <ExportButton
                                    href={leaveApplicationRoutes.export({
                                        query: filters,
                                    })}
                                />
                            )}
                            {can('create-leave-applications') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Add Leave Application')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <LeaveWeek
                    weekStart={weekStart}
                    rows={calendarRows}
                    leaves={calendarLeaves}
                    leaveTypes={leaveTypes}
                    employees={employees}
                    filters={filters}
                    onView={setViewing}
                />

                <DataTable
                    data={leaveApplications}
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
                                name="leave_type_id"
                                label="All Leave Types"
                                options={leaveTypes}
                            />
                        </>
                    }
                    actions={(application) => {
                        const pending = application.status === 'pending';

                        return (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(application)}
                                >
                                    <Eye />
                                </Button>
                                {pending &&
                                    can('approve-leave-applications') && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Approve')}
                                            onClick={() =>
                                                openReview({
                                                    application,
                                                    action: 'approve',
                                                })
                                            }
                                        >
                                            <CircleCheckBig className="text-emerald-600" />
                                        </Button>
                                    )}
                                {pending &&
                                    can('reject-leave-applications') && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Reject')}
                                            onClick={() =>
                                                openReview({
                                                    application,
                                                    action: 'reject',
                                                })
                                            }
                                        >
                                            <CircleX className="text-destructive" />
                                        </Button>
                                    )}
                                {pending && can('edit-leave-applications') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(application)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {(pending || canApplyForOthers) &&
                                    can('delete-leave-applications') && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Delete')}
                                            onClick={() =>
                                                setDeleting(application)
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                            </>
                        );
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Leave Application' : 'Apply Leave'}
                description="Leave days are counted on working days only."
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? leaveApplicationRoutes.update(editing.id)
                            : leaveApplicationRoutes.store(),
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
                    {canApplyForOthers && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="leave-employee">
                                {t('Employee')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="leave-employee"
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
                        <Label htmlFor="leave-type">
                            {t('Leave Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="leave-type"
                            required
                            value={form.data.leave_type_id}
                            onChange={(e) =>
                                form.setData('leave_type_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Leave Type')}</option>
                            {leaveTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.leave_type_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="leave-start">
                            {t('Start Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="leave-start"
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
                        <Label htmlFor="leave-end">
                            {t('End Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="leave-end"
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
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="leave-reason">{t('Reason')}</Label>
                        <textarea
                            id="leave-reason"
                            rows={3}
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
                open={reviewing !== null}
                onOpenChange={(open) => !open && setReviewing(null)}
                title={
                    reviewing?.action === 'approve'
                        ? 'Approve Leave Application'
                        : 'Reject Leave Application'
                }
                onSubmit={(e) => {
                    e.preventDefault();

                    if (!reviewing) {
                        return;
                    }

                    const id = reviewing.application.id;
                    reviewForm.submit(
                        reviewing.action === 'approve'
                            ? leaveApplicationRoutes.approve(id)
                            : leaveApplicationRoutes.reject(id),
                        {
                            preserveScroll: true,
                            onSuccess: () => setReviewing(null),
                        },
                    );
                }}
                processing={reviewForm.processing}
                submitLabel={
                    reviewing?.action === 'approve' ? 'Approve' : 'Reject'
                }
            >
                {reviewing && (
                    <p className="text-sm text-muted-foreground">
                        {reviewing.application.employee.user.name} ·{' '}
                        {reviewing.application.leave_type.name} ·{' '}
                        {date(reviewing.application.start_date)} –{' '}
                        {date(reviewing.application.end_date)} (
                        {t(':days days', {
                            days: reviewing.application.total_days,
                        })}
                        )
                    </p>
                )}
                <div className="grid gap-2">
                    <Label htmlFor="leave-comments">
                        {t('Manager Comments')}
                    </Label>
                    <textarea
                        id="leave-comments"
                        rows={3}
                        className={textareaClass}
                        value={reviewForm.data.manager_comments}
                        onChange={(e) =>
                            reviewForm.setData(
                                'manager_comments',
                                e.target.value,
                            )
                        }
                    />
                    <InputError message={reviewForm.errors.manager_comments} />
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Leave Application')}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Employee')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.employee.user.name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Leave Type')}
                                </dt>
                                <dd className="font-medium">
                                    <LeaveTypeName type={viewing.leave_type} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Duration')}
                                </dt>
                                <dd className="font-medium">
                                    {date(viewing.start_date)} –{' '}
                                    {date(viewing.end_date)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Days')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.total_days}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Leave Policy')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.leave_policy?.name ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.status} />
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Reason')}
                                </dt>
                                <dd className="font-medium whitespace-pre-line">
                                    {viewing.reason || '—'}
                                </dd>
                            </div>
                            {viewing.status !== 'pending' && (
                                <>
                                    <div className="col-span-2">
                                        <dt className="text-muted-foreground">
                                            {t('Manager Comments')}
                                        </dt>
                                        <dd className="font-medium whitespace-pre-line">
                                            {viewing.manager_comments || '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            {t(
                                                viewing.status === 'approved'
                                                    ? 'Approved By'
                                                    : 'Rejected By',
                                            )}
                                        </dt>
                                        <dd className="font-medium">
                                            {viewing.approver?.name ?? '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            {t('Date')}
                                        </dt>
                                        <dd className="font-medium">
                                            {viewing.approved_at
                                                ? date(viewing.approved_at)
                                                : '—'}
                                        </dd>
                                    </div>
                                </>
                            )}
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This leave application will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(leaveApplicationRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

LeaveApplications.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Management', href: leaveApplicationRoutes.index() },
        { title: 'Leave Applications', href: leaveApplicationRoutes.index() },
    ],
};
