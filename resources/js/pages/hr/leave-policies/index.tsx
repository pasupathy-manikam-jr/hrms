import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Eye,
    Lock,
    LockOpen,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
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
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import leavePolicyRoutes from '@/routes/hr/leave-policies';
import type { Paginated, TableFilters } from '@/types';

type LeaveTypeOption = { id: number; name: string; color: string };

type LeavePolicy = {
    id: number;
    name: string;
    description: string | null;
    leave_type_id: number;
    accrual_type: 'yearly' | 'monthly';
    accrual_rate: string;
    carry_forward_limit: number;
    min_days_per_application: number;
    max_days_per_application: number;
    requires_approval: boolean;
    status: 'active' | 'inactive';
    created_at: string;
    leave_type: LeaveTypeOption | null;
};

const blank = {
    name: '',
    description: '',
    leave_type_id: '' as number | string,
    accrual_type: 'yearly' as LeavePolicy['accrual_type'],
    accrual_rate: '0' as number | string,
    carry_forward_limit: 0 as number | string,
    min_days_per_application: 1 as number | string,
    max_days_per_application: 1 as number | string,
    requires_approval: true,
    status: 'active' as LeavePolicy['status'],
};

const numberFields = [
    ['accrual_rate', 'Accrual Rate', '0.01'],
    ['carry_forward_limit', 'Carry Forward Limit', '1'],
    ['min_days_per_application', 'Min Days Per Application', '1'],
    ['max_days_per_application', 'Max Days Per Application', '1'],
] as const;

export default function LeavePolicies({
    leavePolicies,
    leaveTypes,
    statusCounts,
    filters,
}: {
    leavePolicies: Paginated<LeavePolicy>;
    leaveTypes: LeaveTypeOption[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const { date } = useFormat();
    const url = leavePolicyRoutes.index();
    const [editing, setEditing] = useState<LeavePolicy | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<LeavePolicy | null>(null);
    const [viewing, setViewing] = useState<LeavePolicy | null>(null);
    const form = useForm(blank);

    const openForm = (policy: LeavePolicy | null) => {
        setEditing(policy);
        form.clearErrors();
        form.setData(
            policy
                ? {
                      name: policy.name,
                      description: policy.description ?? '',
                      leave_type_id: policy.leave_type_id,
                      accrual_type: policy.accrual_type,
                      accrual_rate: policy.accrual_rate,
                      carry_forward_limit: policy.carry_forward_limit,
                      min_days_per_application: policy.min_days_per_application,
                      max_days_per_application: policy.max_days_per_application,
                      requires_approval: policy.requires_approval,
                      status: policy.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<LeavePolicy>[] = [
        {
            key: 'name',
            label: 'Policy Name',
            sortable: true,
            render: (p) => <span className="font-medium">{p.name}</span>,
        },
        {
            key: 'leave_type',
            label: 'Leave Type',
            render: (p) =>
                p.leave_type && (
                    <span className="flex items-center gap-2">
                        <span
                            className="size-3 shrink-0 rounded-full"
                            style={{ backgroundColor: p.leave_type.color }}
                        />
                        {p.leave_type.name}
                    </span>
                ),
        },
        {
            key: 'carry_forward_limit',
            label: 'Carry Forward',
            render: (p) => (
                <span className="whitespace-nowrap">
                    {t(':days days', { days: p.carry_forward_limit })}
                </span>
            ),
        },
        {
            key: 'requires_approval',
            label: 'Approval',
            render: (p) => (
                <StatusBadge
                    status={p.requires_approval ? 'required' : 'not_required'}
                />
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (p) => <StatusBadge status={p.status} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (p) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(p.created_at)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Leave Policies')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Leave Policies"
                    description="Manage leave policies for your organization."
                    action={
                        can('create-leave-policies') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Leave Policy')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={leavePolicies}
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
                            name="leave_type_id"
                            label="All Leave Types"
                            options={leaveTypes}
                        />
                    }
                    actions={(policy) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(policy)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-leave-policies') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(policy)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            policy.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            policy.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                leavePolicyRoutes.toggleStatus(
                                                    policy.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {policy.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-leave-policies') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(policy)}
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
                title={editing ? 'Edit Leave Policy' : 'Add Leave Policy'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? leavePolicyRoutes.update(editing.id)
                            : leavePolicyRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="policy-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="policy-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="policy-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="policy-description"
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
                        <Label htmlFor="policy-type">
                            {t('Leave Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="policy-type"
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
                        <Label htmlFor="policy-accrual-type">
                            {t('Accrual Type')}
                        </Label>
                        <SelectField
                            id="policy-accrual-type"
                            value={form.data.accrual_type}
                            onChange={(e) =>
                                form.setData(
                                    'accrual_type',
                                    e.target
                                        .value as LeavePolicy['accrual_type'],
                                )
                            }
                        >
                            <option value="yearly">{t('Yearly')}</option>
                            <option value="monthly">{t('Monthly')}</option>
                        </SelectField>
                        <InputError message={form.errors.accrual_type} />
                    </div>
                    {numberFields.map(([field, label, step]) => (
                        <div key={field} className="grid gap-2">
                            <Label htmlFor={`policy-${field}`}>
                                {t(label)}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id={`policy-${field}`}
                                type="number"
                                min={0}
                                step={step}
                                required
                                value={form.data[field]}
                                onChange={(e) =>
                                    form.setData(field, e.target.value)
                                }
                            />
                            <InputError message={form.errors[field]} />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="policy-status">{t('Status')}</Label>
                        <SelectField
                            id="policy-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as LeavePolicy['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="flex items-center gap-3 self-end pb-1.5">
                        <Switch
                            id="policy-approval"
                            checked={form.data.requires_approval}
                            onCheckedChange={(checked) =>
                                form.setData('requires_approval', checked)
                            }
                        />
                        <Label htmlFor="policy-approval">
                            {t('Requires Approval')}
                        </Label>
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{viewing?.name}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            {(
                                [
                                    ['Leave Type', viewing.leave_type?.name],
                                    [
                                        'Accrual',
                                        `${viewing.accrual_rate} (${t(viewing.accrual_type === 'monthly' ? 'Monthly' : 'Yearly')})`,
                                    ],
                                    [
                                        'Carry Forward',
                                        t(':days days', {
                                            days: viewing.carry_forward_limit,
                                        }),
                                    ],
                                    [
                                        'Days Per Application',
                                        `${viewing.min_days_per_application} – ${viewing.max_days_per_application}`,
                                    ],
                                    ['Created At', date(viewing.created_at)],
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
                                    {t('Approval')}
                                </dt>
                                <dd>
                                    <StatusBadge
                                        status={
                                            viewing.requires_approval
                                                ? 'required'
                                                : 'not_required'
                                        }
                                    />
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
                                    {t('Description')}
                                </dt>
                                <dd className="font-medium">
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
                description="This leave policy will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(leavePolicyRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

LeavePolicies.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Management', href: leavePolicyRoutes.index() },
        { title: 'Leave Policies', href: leavePolicyRoutes.index() },
    ],
};
