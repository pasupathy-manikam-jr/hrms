import { Head, router, useForm } from '@inertiajs/react';
import {
    Briefcase,
    CalendarDays,
    CircleCheck,
    CircleX,
    Clock,
    CreditCard,
    Banknote,
    Eye,
    FileText,
    Plane,
    Plus,
    RefreshCw,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DocumentInput } from '@/components/document-input';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { StatCards } from '@/components/stat-cards';
import { StatusBadge } from '@/components/status-badge';
import { DocumentLink } from '@/components/table-cells';
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
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import tripRoutes from '@/routes/hr/trips';
import type { Paginated, TableFilters } from '@/types';

type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = { id: number; name: string; employee_id: string };

const STATUSES = ['planned', 'ongoing', 'completed', 'cancelled'] as const;

type Trip = {
    id: number;
    employee_id: number;
    purpose: string;
    destination: string;
    start_date: string;
    end_date: string;
    description: string | null;
    expected_outcomes: string | null;
    status: (typeof STATUSES)[number];
    advance_amount: string | null;
    advance_status: string | null;
    total_expenses: string | null;
    reimbursement_status: string | null;
    trip_report: string | null;
    approved_at: string | null;
    file_name: string | null;
    approver: { id: number; name: string } | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Person;
    };
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    employee_id: '' as number | string,
    purpose: '',
    destination: '',
    start_date: '',
    end_date: '',
    description: '',
    expected_outcomes: '',
    status: 'planned' as string,
    advance_amount: '',
    total_expenses: '',
    document: null as File | null,
};

type Finance = { trip: Trip; kind: 'advance' | 'expenses' };

export default function Trips({
    trips,
    employees,
    advanceStatuses,
    reimbursementStatuses,
    statusCounts,
    filters,
}: {
    trips: Paginated<Trip>;
    employees: EmployeeOption[];
    advanceStatuses: string[];
    reimbursementStatuses: string[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const can = useCan();
    const url = tripRoutes.index();
    const [editing, setEditing] = useState<Trip | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Trip | null>(null);
    const [viewing, setViewing] = useState<Trip | null>(null);
    const [statusFor, setStatusFor] = useState<Trip | null>(null);
    const [reportFor, setReportFor] = useState<Trip | null>(null);
    const [finance, setFinance] = useState<Finance | null>(null);
    const form = useForm(blank);
    const statusForm = useForm({ status: '' });
    const reportForm = useForm({ trip_report: '' });
    const financeForm = useForm({ amount: '', status: '' });
    const canDecideMoney = can('approve-trip-expenses');

    const openFinance = (trip: Trip, kind: Finance['kind']) => {
        financeForm.clearErrors();
        financeForm.setData(
            kind === 'advance'
                ? {
                      amount: trip.advance_amount ?? '',
                      status: trip.advance_status ?? 'requested',
                  }
                : {
                      amount: trip.total_expenses ?? '',
                      status: trip.reimbursement_status ?? 'pending',
                  },
        );
        setFinance({ trip, kind });
    };

    // Travellers may only request/submit; approvers can move money statuses on.
    const financeStatuses = finance
        ? (finance.kind === 'advance'
              ? advanceStatuses
              : reimbursementStatuses
          ).filter(
              (status) =>
                  canDecideMoney ||
                  status ===
                      (finance.kind === 'advance' ? 'requested' : 'pending'),
          )
        : [];

    const openForm = (trip: Trip | null) => {
        setEditing(trip);
        form.clearErrors();
        form.setData(
            trip
                ? {
                      employee_id: trip.employee_id,
                      purpose: trip.purpose,
                      destination: trip.destination,
                      start_date: trip.start_date,
                      end_date: trip.end_date,
                      description: trip.description ?? '',
                      expected_outcomes: trip.expected_outcomes ?? '',
                      status: trip.status,
                      advance_amount: trip.advance_amount ?? '',
                      total_expenses: trip.total_expenses ?? '',
                      document: null,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    /** Amount with its status badge underneath, like the demo's Advance / Expenses cells. */
    const amount = (value: string | null, status: string | null) =>
        value === null ? (
            '—'
        ) : (
            <div className="grid justify-items-start gap-1">
                <span className="font-medium whitespace-nowrap tabular-nums">
                    {money(Number(value))}
                </span>
                {status && <StatusBadge status={status} />}
            </div>
        );

    const columns: Column<Trip>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (r) => (
                <PersonCell
                    name={r.employee.user.name}
                    detail={r.employee.user.email}
                    src={r.employee.user.avatar}
                    gender={r.employee.gender}
                />
            ),
        },
        {
            key: 'destination',
            label: 'Destination',
            render: (r) => <span className="font-medium">{r.destination}</span>,
        },
        {
            key: 'start_date',
            label: 'Trip Period',
            sortable: true,
            render: (r) => (
                <div>
                    <span className="flex items-center gap-2 whitespace-nowrap">
                        <CalendarDays className="size-4 shrink-0 text-muted-foreground" />
                        {date(r.start_date)} – {date(r.end_date)}
                    </span>
                    <div className="ps-6 text-xs text-muted-foreground">
                        {t(':count Days', {
                            count:
                                Math.round(
                                    (Date.parse(r.end_date) -
                                        Date.parse(r.start_date)) /
                                        86_400_000,
                                ) + 1,
                        })}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (r) => <StatusBadge status={r.status} />,
        },
        {
            key: 'advance_amount',
            label: 'Advance',
            render: (r) => amount(r.advance_amount, r.advance_status),
        },
        {
            key: 'total_expenses',
            label: 'Expenses',
            sortable: true,
            render: (r) => amount(r.total_expenses, r.reimbursement_status),
        },
    ];

    const field = (
        name: Exclude<keyof typeof blank, 'document'>,
        label: string,
        props: React.ComponentProps<typeof Input> = {},
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`trip-${name}`}>
                {t(label)}
                {props.required && <span className="text-destructive">*</span>}
            </Label>
            <Input
                id={`trip-${name}`}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                {...props}
            />
            <InputError message={form.errors[name]} />
        </div>
    );

    const textarea = (
        name: 'description' | 'expected_outcomes',
        label: string,
    ) => (
        <div className="grid gap-2 sm:col-span-2">
            <Label htmlFor={`trip-${name}`}>{t(label)}</Label>
            <textarea
                id={`trip-${name}`}
                rows={3}
                className={textareaClass}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
            />
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <>
            <Head title={t('Trips')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Trips"
                    description="Manage business trips and travel requests for employees."
                    action={
                        can('create-trips') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Trip')}
                            </Button>
                        )
                    }
                />

                <StatCards
                    className="xl:grid-cols-5"
                    stats={[
                        {
                            label: 'Total Trips',
                            value: statusCounts.all ?? 0,
                            note: 'All business trips',
                            icon: Briefcase,
                            tone: 'bg-muted text-muted-foreground',
                        },
                        {
                            label: 'Planned',
                            value: statusCounts.planned ?? 0,
                            note: 'Upcoming trips',
                            icon: Plane,
                            tone: 'bg-blue-100 text-blue-600 dark:bg-blue-950',
                        },
                        {
                            label: 'Ongoing',
                            value: statusCounts.ongoing ?? 0,
                            note: 'Currently in progress',
                            icon: Clock,
                            tone: 'bg-amber-100 text-amber-600 dark:bg-amber-950',
                        },
                        {
                            label: 'Completed',
                            value: statusCounts.completed ?? 0,
                            note: 'Successfully completed',
                            icon: CircleCheck,
                            tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950',
                        },
                        {
                            label: 'Cancelled',
                            value: statusCounts.cancelled ?? 0,
                            note: 'Trips not completed',
                            icon: CircleX,
                            tone: 'bg-red-100 text-red-600 dark:bg-red-950',
                        },
                    ]}
                />

                <DataTable
                    data={trips}
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
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(trip) => {
                        const cancelled = trip.status === 'cancelled';

                        return (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(trip)}
                                >
                                    <Eye />
                                </Button>
                                {!cancelled && can('edit-trips') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(trip)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {can('approve-trips') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Change Status')}
                                        title={t('Change Status')}
                                        onClick={() => {
                                            statusForm.setData(
                                                'status',
                                                trip.status,
                                            );
                                            statusForm.clearErrors();
                                            setStatusFor(trip);
                                        }}
                                    >
                                        <RefreshCw />
                                    </Button>
                                )}
                                {!cancelled && can('manage-trip-expenses') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Travel Advance')}
                                        title={t('Travel Advance')}
                                        onClick={() =>
                                            openFinance(trip, 'advance')
                                        }
                                    >
                                        <Banknote />
                                    </Button>
                                )}
                                {!cancelled && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Trip Report')}
                                        title={t('Trip Report')}
                                        onClick={() => {
                                            reportForm.setData(
                                                'trip_report',
                                                trip.trip_report ?? '',
                                            );
                                            reportForm.clearErrors();
                                            setReportFor(trip);
                                        }}
                                    >
                                        <FileText />
                                    </Button>
                                )}
                                {!cancelled && can('manage-trip-expenses') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Expenses')}
                                        title={t('Expenses')}
                                        onClick={() =>
                                            openFinance(trip, 'expenses')
                                        }
                                    >
                                        <CreditCard />
                                    </Button>
                                )}
                                {can('delete-trips') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(trip)}
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
                title={editing ? 'Edit Trip' : 'Add Trip'}
                onSubmit={(e) => {
                    e.preventDefault();
                    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
                    form.post(
                        editing
                            ? tripRoutes.update.form(editing.id).action
                            : tripRoutes.store().url,
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
                submitLabel={editing ? 'Save' : 'Create'}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="trip-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="trip-employee"
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
                        <Label htmlFor="trip-status">
                            {t('Status')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="trip-status"
                            required
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {STATUSES.map((status) => (
                                <option key={status} value={status}>
                                    {t(
                                        status.charAt(0).toUpperCase() +
                                            status.slice(1),
                                    )}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    {field('purpose', 'Purpose', { required: true })}
                    {field('destination', 'Destination', { required: true })}
                    {field('start_date', 'Start Date', {
                        type: 'date',
                        required: true,
                    })}
                    {field('end_date', 'End Date', {
                        type: 'date',
                        required: true,
                        min: form.data.start_date || undefined,
                    })}
                    {field('advance_amount', 'Advance Amount', {
                        type: 'number',
                        min: 0,
                        step: '0.01',
                    })}
                    {field('total_expenses', 'Total Expenses', {
                        type: 'number',
                        min: 0,
                        step: '0.01',
                    })}
                    {textarea('description', 'Description')}
                    {textarea('expected_outcomes', 'Expected Outcomes')}
                    <DocumentInput
                        id="trip-document"
                        currentName={editing?.file_name}
                        hasNewFile={form.data.document !== null}
                        error={form.errors.document}
                        onChange={(file) => form.setData('document', file)}
                    />
                </div>
            </FormDialog>

            <FormDialog
                open={statusFor !== null}
                onOpenChange={(open) => !open && setStatusFor(null)}
                title="Change Status"
                description={statusFor?.destination}
                processing={statusForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (statusFor) {
                        statusForm.submit(
                            tripRoutes.changeStatus(statusFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setStatusFor(null),
                            },
                        );
                    }
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="trip-new-status">{t('Status')}</Label>
                    <SelectField
                        id="trip-new-status"
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData('status', e.target.value)
                        }
                    >
                        {STATUSES.map((status) => (
                            <option key={status} value={status}>
                                {t(
                                    status.charAt(0).toUpperCase() +
                                        status.slice(1),
                                )}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={statusForm.errors.status} />
                </div>
            </FormDialog>

            <FormDialog
                open={finance !== null}
                onOpenChange={(open) => !open && setFinance(null)}
                title={
                    finance?.kind === 'advance'
                        ? 'Travel Advance'
                        : 'Trip Expenses'
                }
                description={finance?.trip.destination}
                processing={financeForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (!finance) {
                        return;
                    }

                    const advance = finance.kind === 'advance';
                    financeForm.transform((data) =>
                        advance
                            ? {
                                  advance_amount: data.amount,
                                  advance_status: data.status,
                              }
                            : {
                                  total_expenses: data.amount,
                                  reimbursement_status: data.status,
                              },
                    );
                    financeForm.submit(
                        advance
                            ? tripRoutes.advance(finance.trip.id)
                            : tripRoutes.expenses(finance.trip.id),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFinance(null),
                        },
                    );
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="trip-finance-amount">
                            {t(
                                finance?.kind === 'advance'
                                    ? 'Advance Amount'
                                    : 'Total Expenses',
                            )}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="trip-finance-amount"
                            type="number"
                            min={0}
                            step="0.01"
                            required
                            value={financeForm.data.amount}
                            onChange={(e) =>
                                financeForm.setData('amount', e.target.value)
                            }
                        />
                        <InputError
                            message={
                                financeForm.errors.amount ??
                                (financeForm.errors as Record<string, string>)
                                    .advance_amount ??
                                (financeForm.errors as Record<string, string>)
                                    .total_expenses
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="trip-finance-status">
                            {t('Status')}
                        </Label>
                        <SelectField
                            id="trip-finance-status"
                            value={financeForm.data.status}
                            onChange={(e) =>
                                financeForm.setData('status', e.target.value)
                            }
                        >
                            {financeStatuses.map((status) => (
                                <option key={status} value={status}>
                                    {t(
                                        status.charAt(0).toUpperCase() +
                                            status.slice(1),
                                    )}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={reportFor !== null}
                onOpenChange={(open) => !open && setReportFor(null)}
                title="Trip Report"
                description={reportFor?.destination}
                processing={reportForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (reportFor) {
                        reportForm.submit(tripRoutes.report(reportFor.id), {
                            preserveScroll: true,
                            onSuccess: () => setReportFor(null),
                        });
                    }
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="trip-report">
                        {t('Report')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <textarea
                        id="trip-report"
                        rows={6}
                        required
                        className={textareaClass}
                        value={reportForm.data.trip_report}
                        onChange={(e) =>
                            reportForm.setData('trip_report', e.target.value)
                        }
                    />
                    <InputError message={reportForm.errors.trip_report} />
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{viewing?.destination}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            {(
                                [
                                    ['Employee', viewing.employee.user.name],
                                    ['Purpose', viewing.purpose],
                                    [
                                        'Trip Period',
                                        `${date(viewing.start_date)} – ${date(viewing.end_date)}`,
                                    ],
                                    ['Approved By', viewing.approver?.name],
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
                                    {t('Documents')}
                                </dt>
                                <dd>
                                    <DocumentLink
                                        href={tripRoutes.document.url(
                                            viewing.id,
                                        )}
                                        fileName={viewing.file_name}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Advance')}
                                </dt>
                                <dd>
                                    {amount(
                                        viewing.advance_amount,
                                        viewing.advance_status,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Expenses')}
                                </dt>
                                <dd>
                                    {amount(
                                        viewing.total_expenses,
                                        viewing.reimbursement_status,
                                    )}
                                </dd>
                            </div>
                            {(
                                [
                                    ['Description', viewing.description],
                                    [
                                        'Expected Outcomes',
                                        viewing.expected_outcomes,
                                    ],
                                    ['Trip Report', viewing.trip_report],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label} className="col-span-2">
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium whitespace-pre-line">
                                        {value || '—'}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This trip will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(tripRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Trips.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Lifecycle', href: tripRoutes.index() },
        { title: 'Trips', href: tripRoutes.index() },
    ],
};
