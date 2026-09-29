import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Clock,
    Eye,
    FileText,
    Plus,
    RefreshCw,
    SquarePen,
    Tag,
    Trash2,
    UserX,
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
import { DateCell, DocumentLink } from '@/components/table-cells';
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
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import terminationRoutes from '@/routes/hr/terminations';
import type { Paginated, TableFilters } from '@/types';

type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = { id: number; name: string; employee_id: string };

type Termination = {
    id: number;
    employee_id: number;
    termination_type: string;
    notice_date: string;
    termination_date: string;
    notice_period: string | null;
    reason: string;
    description: string | null;
    status: 'planned' | 'in progress' | 'completed';
    exit_interview_conducted: boolean;
    exit_interview_date: string | null;
    exit_feedback: string | null;
    file_name: string | null;
    employee: {
        id: number;
        employee_id: string;
        employee_status: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Person;
    };
    approver: { id: number; name: string } | null;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const titleCase = (value: string) =>
    value.replace(/\b\w/g, (c) => c.toUpperCase());

const blank = {
    employee_id: '' as number | string,
    termination_type: '',
    notice_date: '',
    termination_date: '',
    notice_period: '',
    reason: '',
    description: '',
    document: null as File | null,
};

export default function Terminations({
    terminations,
    employees,
    terminationTypes,
    statusCounts,
    filters,
}: {
    terminations: Paginated<Termination>;
    employees: EmployeeOption[];
    terminationTypes: string[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = terminationRoutes.index();
    const statuses = [
        ...(can('reject-terminations') ? ['planned'] : []),
        ...(can('approve-terminations') ? ['in progress', 'completed'] : []),
    ];
    const [editing, setEditing] = useState<Termination | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Termination | null>(null);
    const [deciding, setDeciding] = useState<Termination | null>(null);
    const form = useForm(blank);
    const statusForm = useForm({
        status: '',
        exit_interview_conducted: false,
        exit_interview_date: '',
        exit_feedback: '',
    });
    const [viewing, setViewing] = useState<Termination | null>(null);
    const { date } = useFormat();

    const openForm = (termination: Termination | null) => {
        setEditing(termination);
        form.clearErrors();
        form.setData(
            termination
                ? {
                      employee_id: termination.employee_id,
                      termination_type: termination.termination_type,
                      notice_date: termination.notice_date,
                      termination_date: termination.termination_date,
                      notice_period: termination.notice_period ?? '',
                      reason: termination.reason,
                      description: termination.description ?? '',
                      document: null,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const openStatus = (termination: Termination) => {
        statusForm.setData({
            status: termination.status,
            exit_interview_conducted: termination.exit_interview_conducted,
            exit_interview_date: termination.exit_interview_date ?? '',
            exit_feedback: termination.exit_feedback ?? '',
        });
        statusForm.clearErrors();
        setDeciding(termination);
    };

    const columns: Column<Termination>[] = [
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
            key: 'termination_type',
            label: 'Type',
            render: (r) => t(titleCase(r.termination_type)),
        },
        {
            key: 'termination_date',
            label: 'Termination Date',
            sortable: true,
            render: (r) => <DateCell value={r.termination_date} />,
        },
        {
            key: 'notice_date',
            label: 'Notice Date',
            sortable: true,
            render: (r) => <DateCell value={r.notice_date} />,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (r) => <StatusBadge status={r.status} />,
        },
        {
            key: 'document',
            label: 'Documents',
            render: (r) => (
                <DocumentLink
                    href={terminationRoutes.document.url(r.id)}
                    fileName={r.file_name}
                />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Terminations')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Terminations"
                    description="Manage employee termination records and details."
                    action={
                        can('create-terminations') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Termination')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={terminations}
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
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="termination_type"
                                label="All Types"
                                options={terminationTypes.map((type) => ({
                                    id: type,
                                    name: t(titleCase(type)),
                                }))}
                            />
                        </>
                    }
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
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(termination) => {
                        const open = termination.status !== 'completed';

                        return (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(termination)}
                                >
                                    <Eye />
                                </Button>
                                {open && can('edit-terminations') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(termination)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {statuses.length > 0 && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Change Status')}
                                        title={t('Change Status')}
                                        onClick={() => openStatus(termination)}
                                    >
                                        <RefreshCw />
                                    </Button>
                                )}
                                {can('delete-terminations') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(termination)}
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
                title={editing ? 'Edit Termination' : 'Add New Termination'}
                onSubmit={(e) => {
                    e.preventDefault();
                    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
                    form.post(
                        editing
                            ? terminationRoutes.update.form(editing.id).action
                            : terminationRoutes.store().url,
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
                        <Label htmlFor="termination-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="termination-employee"
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
                        <Label htmlFor="termination-type">
                            {t('Termination Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="termination-type"
                            required
                            value={form.data.termination_type}
                            onChange={(e) =>
                                form.setData('termination_type', e.target.value)
                            }
                        >
                            <option value="">
                                {t('Select Termination Type')}
                            </option>
                            {terminationTypes.map((type) => (
                                <option key={type} value={type}>
                                    {t(titleCase(type))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.termination_type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="termination-notice-date">
                            {t('Notice Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="termination-notice-date"
                            type="date"
                            required
                            value={form.data.notice_date}
                            onChange={(e) =>
                                form.setData('notice_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notice_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="termination-date">
                            {t('Termination Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="termination-date"
                            type="date"
                            required
                            min={form.data.notice_date || undefined}
                            value={form.data.termination_date}
                            onChange={(e) =>
                                form.setData('termination_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.termination_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="termination-notice">
                            {t('Notice Period')}
                        </Label>
                        <Input
                            id="termination-notice"
                            placeholder={t('e.g. 1 month, 2 weeks')}
                            value={form.data.notice_period}
                            onChange={(e) =>
                                form.setData('notice_period', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notice_period} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="termination-reason">
                            {t('Reason')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="termination-reason"
                            required
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="termination-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="termination-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <DocumentInput
                        id="termination-document"
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
                title="Change Termination Status"
                description={
                    deciding?.status === 'completed'
                        ? 'This termination is complete; you can still record the exit interview.'
                        : 'Approving a termination whose date has arrived marks the employee as terminated.'
                }
                onSubmit={(e) => {
                    e.preventDefault();

                    if (deciding) {
                        statusForm.submit(
                            terminationRoutes.changeStatus(deciding.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setDeciding(null),
                            },
                        );
                    }
                }}
                processing={statusForm.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="termination-status">
                            {t('Status')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="termination-status"
                            required
                            disabled={deciding?.status === 'completed'}
                            value={statusForm.data.status}
                            onChange={(e) =>
                                statusForm.setData('status', e.target.value)
                            }
                        >
                            {(deciding?.status === 'completed'
                                ? ['completed']
                                : statuses
                            ).map((status) => (
                                <option key={status} value={status}>
                                    {t(titleCase(status))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={statusForm.errors.status} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="termination-exit-conducted"
                            checked={statusForm.data.exit_interview_conducted}
                            onCheckedChange={(checked) =>
                                statusForm.setData(
                                    'exit_interview_conducted',
                                    checked,
                                )
                            }
                        />
                        <Label htmlFor="termination-exit-conducted">
                            {t('Exit Interview Conducted')}
                        </Label>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="termination-exit-date">
                            {t('Exit Interview Date')}
                        </Label>
                        <Input
                            id="termination-exit-date"
                            type="date"
                            value={statusForm.data.exit_interview_date}
                            onChange={(e) =>
                                statusForm.setData(
                                    'exit_interview_date',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={statusForm.errors.exit_interview_date}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="termination-exit-feedback">
                            {t('Exit Feedback')}
                        </Label>
                        <textarea
                            id="termination-exit-feedback"
                            rows={3}
                            className={textareaClass}
                            value={statusForm.data.exit_feedback}
                            onChange={(e) =>
                                statusForm.setData(
                                    'exit_feedback',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={statusForm.errors.exit_feedback} />
                    </div>
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
                                <UserX className="size-5" />
                            </span>
                            {t('Termination Details')}
                        </DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <div className="grid gap-5 text-sm">
                            <div className="flex items-center justify-between gap-3 rounded-lg bg-muted/40 p-3">
                                <PersonCell
                                    name={viewing.employee.user.name}
                                    detail={t('Employee')}
                                    src={viewing.employee.user.avatar}
                                    gender={viewing.employee.gender}
                                />
                                <StatusBadge status={viewing.status} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                {(
                                    [
                                        [
                                            Tag,
                                            'Termination Type',
                                            t(
                                                titleCase(
                                                    viewing.termination_type,
                                                ),
                                            ),
                                        ],
                                        [
                                            Clock,
                                            'Notice Period',
                                            viewing.notice_period,
                                        ],
                                        [
                                            CalendarDays,
                                            'Notice Date',
                                            date(viewing.notice_date),
                                        ],
                                        [
                                            CalendarDays,
                                            'Termination Date',
                                            date(viewing.termination_date),
                                        ],
                                    ] as const
                                ).map(([Icon, label, value]) => (
                                    <div key={label}>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Icon className="size-4" />
                                            {t(label)}
                                        </div>
                                        <div className="mt-1 font-medium">
                                            {value || '—'}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div>
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <FileText className="size-4" />
                                    {t('Documents')}
                                </div>
                                <div className="mt-1 font-medium">
                                    {viewing.file_name ? (
                                        <a
                                            href={terminationRoutes.document.url(
                                                viewing.id,
                                            )}
                                            className="text-blue-600 hover:underline"
                                        >
                                            {viewing.file_name}
                                        </a>
                                    ) : (
                                        '—'
                                    )}
                                </div>
                            </div>
                            {(
                                [
                                    ['Reason', viewing.reason],
                                    ['Description', viewing.description],
                                    [
                                        'Exit Interview',
                                        viewing.exit_interview_conducted
                                            ? viewing.exit_interview_date
                                                ? date(
                                                      viewing.exit_interview_date,
                                                  )
                                                : t('Conducted')
                                            : t('Not conducted'),
                                    ],
                                    ['Exit Feedback', viewing.exit_feedback],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label}>
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <FileText className="size-4" />
                                        {t(label)}
                                    </div>
                                    <p className="mt-1 font-medium whitespace-pre-line">
                                        {value || '—'}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This termination will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(terminationRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Terminations.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Lifecycle', href: terminationRoutes.index() },
        { title: 'Terminations', href: terminationRoutes.index() },
    ],
};
