import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, Lock, LockOpen, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SideForm, SideFormLayout } from '@/components/side-form';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect } from '@/components/table-filters';
import { ClampedText } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import leaveTypeRoutes from '@/routes/hr/leave-types';
import type { Paginated, TableFilters } from '@/types';

type LeaveType = {
    id: number;
    name: string;
    description: string | null;
    max_days_per_year: number;
    is_paid: boolean;
    color: string;
    status: 'active' | 'inactive';
    created_at: string;
};

const blank = {
    name: '',
    description: '',
    max_days_per_year: 0 as number | string,
    is_paid: true,
    color: '#3B82F6',
    status: 'active' as LeaveType['status'],
};

function PaidBadge({ paid }: { paid: boolean }) {
    const { t } = useTranslation();

    return (
        <Badge
            variant="outline"
            className={
                paid
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : 'border-gray-200 bg-gray-50 text-gray-600'
            }
        >
            {t(paid ? 'Paid' : 'Unpaid')}
        </Badge>
    );
}

export default function LeaveTypes({
    leaveTypes,
    filters,
}: {
    leaveTypes: Paginated<LeaveType>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = leaveTypeRoutes.index();
    const [editing, setEditing] = useState<LeaveType | null>(null);
    const [deleting, setDeleting] = useState<LeaveType | null>(null);
    const form = useForm(blank);
    const showForm = editing
        ? can('edit-leave-types')
        : can('create-leave-types');

    const edit = (leaveType: LeaveType | null) => {
        setEditing(leaveType);
        form.clearErrors();
        form.setData(
            leaveType
                ? {
                      name: leaveType.name,
                      description: leaveType.description ?? '',
                      max_days_per_year: leaveType.max_days_per_year,
                      is_paid: leaveType.is_paid,
                      color: leaveType.color,
                      status: leaveType.status,
                  }
                : blank,
        );
    };

    const submit = () =>
        form.submit(
            editing
                ? leaveTypeRoutes.update(editing.id)
                : leaveTypeRoutes.store(),
            { preserveScroll: true, onSuccess: () => edit(null) },
        );

    const columns: Column<LeaveType>[] = [
        {
            key: 'name',
            label: 'Leave Type',
            sortable: true,
            className: 'min-w-64',
            render: (l) => (
                <div className="flex items-start gap-3">
                    <span
                        className="flex size-10 shrink-0 items-center justify-center rounded-lg text-white"
                        style={{ backgroundColor: l.color }}
                    >
                        <CalendarDays className="size-5" />
                    </span>
                    <div className="min-w-0">
                        <div className="font-medium">{l.name}</div>
                        <ClampedText text={l.description} limit={60} />
                    </div>
                </div>
            ),
        },
        {
            key: 'max_days_per_year',
            label: 'Days/Year',
            sortable: true,
            render: (l) => (
                <span className="whitespace-nowrap">
                    {t(':days Days', { days: l.max_days_per_year })}
                </span>
            ),
        },
        {
            key: 'is_paid',
            label: 'Payment Type',
            render: (l) => <PaidBadge paid={l.is_paid} />,
        },
        {
            key: 'status',
            label: 'Status',
            render: (l) => <StatusBadge status={l.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Leave Types')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Leave Types"
                    description="Manage leave types and their configurations."
                />

                <SideFormLayout
                    form={
                        showForm && (
                            <SideForm
                                title={
                                    editing
                                        ? 'Edit Leave Type'
                                        : 'Add New Leave Type'
                                }
                                description={
                                    editing
                                        ? 'Update the details of this leave type'
                                        : 'Fill in the details to create a new leave type'
                                }
                                submitLabel={
                                    editing
                                        ? 'Update Leave Type'
                                        : 'Add Leave Type'
                                }
                                processing={form.processing}
                                onSubmit={submit}
                                onCancel={
                                    editing ? () => edit(null) : undefined
                                }
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="leave-type-name">
                                        {t('Leave Type Name')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="leave-type-name"
                                        required
                                        placeholder={t(
                                            'e.g., Casual Leave, Sick Leave',
                                        )}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="leave-type-description">
                                        {t('Description')}
                                    </Label>
                                    <textarea
                                        id="leave-type-description"
                                        rows={3}
                                        placeholder={t(
                                            'Brief description of the leave policies',
                                        )}
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.description}
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="leave-type-max">
                                            {t('Max Days / Year')}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="leave-type-max"
                                            type="number"
                                            min={0}
                                            required
                                            value={form.data.max_days_per_year}
                                            onChange={(e) =>
                                                form.setData(
                                                    'max_days_per_year',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="leave-type-color">
                                            {t('Color')}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <div className="flex gap-2">
                                            <Input
                                                id="leave-type-color"
                                                type="color"
                                                className="h-9 w-12 shrink-0 p-1"
                                                value={form.data.color}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'color',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                aria-label={t('Color code')}
                                                className="font-mono uppercase"
                                                value={form.data.color}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'color',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                    <InputError
                                        message={form.errors.max_days_per_year}
                                    />
                                    <InputError message={form.errors.color} />
                                </div>
                                <div className="flex items-center justify-between gap-3 rounded-lg border p-4">
                                    <div>
                                        <Label htmlFor="leave-type-paid">
                                            {t('Paid Leave')}
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'Employees will receive salary for these days',
                                            )}
                                        </p>
                                    </div>
                                    <Switch
                                        id="leave-type-paid"
                                        checked={form.data.is_paid}
                                        onCheckedChange={(checked) =>
                                            form.setData('is_paid', checked)
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="leave-type-status">
                                        {t('Status')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="leave-type-status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target
                                                    .value as LeaveType['status'],
                                            )
                                        }
                                    >
                                        <option value="active">
                                            {t('Active')}
                                        </option>
                                        <option value="inactive">
                                            {t('Inactive')}
                                        </option>
                                    </SelectField>
                                    <InputError message={form.errors.status} />
                                </div>
                            </SideForm>
                        )
                    }
                >
                    <DataTable
                        data={leaveTypes}
                        columns={columns}
                        filters={filters}
                        url={url}
                        toolbar={
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="status"
                                label="All Statuses"
                                options={[
                                    { id: 'active', name: t('Active') },
                                    { id: 'inactive', name: t('Inactive') },
                                ]}
                            />
                        }
                        actions={(leaveType) => (
                            <>
                                {can('edit-leave-types') && (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edit')}
                                            onClick={() => edit(leaveType)}
                                        >
                                            <SquarePen />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t(
                                                leaveType.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                            title={t(
                                                leaveType.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                            onClick={() =>
                                                router.put(
                                                    leaveTypeRoutes.toggleStatus(
                                                        leaveType.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {leaveType.status === 'active' ? (
                                                <Lock />
                                            ) : (
                                                <LockOpen />
                                            )}
                                        </Button>
                                    </>
                                )}
                                {can('delete-leave-types') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(leaveType)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </>
                        )}
                    />
                </SideFormLayout>
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This leave type will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(leaveTypeRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

LeaveTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Management', href: leaveTypeRoutes.index() },
        { title: 'Leave Types', href: leaveTypeRoutes.index() },
    ],
};
