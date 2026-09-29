import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Briefcase,
    Eye,
    FileText,
    Plus,
    RefreshCw,
    SquarePen,
    Trash2,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DocumentInput } from '@/components/document-input';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell, DocumentLink } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import {
    DateRangeFilter,
    FilterSelect,
    StatusTabs,
} from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import promotionRoutes from '@/routes/hr/promotions';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = Option & { employee_id: string };

type Promotion = {
    id: number;
    employee_id: number;
    designation_id: number;
    promotion_date: string;
    effective_date: string;
    salary_adjustment: string | null;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected';
    file_name: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Person;
    };
    designation: Option;
    previous_designation: Option | null;
};

const STATUSES = ['pending', 'approved', 'rejected'] as const;

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    employee_id: '' as number | string,
    designation_id: '' as number | string,
    promotion_date: '',
    effective_date: '',
    salary_adjustment: '',
    reason: '',
    document: null as File | null,
};

export default function Promotions({
    promotions,
    employees,
    designations,
    statusCounts,
    filters,
}: {
    promotions: Paginated<Promotion>;
    employees: EmployeeOption[];
    designations: Option[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = promotionRoutes.index();
    const [editing, setEditing] = useState<Promotion | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Promotion | null>(null);
    const [viewing, setViewing] = useState<Promotion | null>(null);
    const [statusFor, setStatusFor] = useState<Promotion | null>(null);
    const statusForm = useForm({ status: '' });
    const form = useForm(blank);
    const { date } = useFormat();
    const canDecide = can('approve-promotions') || can('reject-promotions');

    const openForm = (promotion: Promotion | null) => {
        setEditing(promotion);
        form.clearErrors();
        form.setData(
            promotion
                ? {
                      employee_id: promotion.employee_id,
                      designation_id: promotion.designation_id,
                      promotion_date: promotion.promotion_date,
                      effective_date: promotion.effective_date,
                      salary_adjustment: promotion.salary_adjustment ?? '',
                      reason: promotion.reason ?? '',
                      document: null,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Promotion>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (p) => (
                <PersonCell
                    name={p.employee.user.name}
                    detail={p.employee.user.email}
                    src={p.employee.user.avatar}
                    gender={p.employee.gender}
                />
            ),
        },
        {
            key: 'previous_designation',
            label: 'Previous Designation',
            render: (p) => p.previous_designation?.name ?? '—',
        },
        {
            key: 'designation',
            label: 'New Designation',
            render: (p) => p.designation.name,
        },
        {
            key: 'promotion_date',
            label: 'Promotion Date',
            sortable: true,
            render: (p) => <DateCell value={p.promotion_date} />,
        },
        {
            key: 'effective_date',
            label: 'Effective Date',
            sortable: true,
            render: (p) => <DateCell value={p.effective_date} />,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (p) => <StatusBadge status={p.status} />,
        },
        {
            key: 'document',
            label: 'Document',
            render: (p) => (
                <DocumentLink
                    href={promotionRoutes.document.url(p.id)}
                    fileName={p.file_name}
                />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Promotions')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Promotions"
                    description="Manage employee promotions and career advancements."
                    action={
                        can('create-promotions') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Promotion')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={promotions}
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
                    moreFilters={
                        employees.length > 0 && (
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="employee_id"
                                label="All Employees"
                                options={employees}
                            />
                        )
                    }
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="designation_id"
                                label="All Designations"
                                options={designations}
                            />
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(promotion) => {
                        const pending = promotion.status === 'pending';

                        return (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(promotion)}
                                >
                                    <Eye />
                                </Button>
                                {pending && can('edit-promotions') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(promotion)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {pending && canDecide && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Update Status')}
                                        title={t('Update Status')}
                                        onClick={() => {
                                            statusForm.setData(
                                                'status',
                                                can('approve-promotions')
                                                    ? 'approved'
                                                    : 'rejected',
                                            );
                                            statusForm.clearErrors();
                                            setStatusFor(promotion);
                                        }}
                                    >
                                        <RefreshCw />
                                    </Button>
                                )}
                                {can('delete-promotions') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(promotion)}
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
                title={editing ? 'Edit Promotion' : 'Add New Promotion'}
                description="The employee's designation changes when the promotion is approved."
                onSubmit={(e) => {
                    e.preventDefault();
                    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
                    form.post(
                        editing
                            ? promotionRoutes.update.form(editing.id).action
                            : promotionRoutes.store().url,
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
                        <Label htmlFor="promotion-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="promotion-employee"
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
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="promotion-designation">
                            {t('New Designation')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="promotion-designation"
                            required
                            value={form.data.designation_id}
                            onChange={(e) =>
                                form.setData('designation_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Designation')}</option>
                            {designations.map((designation) => (
                                <option
                                    key={designation.id}
                                    value={designation.id}
                                >
                                    {designation.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.designation_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="promotion-date">
                            {t('Promotion Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="promotion-date"
                            type="date"
                            required
                            value={form.data.promotion_date}
                            onChange={(e) =>
                                form.setData('promotion_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.promotion_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="promotion-effective">
                            {t('Effective Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="promotion-effective"
                            type="date"
                            required
                            min={form.data.promotion_date || undefined}
                            value={form.data.effective_date}
                            onChange={(e) =>
                                form.setData('effective_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.effective_date} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="promotion-salary">
                            {t('Salary Adjustment')}
                        </Label>
                        <Input
                            id="promotion-salary"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.salary_adjustment}
                            onChange={(e) =>
                                form.setData(
                                    'salary_adjustment',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.salary_adjustment} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="promotion-reason">{t('Reason')}</Label>
                        <textarea
                            id="promotion-reason"
                            rows={3}
                            className={textareaClass}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DocumentInput
                        id="promotion-document"
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
                title="Update Promotion Status"
                description="Approving moves the employee to the new designation."
                processing={statusForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (statusFor) {
                        statusForm.submit(
                            promotionRoutes.changeStatus(statusFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setStatusFor(null),
                            },
                        );
                    }
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="promotion-new-status">
                        {t('Status')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="promotion-new-status"
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData('status', e.target.value)
                        }
                    >
                        {STATUSES.filter(
                            (status) =>
                                (status !== 'approved' ||
                                    can('approve-promotions')) &&
                                (status !== 'rejected' ||
                                    can('reject-promotions')),
                        ).map((status) => (
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

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-3">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-950">
                                <TrendingUp className="size-5" />
                            </span>
                            {t('Promotion Details')}
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
                            <div>
                                <div className="mb-2 font-medium text-muted-foreground">
                                    {t('Designation Change')}
                                </div>
                                <div className="grid items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/40">
                                        <div className="text-xs text-muted-foreground">
                                            {t('Previous')}
                                        </div>
                                        <div className="flex items-center gap-2 font-semibold">
                                            <Briefcase className="size-4 text-red-500" />
                                            {viewing.previous_designation
                                                ?.name ?? '—'}
                                        </div>
                                    </div>
                                    <ArrowRight className="mx-auto size-5 text-emerald-600 max-sm:rotate-90" />
                                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900 dark:bg-emerald-950/40">
                                        <div className="text-xs text-muted-foreground">
                                            {t('New')}
                                        </div>
                                        <div className="flex items-center gap-2 font-semibold">
                                            <Briefcase className="size-4 text-emerald-600" />
                                            {viewing.designation.name}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div className="mb-2 font-medium text-muted-foreground">
                                    {t('Timeline')}
                                </div>
                                <ol className="grid gap-3 border-s-2 border-emerald-200 ps-4">
                                    {(
                                        [
                                            [
                                                'Promotion Date',
                                                viewing.promotion_date,
                                            ],
                                            [
                                                'Effective Date',
                                                viewing.effective_date,
                                            ],
                                        ] as const
                                    ).map(([label, value]) => (
                                        <li key={label}>
                                            <div className="text-xs text-muted-foreground">
                                                {t(label)}
                                            </div>
                                            <div className="font-semibold">
                                                {date(value)}
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                            <div>
                                <div className="mb-1 flex items-center gap-2 font-medium text-muted-foreground">
                                    <FileText className="size-4" />
                                    {t('Document')}
                                </div>
                                {viewing.file_name ? (
                                    <a
                                        href={promotionRoutes.document.url(
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
                            <div>
                                <div className="mb-1 flex items-center gap-2 font-medium text-muted-foreground">
                                    <FileText className="size-4" />
                                    {t('Reason for Promotion')}
                                </div>
                                <p className="font-medium whitespace-pre-line">
                                    {viewing.reason || '—'}
                                </p>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This promotion will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(promotionRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Promotions.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Lifecycle', href: promotionRoutes.index() },
        { title: 'Promotions', href: promotionRoutes.index() },
    ],
};
