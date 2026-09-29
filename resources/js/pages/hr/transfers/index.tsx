import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowRight,
    Building2,
    CalendarDays,
    CircleCheckBig,
    CircleX,
    Eye,
    FileText,
    GitBranch,
    Lock,
    Plus,
    SquarePen,
    Trash2,
    Briefcase,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DocumentInput } from '@/components/document-input';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { DateCell, DocumentLink, IdBadge } from '@/components/table-cells';
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
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import transferRoutes from '@/routes/hr/transfers';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = Option & { employee_id: string };

type Transfer = {
    id: number;
    employee_id: number;
    to_branch_id: number;
    to_department_id: number;
    to_designation_id: number;
    transfer_date: string;
    effective_date: string;
    reason: string | null;
    notes: string | null;
    status: 'pending' | 'approved' | 'rejected';
    file_name: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Person;
    };
    from_branch: Option | null;
    to_branch: Option;
    from_department: Option | null;
    to_department: Option;
    from_designation: Option | null;
    to_designation: Option;
};

type Decision = { transfer: Transfer; action: 'approve' | 'reject' };

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    employee_id: '' as number | string,
    to_branch_id: '' as number | string,
    to_department_id: '' as number | string,
    to_designation_id: '' as number | string,
    transfer_date: '',
    effective_date: '',
    reason: '',
    notes: '',
    document: null as File | null,
};

function Move({
    label,
    from,
    to,
}: {
    label: string;
    from: Option | null;
    to: Option;
}) {
    return (
        <div className="flex items-center gap-1.5 text-xs whitespace-nowrap">
            <IdBadge>{label}</IdBadge>
            <span className="text-muted-foreground">{from?.name ?? '—'}</span>
            <ArrowRight className="size-3 text-muted-foreground" />
            <span className="font-medium">{to.name}</span>
        </div>
    );
}

/** One "From → To" pair in the details popup, like the demo. */
function Change({
    icon: Icon,
    label,
    from,
    to,
}: {
    icon: LucideIcon;
    label: string;
    from: Option | null;
    to: Option;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                <Icon className="size-3.5" /> {t(label)}
            </div>
            <div className="grid items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/40">
                    <div className="text-xs text-muted-foreground">
                        {t('From')}
                    </div>
                    <div className="font-semibold">{from?.name ?? '—'}</div>
                </div>
                <ArrowRight className="mx-auto size-4 text-emerald-600 max-sm:rotate-90" />
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900 dark:bg-emerald-950/40">
                    <div className="text-xs text-muted-foreground">
                        {t('To')}
                    </div>
                    <div className="font-semibold">{to.name}</div>
                </div>
            </div>
        </div>
    );
}

export default function Transfers({
    transfers,
    employees,
    branches,
    departments,
    designations,
    statusCounts,
    filters,
}: {
    transfers: Paginated<Transfer>;
    employees: EmployeeOption[];
    branches: Option[];
    departments: (Option & { branch_id: number })[];
    designations: (Option & { department_id: number })[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = transferRoutes.index();
    const [editing, setEditing] = useState<Transfer | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Transfer | null>(null);
    const [deciding, setDeciding] = useState<Decision | null>(null);
    const decisionForm = useForm({ notes: '' });
    const [viewing, setViewing] = useState<Transfer | null>(null);
    const { date } = useFormat();
    const form = useForm(blank);

    const openForm = (transfer: Transfer | null) => {
        setEditing(transfer);
        form.clearErrors();
        form.setData(
            transfer
                ? {
                      employee_id: transfer.employee_id,
                      to_branch_id: transfer.to_branch_id,
                      to_department_id: transfer.to_department_id,
                      to_designation_id: transfer.to_designation_id,
                      transfer_date: transfer.transfer_date,
                      effective_date: transfer.effective_date,
                      reason: transfer.reason ?? '',
                      notes: transfer.notes ?? '',
                      document: null,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Transfer>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (tr) => (
                <PersonCell
                    name={tr.employee.user.name}
                    detail={tr.employee.user.email}
                    src={tr.employee.user.avatar}
                    gender={tr.employee.gender}
                />
            ),
        },
        {
            key: 'from_to',
            label: 'From → To',
            render: (tr) => (
                <div className="grid gap-1">
                    <Move
                        label={t('Branch')}
                        from={tr.from_branch}
                        to={tr.to_branch}
                    />
                    <Move
                        label={t('Dept')}
                        from={tr.from_department}
                        to={tr.to_department}
                    />
                    <Move
                        label={t('Role')}
                        from={tr.from_designation}
                        to={tr.to_designation}
                    />
                </div>
            ),
        },
        {
            key: 'transfer_date',
            label: 'Transfer Date',
            sortable: true,
            render: (tr) => <DateCell value={tr.transfer_date} />,
        },
        {
            key: 'effective_date',
            label: 'Effective Date',
            sortable: true,
            render: (tr) => <DateCell value={tr.effective_date} />,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (tr) => <StatusBadge status={tr.status} />,
        },
        {
            key: 'document',
            label: 'Documents',
            render: (row) => (
                <DocumentLink
                    href={transferRoutes.document.url(row.id)}
                    fileName={row.file_name}
                />
            ),
        },
    ];

    const selectField = (
        name: 'to_branch_id' | 'to_department_id' | 'to_designation_id',
        label: string,
        options: Option[],
        reset: Partial<typeof blank>,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`transfer-${name}`}>
                {t(label)}
                <span className="text-destructive">*</span>
            </Label>
            <SelectField
                id={`transfer-${name}`}
                required
                value={form.data[name]}
                onChange={(e) =>
                    form.setData({
                        ...form.data,
                        ...reset,
                        [name]: e.target.value,
                    })
                }
            >
                <option value="">{t('Select')}</option>
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.name}
                    </option>
                ))}
            </SelectField>
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <>
            <Head title={t('Transfers')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employee Transfers"
                    description="Manage employee transfers between departments and branches."
                    action={
                        can('create-employee-transfers') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Transfer')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={transfers}
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
                    moreFilters={
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
                                name="branch_id"
                                label="All Branches"
                                options={branches}
                            />
                        </>
                    }
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="department_id"
                                label="All Departments"
                                options={departments}
                            />
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(transfer) => {
                        const pending = transfer.status === 'pending';

                        const decide = (action: Decision['action']) => {
                            decisionForm.reset();
                            decisionForm.clearErrors();
                            setDeciding({ transfer, action });
                        };

                        return (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(transfer)}
                                >
                                    <Eye />
                                </Button>
                                {pending &&
                                    can('approve-employee-transfers') && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Approve')}
                                            onClick={() => decide('approve')}
                                        >
                                            <CircleCheckBig />
                                        </Button>
                                    )}
                                {pending &&
                                    can('reject-employee-transfers') && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Reject')}
                                            onClick={() => decide('reject')}
                                        >
                                            <CircleX />
                                        </Button>
                                    )}
                                {pending && can('edit-employee-transfers') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(transfer)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {can('delete-employee-transfers') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(transfer)}
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
                title={editing ? 'Edit Transfer' : 'Add New Transfer'}
                description="The employee moves to the new placement when the transfer is approved."
                onSubmit={(e) => {
                    e.preventDefault();
                    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
                    form.post(
                        editing
                            ? transferRoutes.update.form(editing.id).action
                            : transferRoutes.store().url,
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
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="transfer-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="transfer-employee"
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
                    {selectField('to_branch_id', 'To Branch', branches, {
                        to_department_id: '',
                        to_designation_id: '',
                    })}
                    {selectField(
                        'to_department_id',
                        'To Department',
                        departments.filter(
                            (d) =>
                                String(d.branch_id) ===
                                String(form.data.to_branch_id),
                        ),
                        { to_designation_id: '' },
                    )}
                    {selectField(
                        'to_designation_id',
                        'To Designation',
                        designations.filter(
                            (d) =>
                                String(d.department_id) ===
                                String(form.data.to_department_id),
                        ),
                        {},
                    )}
                    <div />
                    <div className="grid gap-2">
                        <Label htmlFor="transfer-date">
                            {t('Transfer Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="transfer-date"
                            type="date"
                            required
                            value={form.data.transfer_date}
                            onChange={(e) =>
                                form.setData('transfer_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.transfer_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="transfer-effective">
                            {t('Effective Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="transfer-effective"
                            type="date"
                            required
                            min={form.data.transfer_date || undefined}
                            value={form.data.effective_date}
                            onChange={(e) =>
                                form.setData('effective_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.effective_date} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="transfer-reason">
                            {t('Reason')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="transfer-reason"
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
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="transfer-notes">{t('Notes')}</Label>
                        <textarea
                            id="transfer-notes"
                            rows={2}
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <DocumentInput
                        id="transfer-document"
                        currentName={editing?.file_name}
                        hasNewFile={form.data.document !== null}
                        error={form.errors.document}
                        onChange={(file) => form.setData('document', file)}
                    />
                </div>
            </FormDialog>

            <FormDialog
                open={deciding !== null}
                onOpenChange={(open) => !open && setDeciding(null)}
                title={
                    deciding?.action === 'approve'
                        ? 'Approve Transfer'
                        : 'Reject Transfer'
                }
                description={
                    deciding?.action === 'approve'
                        ? "The employee's branch, department and designation will be updated."
                        : undefined
                }
                processing={decisionForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (deciding) {
                        decisionForm.submit(
                            deciding.action === 'approve'
                                ? transferRoutes.approve(deciding.transfer.id)
                                : transferRoutes.reject(deciding.transfer.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setDeciding(null),
                            },
                        );
                    }
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="transfer-decision-notes">
                        {t(
                            deciding?.action === 'approve'
                                ? 'Notes'
                                : 'Rejection Reason',
                        )}
                        {deciding?.action === 'reject' && (
                            <span className="text-destructive">*</span>
                        )}
                    </Label>
                    <textarea
                        id="transfer-decision-notes"
                        rows={3}
                        required={deciding?.action === 'reject'}
                        className={textareaClass}
                        value={decisionForm.data.notes}
                        onChange={(e) =>
                            decisionForm.setData('notes', e.target.value)
                        }
                    />
                    <InputError message={decisionForm.errors.notes} />
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-3">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-950">
                                <ArrowLeftRight className="size-5" />
                            </span>
                            {t('Transfer Details')}
                        </DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <div className="grid gap-4 text-sm">
                            <PersonCell
                                name={viewing.employee.user.name}
                                detail={viewing.employee.user.email}
                                src={viewing.employee.user.avatar}
                                gender={viewing.employee.gender}
                            />
                            <Change
                                icon={GitBranch}
                                label="Branch Transfer"
                                from={viewing.from_branch}
                                to={viewing.to_branch}
                            />
                            <Change
                                icon={Building2}
                                label="Department Transfer"
                                from={viewing.from_department}
                                to={viewing.to_department}
                            />
                            <Change
                                icon={Briefcase}
                                label="Designation Transfer"
                                from={viewing.from_designation}
                                to={viewing.to_designation}
                            />
                            <div className="grid grid-cols-2 gap-4">
                                {(
                                    [
                                        [
                                            'Transfer Date',
                                            date(viewing.transfer_date),
                                        ],
                                        [
                                            'Effective Date',
                                            date(viewing.effective_date),
                                        ],
                                    ] as const
                                ).map(([label, value]) => (
                                    <div key={label}>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <CalendarDays className="size-4" />
                                            {t(label)}
                                        </div>
                                        <div className="mt-1 font-medium">
                                            {value}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div>
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <Lock className="size-4" /> {t('Status')}
                                </div>
                                <div className="mt-1">
                                    <StatusBadge status={viewing.status} />
                                </div>
                            </div>
                            {(
                                [
                                    ['Documents', null],
                                    ['Reason', viewing.reason],
                                    ['Notes', viewing.notes],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label}>
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <FileText className="size-4" />
                                        {t(label)}
                                    </div>
                                    <div className="mt-1 font-medium whitespace-pre-line">
                                        {label === 'Documents' ? (
                                            viewing.file_name ? (
                                                <a
                                                    href={transferRoutes.document.url(
                                                        viewing.id,
                                                    )}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {viewing.file_name}
                                                </a>
                                            ) : (
                                                '—'
                                            )
                                        ) : (
                                            value || '—'
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This transfer will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(transferRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Transfers.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Lifecycle', href: transferRoutes.index() },
        { title: 'Employee Transfers', href: transferRoutes.index() },
    ],
};
