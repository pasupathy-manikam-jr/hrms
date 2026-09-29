import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ChartLine,
    Eye,
    Plus,
    RefreshCw,
    SquarePen,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
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
import warningRoutes from '@/routes/hr/warnings';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = Option & { employee_id: string };

type Warning = {
    id: number;
    employee_id: number;
    warning_by: number | null;
    warning_type: string;
    subject: string;
    severity: string;
    warning_date: string;
    expiry_date: string | null;
    description: string | null;
    status: string;
    acknowledgment_date: string | null;
    employee_response: string | null;
    has_improvement_plan: boolean;
    improvement_plan_goals: string | null;
    improvement_plan_start_date: string | null;
    improvement_plan_end_date: string | null;
    improvement_plan_progress: string | null;
    file_name: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Person;
    };
    issuer: Option | null;
    approver: Option | null;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

// "policy_violation" -> "Policy Violation"
const pretty = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const blank = {
    employee_id: '' as number | string,
    warning_by: '' as number | string,
    warning_type: '',
    subject: '',
    severity: '',
    warning_date: '',
    expiry_date: '',
    description: '',
    has_improvement_plan: false,
    improvement_plan_goals: '',
    improvement_plan_start_date: '',
    improvement_plan_end_date: '',
    document: null as File | null,
};

/** The demo's green "Yes" / grey "No". */
function YesNo({ value }: { value: boolean }) {
    return value ? (
        <StatusBadge status="yes" />
    ) : (
        <StatusBadge status="optional" label="No" />
    );
}

export default function Warnings({
    warnings,
    employees,
    managers,
    warningTypes,
    severities,
    statusCounts,
    filters,
}: {
    warnings: Paginated<Warning>;
    employees: EmployeeOption[];
    managers: Option[];
    warningTypes: string[];
    severities: string[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const { auth } = usePage().props;
    const url = warningRoutes.index();
    const [editing, setEditing] = useState<Warning | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [viewing, setViewing] = useState<Warning | null>(null);
    const [statusFor, setStatusFor] = useState<Warning | null>(null);
    const statusForm = useForm({
        status: '',
        acknowledgment_date: '',
        employee_response: '',
    });
    const statusChoices = [
        ...(can('approve-warnings') ? ['draft', 'issued'] : []),
        ...(can('acknowledge-warnings') ? ['acknowledged'] : []),
        ...(can('approve-warnings') ? ['expired'] : []),
    ];
    const [deleting, setDeleting] = useState<Warning | null>(null);
    const [planFor, setPlanFor] = useState<Warning | null>(null);
    const planForm = useForm({
        has_improvement_plan: false,
        improvement_plan_goals: '',
        improvement_plan_start_date: '',
        improvement_plan_end_date: '',
        improvement_plan_progress: '',
    });
    const form = useForm(blank);

    const typeOptions = warningTypes.map((type) => ({
        id: type,
        name: t(pretty(type)),
    }));
    const severityOptions = severities.map((severity) => ({
        id: severity,
        name: t(pretty(severity)),
    }));

    const openForm = (warning: Warning | null) => {
        setEditing(warning);
        form.clearErrors();
        form.setData(
            warning
                ? {
                      employee_id: warning.employee_id,
                      warning_by: warning.warning_by ?? '',
                      warning_type: warning.warning_type,
                      subject: warning.subject,
                      severity: warning.severity,
                      warning_date: warning.warning_date,
                      expiry_date: warning.expiry_date ?? '',
                      description: warning.description ?? '',
                      has_improvement_plan: warning.has_improvement_plan,
                      improvement_plan_goals:
                          warning.improvement_plan_goals ?? '',
                      improvement_plan_start_date:
                          warning.improvement_plan_start_date ?? '',
                      improvement_plan_end_date:
                          warning.improvement_plan_end_date ?? '',
                      document: null,
                  }
                : { ...blank, warning_by: auth.user.id },
        );
        setFormOpen(true);
    };

    const columns: Column<Warning>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (w) => (
                <PersonCell
                    name={w.employee.user.name}
                    detail={w.employee.user.email}
                    src={w.employee.user.avatar}
                    gender={w.employee.gender}
                />
            ),
        },
        {
            key: 'subject',
            label: 'Subject',
            render: (w) => <span className="font-medium">{w.subject}</span>,
        },
        {
            key: 'warning_type',
            label: 'Type',
            render: (w) => t(pretty(w.warning_type)),
        },
        {
            key: 'severity',
            label: 'Severity',
            sortable: true,
            render: (w) => <StatusBadge status={w.severity} />,
        },
        {
            key: 'warning_date',
            label: 'Date',
            sortable: true,
            render: (w) => <DateCell value={w.warning_date} />,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (w) => <StatusBadge status={w.status} />,
        },
        {
            key: 'has_improvement_plan',
            label: 'Improvement Plan',
            render: (w) => <YesNo value={w.has_improvement_plan} />,
        },
        {
            key: 'document',
            label: 'Documents',
            render: (w) => (
                <DocumentLink
                    href={warningRoutes.document.url(w.id)}
                    fileName={w.file_name}
                />
            ),
        },
    ];

    const field = (
        name: Exclude<keyof typeof blank, 'has_improvement_plan' | 'document'>,
        label: string,
        input: ReactNode,
        wide = false,
    ) => (
        <div className={wide ? 'grid gap-2 sm:col-span-2' : 'grid gap-2'}>
            <Label htmlFor={`warning-${name}`}>{t(label)}</Label>
            {input}
            <InputError message={form.errors[name]} />
        </div>
    );

    const select = (
        name: 'employee_id' | 'warning_by' | 'warning_type' | 'severity',
        placeholder: string,
        options: { id: number | string; name: string }[],
    ) => (
        <SelectField
            id={`warning-${name}`}
            required
            value={form.data[name]}
            onChange={(e) => form.setData(name, e.target.value)}
        >
            <option value="">{t(placeholder)}</option>
            {options.map((option) => (
                <option key={option.id} value={option.id}>
                    {option.name}
                </option>
            ))}
        </SelectField>
    );

    return (
        <>
            <Head title={t('Warnings')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Warnings"
                    description="Manage formal warnings issued to employees."
                    action={
                        can('create-warnings') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Warning')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={warnings}
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
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="warning_type"
                                label="All Types"
                                options={typeOptions}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="severity"
                                label="All Severities"
                                options={severityOptions}
                            />
                        </>
                    }
                    actions={(warning) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(warning)}
                            >
                                <Eye />
                            </Button>
                            {warning.status === 'draft' &&
                                can('edit-warnings') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(warning)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                            {statusChoices.length > 0 && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Change Status')}
                                    title={t('Change Status')}
                                    onClick={() => {
                                        statusForm.setData({
                                            status: warning.status,
                                            acknowledgment_date:
                                                warning.acknowledgment_date ??
                                                '',
                                            employee_response:
                                                warning.employee_response ?? '',
                                        });
                                        statusForm.clearErrors();
                                        setStatusFor(warning);
                                    }}
                                >
                                    <RefreshCw />
                                </Button>
                            )}
                            {can('edit-warnings') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Improvement Plan')}
                                    title={t('Improvement Plan')}
                                    onClick={() => {
                                        planForm.setData({
                                            has_improvement_plan:
                                                warning.has_improvement_plan,
                                            improvement_plan_goals:
                                                warning.improvement_plan_goals ??
                                                '',
                                            improvement_plan_start_date:
                                                warning.improvement_plan_start_date ??
                                                '',
                                            improvement_plan_end_date:
                                                warning.improvement_plan_end_date ??
                                                '',
                                            improvement_plan_progress:
                                                warning.improvement_plan_progress ??
                                                '',
                                        });
                                        planForm.clearErrors();
                                        setPlanFor(warning);
                                    }}
                                >
                                    <ChartLine />
                                </Button>
                            )}
                            {can('delete-warnings') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(warning)}
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
                title={editing ? 'Edit Warning' : 'Add Warning'}
                description="New warnings start as drafts until they are issued."
                onSubmit={(e) => {
                    e.preventDefault();
                    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
                    form.post(
                        editing
                            ? warningRoutes.update.form(editing.id).action
                            : warningRoutes.store().url,
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
                    {field(
                        'employee_id',
                        'Employee',
                        select(
                            'employee_id',
                            'Select Employee',
                            employees.map((e) => ({
                                id: e.id,
                                name: `${e.name} (${e.employee_id})`,
                            })),
                        ),
                    )}
                    {field(
                        'warning_by',
                        'Issued By',
                        select('warning_by', 'Select', managers),
                    )}
                    {field(
                        'warning_type',
                        'Warning Type',
                        select('warning_type', 'Select Type', typeOptions),
                    )}
                    {field(
                        'severity',
                        'Severity',
                        select('severity', 'Select Severity', severityOptions),
                    )}
                    {field(
                        'subject',
                        'Subject',
                        <Input
                            id="warning-subject"
                            required
                            value={form.data.subject}
                            onChange={(e) =>
                                form.setData('subject', e.target.value)
                            }
                        />,
                        true,
                    )}
                    {field(
                        'warning_date',
                        'Warning Date',
                        <Input
                            id="warning-warning_date"
                            type="date"
                            required
                            value={form.data.warning_date}
                            onChange={(e) =>
                                form.setData('warning_date', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'expiry_date',
                        'Expiry Date',
                        <Input
                            id="warning-expiry_date"
                            type="date"
                            min={form.data.warning_date || undefined}
                            value={form.data.expiry_date}
                            onChange={(e) =>
                                form.setData('expiry_date', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'description',
                        'Description',
                        <textarea
                            id="warning-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />,
                        true,
                    )}
                    <DocumentInput
                        id="warning-document"
                        currentName={editing?.file_name}
                        hasNewFile={form.data.document !== null}
                        error={form.errors.document}
                        onChange={(file) => form.setData('document', file)}
                    />
                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Switch
                            id="warning-plan"
                            checked={form.data.has_improvement_plan}
                            onCheckedChange={(checked) =>
                                form.setData('has_improvement_plan', checked)
                            }
                        />
                        <Label htmlFor="warning-plan">
                            {t('Improvement Plan')}
                        </Label>
                    </div>
                    {form.data.has_improvement_plan && (
                        <>
                            {field(
                                'improvement_plan_goals',
                                'Improvement Plan Goals',
                                <textarea
                                    id="warning-improvement_plan_goals"
                                    rows={3}
                                    required
                                    className={textareaClass}
                                    value={form.data.improvement_plan_goals}
                                    onChange={(e) =>
                                        form.setData(
                                            'improvement_plan_goals',
                                            e.target.value,
                                        )
                                    }
                                />,
                                true,
                            )}
                            {field(
                                'improvement_plan_start_date',
                                'Plan Start Date',
                                <Input
                                    id="warning-improvement_plan_start_date"
                                    type="date"
                                    required
                                    value={
                                        form.data.improvement_plan_start_date
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'improvement_plan_start_date',
                                            e.target.value,
                                        )
                                    }
                                />,
                            )}
                            {field(
                                'improvement_plan_end_date',
                                'Plan End Date',
                                <Input
                                    id="warning-improvement_plan_end_date"
                                    type="date"
                                    required
                                    min={
                                        form.data.improvement_plan_start_date ||
                                        undefined
                                    }
                                    value={form.data.improvement_plan_end_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'improvement_plan_end_date',
                                            e.target.value,
                                        )
                                    }
                                />,
                            )}
                        </>
                    )}
                </div>
            </FormDialog>

            <FormDialog
                open={planFor !== null}
                onOpenChange={(open) => !open && setPlanFor(null)}
                title="Update Improvement Plan"
                description={planFor?.subject}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (planFor) {
                        planForm.submit(
                            warningRoutes.improvementPlan(planFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setPlanFor(null),
                            },
                        );
                    }
                }}
                processing={planForm.processing}
            >
                <div className="grid gap-4">
                    <div className="flex items-center gap-3">
                        <Switch
                            id="plan-enabled"
                            checked={planForm.data.has_improvement_plan}
                            onCheckedChange={(checked) =>
                                planForm.setData(
                                    'has_improvement_plan',
                                    checked,
                                )
                            }
                        />
                        <Label htmlFor="plan-enabled">
                            {t('Has Improvement Plan')}
                        </Label>
                    </div>
                    {planForm.data.has_improvement_plan && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-goals">
                                    {t('Improvement Plan Goals')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <textarea
                                    id="plan-goals"
                                    rows={3}
                                    required
                                    className={textareaClass}
                                    value={planForm.data.improvement_plan_goals}
                                    onChange={(e) =>
                                        planForm.setData(
                                            'improvement_plan_goals',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        planForm.errors.improvement_plan_goals
                                    }
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-improvement_plan_start_date">
                                        {t('Improvement Plan Start Date')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="plan-improvement_plan_start_date"
                                        type="date"
                                        required
                                        value={
                                            planForm.data
                                                .improvement_plan_start_date
                                        }
                                        onChange={(e) =>
                                            planForm.setData(
                                                'improvement_plan_start_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            planForm.errors
                                                .improvement_plan_start_date
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-improvement_plan_end_date">
                                        {t('Improvement Plan End Date')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="plan-improvement_plan_end_date"
                                        type="date"
                                        required
                                        value={
                                            planForm.data
                                                .improvement_plan_end_date
                                        }
                                        onChange={(e) =>
                                            planForm.setData(
                                                'improvement_plan_end_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            planForm.errors
                                                .improvement_plan_end_date
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-progress">
                                    {t('Improvement Plan Progress')}
                                </Label>
                                <textarea
                                    id="plan-progress"
                                    rows={3}
                                    className={textareaClass}
                                    value={
                                        planForm.data.improvement_plan_progress
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'improvement_plan_progress',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        planForm.errors
                                            .improvement_plan_progress
                                    }
                                />
                            </div>
                        </>
                    )}
                </div>
            </FormDialog>

            <FormDialog
                open={statusFor !== null}
                onOpenChange={(open) => !open && setStatusFor(null)}
                title="Change Warning Status"
                description={statusFor?.subject}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (statusFor) {
                        statusForm.submit(
                            warningRoutes.changeStatus(statusFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setStatusFor(null),
                            },
                        );
                    }
                }}
                processing={statusForm.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="warning-new-status">
                            {t('Status')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="warning-new-status"
                            value={statusForm.data.status}
                            onChange={(e) =>
                                statusForm.setData('status', e.target.value)
                            }
                        >
                            {statusChoices.map((status) => (
                                <option key={status} value={status}>
                                    {t(pretty(status))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={statusForm.errors.status} />
                    </div>
                    {statusForm.data.status === 'acknowledged' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="warning-ack-date">
                                    {t('Acknowledgment Date')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="warning-ack-date"
                                    type="date"
                                    required
                                    value={statusForm.data.acknowledgment_date}
                                    onChange={(e) =>
                                        statusForm.setData(
                                            'acknowledgment_date',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        statusForm.errors.acknowledgment_date
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="warning-response">
                                    {t('Employee Response')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <textarea
                                    id="warning-response"
                                    rows={3}
                                    required
                                    className={textareaClass}
                                    value={statusForm.data.employee_response}
                                    onChange={(e) =>
                                        statusForm.setData(
                                            'employee_response',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        statusForm.errors.employee_response
                                    }
                                />
                            </div>
                        </>
                    )}
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Warning')}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            {(
                                [
                                    ['Employee', viewing.employee.user.name],
                                    ['Issued By', viewing.issuer?.name],
                                    [
                                        'Warning Type',
                                        t(pretty(viewing.warning_type)),
                                    ],
                                    [
                                        'Severity',
                                        <StatusBadge
                                            key="severity"
                                            status={viewing.severity}
                                        />,
                                    ],
                                    [
                                        'Warning Date',
                                        date(viewing.warning_date),
                                    ],
                                    [
                                        'Expiry Date',
                                        viewing.expiry_date &&
                                            date(viewing.expiry_date),
                                    ],
                                    [
                                        'Status',
                                        <StatusBadge
                                            key="status"
                                            status={viewing.status}
                                        />,
                                    ],
                                    [
                                        'Acknowledged On',
                                        viewing.acknowledgment_date &&
                                            date(viewing.acknowledgment_date),
                                    ],
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
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Subject')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.subject}
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
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Improvement Plan')}
                                </dt>
                                <dd>
                                    <YesNo
                                        value={viewing.has_improvement_plan}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Documents')}
                                </dt>
                                <dd>
                                    <DocumentLink
                                        href={warningRoutes.document.url(
                                            viewing.id,
                                        )}
                                        fileName={viewing.file_name}
                                    />
                                </dd>
                            </div>
                            {viewing.has_improvement_plan && (
                                <>
                                    <div className="col-span-2">
                                        <dt className="text-muted-foreground">
                                            {t('Improvement Plan Goals')}
                                        </dt>
                                        <dd className="font-medium whitespace-pre-line">
                                            {viewing.improvement_plan_goals}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            {t('Plan Period')}
                                        </dt>
                                        <dd className="font-medium">
                                            {viewing.improvement_plan_start_date &&
                                                date(
                                                    viewing.improvement_plan_start_date,
                                                )}{' '}
                                            –{' '}
                                            {viewing.improvement_plan_end_date &&
                                                date(
                                                    viewing.improvement_plan_end_date,
                                                )}
                                        </dd>
                                    </div>
                                    <div className="col-span-2">
                                        <dt className="text-muted-foreground">
                                            {t('Progress Notes')}
                                        </dt>
                                        <dd className="font-medium whitespace-pre-line">
                                            {viewing.improvement_plan_progress ||
                                                '—'}
                                        </dd>
                                    </div>
                                </>
                            )}
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Employee Response')}
                                </dt>
                                <dd className="font-medium whitespace-pre-line">
                                    {viewing.employee_response || '—'}
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This warning will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(warningRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Warnings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Lifecycle', href: warningRoutes.index() },
        { title: 'Warnings', href: warningRoutes.index() },
    ],
};
