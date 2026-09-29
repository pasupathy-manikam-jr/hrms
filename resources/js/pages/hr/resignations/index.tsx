import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Plus, RefreshCw, SquarePen, Trash2 } from 'lucide-react';
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
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import resignationRoutes from '@/routes/hr/resignations';
import type { Paginated, TableFilters } from '@/types';

type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = { id: number; name: string; employee_id: string };

type Resignation = {
    id: number;
    employee_id: number;
    resignation_date: string;
    last_working_day: string;
    notice_period: string | null;
    reason: string;
    description: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'completed';
    approved_at: string | null;
    file_name: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Person;
    };
    approver: { id: number; name: string } | null;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    employee_id: '' as number | string,
    resignation_date: '',
    last_working_day: '',
    notice_period: '',
    reason: '',
    description: '',
    document: null as File | null,
};

export default function Resignations({
    resignations,
    employees,
    statusCounts,
    filters,
}: {
    resignations: Paginated<Resignation>;
    employees: EmployeeOption[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = resignationRoutes.index();
    const isStaff = can('manage-any-resignations');
    const decisions = [
        ...(can('approve-resignations') ? ['approved', 'completed'] : []),
        ...(can('reject-resignations') ? ['rejected'] : []),
    ];
    const [editing, setEditing] = useState<Resignation | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Resignation | null>(null);
    const [viewing, setViewing] = useState<Resignation | null>(null);
    const { date } = useFormat();
    const [deciding, setDeciding] = useState<Resignation | null>(null);
    const form = useForm(blank);
    const statusForm = useForm({ status: '' });

    const openForm = (resignation: Resignation | null) => {
        setEditing(resignation);
        form.clearErrors();
        form.setData(
            resignation
                ? {
                      employee_id: resignation.employee_id,
                      resignation_date: resignation.resignation_date,
                      last_working_day: resignation.last_working_day,
                      notice_period: resignation.notice_period ?? '',
                      reason: resignation.reason,
                      description: resignation.description ?? '',
                      document: null,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const openStatus = (resignation: Resignation) => {
        statusForm.setData('status', decisions[0] ?? '');
        statusForm.clearErrors();
        setDeciding(resignation);
    };

    const columns: Column<Resignation>[] = [
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
            key: 'resignation_date',
            label: 'Resignation Date',
            sortable: true,
            render: (r) => <DateCell value={r.resignation_date} />,
        },
        {
            key: 'last_working_day',
            label: 'Last Working Day',
            sortable: true,
            render: (r) => <DateCell value={r.last_working_day} />,
        },
        {
            key: 'notice_period',
            label: 'Notice Period',
            render: (r) => r.notice_period ?? '—',
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
                    href={resignationRoutes.document.url(r.id)}
                    fileName={r.file_name}
                />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Resignations')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Resignations"
                    description="View and manage employee resignation requests."
                    action={
                        can('create-resignations') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Resignation')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={resignations}
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
                    actions={(resignation) => {
                        const changeable =
                            isStaff || resignation.status === 'pending';

                        return (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(resignation)}
                                >
                                    <Eye />
                                </Button>
                                {decisions.length > 0 && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Change Status')}
                                        onClick={() => openStatus(resignation)}
                                    >
                                        <RefreshCw className="text-emerald-600" />
                                    </Button>
                                )}
                                {changeable && can('edit-resignations') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(resignation)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {changeable && can('delete-resignations') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(resignation)}
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
                title={editing ? 'Edit Resignation' : 'Add Resignation'}
                onSubmit={(e) => {
                    e.preventDefault();
                    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
                    form.post(
                        editing
                            ? resignationRoutes.update.form(editing.id).action
                            : resignationRoutes.store().url,
                        {
                            forceFormData: true,
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
                            <Label htmlFor="resignation-employee">
                                {t('Employee')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="resignation-employee"
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
                    <div className="grid gap-2">
                        <Label htmlFor="resignation-date">
                            {t('Notice Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="resignation-date"
                            type="date"
                            required
                            value={form.data.resignation_date}
                            onChange={(e) =>
                                form.setData('resignation_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.resignation_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="resignation-last-day">
                            {t('Last Working Day')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="resignation-last-day"
                            type="date"
                            required
                            min={form.data.resignation_date || undefined}
                            value={form.data.last_working_day}
                            onChange={(e) =>
                                form.setData('last_working_day', e.target.value)
                            }
                        />
                        <InputError message={form.errors.last_working_day} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="resignation-notice">
                            {t('Notice Period')}
                        </Label>
                        <Input
                            id="resignation-notice"
                            placeholder={t('e.g. 1 month, 2 weeks')}
                            value={form.data.notice_period}
                            onChange={(e) =>
                                form.setData('notice_period', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notice_period} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="resignation-reason">
                            {t('Reason')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="resignation-reason"
                            required
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="resignation-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="resignation-description"
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
                        id="resignation-document"
                        currentName={editing?.file_name}
                        hasNewFile={form.data.document !== null}
                        error={form.errors.document}
                        onChange={(file) => form.setData('document', file)}
                    />
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Resignation')}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            {(
                                [
                                    ['Employee', viewing.employee.user.name],
                                    ['Reason', viewing.reason],
                                    [
                                        'Resignation Date',
                                        date(viewing.resignation_date),
                                    ],
                                    [
                                        'Last Working Day',
                                        date(viewing.last_working_day),
                                    ],
                                    ['Notice Period', viewing.notice_period],
                                    ['Decided By', viewing.approver?.name],
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
                                        href={resignationRoutes.document.url(
                                            viewing.id,
                                        )}
                                        fileName={viewing.file_name}
                                    />
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

            <FormDialog
                open={deciding !== null}
                onOpenChange={(open) => !open && setDeciding(null)}
                title="Change Status"
                description={deciding?.employee.user.name}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (deciding) {
                        statusForm.submit(
                            resignationRoutes.changeStatus(deciding.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setDeciding(null),
                            },
                        );
                    }
                }}
                processing={statusForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="resignation-status">{t('Status')}</Label>
                    <SelectField
                        id="resignation-status"
                        required
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData('status', e.target.value)
                        }
                    >
                        {decisions.map((status) => (
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

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This resignation will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(resignationRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Resignations.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Lifecycle', href: resignationRoutes.index() },
        { title: 'Resignations', href: resignationRoutes.index() },
    ],
};
