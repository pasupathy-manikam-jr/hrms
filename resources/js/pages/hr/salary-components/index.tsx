import { Head, router, useForm } from '@inertiajs/react';
import {
    Lock,
    LockOpen,
    SquarePen,
    Trash2,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SideForm, SideFormLayout } from '@/components/side-form';
import { StatusBadge } from '@/components/status-badge';
import { ClampedText } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import componentRoutes from '@/routes/hr/salary-components';
import type { Paginated, TableFilters } from '@/types';

type SalaryComponent = {
    id: number;
    name: string;
    description: string | null;
    type: 'earning' | 'deduction';
    calculation_type: 'fixed' | 'percentage';
    default_amount: string;
    percentage_of_basic: string | null;
    is_taxable: boolean;
    is_mandatory: boolean;
    status: 'active' | 'inactive';
};

const blank = {
    name: '',
    description: '',
    type: 'earning' as SalaryComponent['type'],
    calculation_type: 'fixed' as SalaryComponent['calculation_type'],
    default_amount: '',
    percentage_of_basic: '',
    is_taxable: false,
    is_mandatory: false,
    status: 'active' as SalaryComponent['status'],
};

export default function SalaryComponents({
    salaryComponents,
    filters,
}: {
    salaryComponents: Paginated<SalaryComponent>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const url = componentRoutes.index();
    const [editing, setEditing] = useState<SalaryComponent | null>(null);
    const [deleting, setDeleting] = useState<SalaryComponent | null>(null);
    const form = useForm(blank);

    const showForm = editing
        ? can('edit-salary-components')
        : can('create-salary-components');

    const openForm = (component: SalaryComponent | null) => {
        setEditing(component);
        form.clearErrors();
        form.setData(
            component
                ? {
                      ...component,
                      description: component.description ?? '',
                      percentage_of_basic: component.percentage_of_basic ?? '',
                  }
                : blank,
        );
    };

    const submit = () =>
        form.submit(
            editing
                ? componentRoutes.update(editing.id)
                : componentRoutes.store(),
            { preserveScroll: true, onSuccess: () => openForm(null) },
        );

    const columns: Column<SalaryComponent>[] = [
        {
            key: 'name',
            label: 'Component',
            render: (c) => {
                const earning = c.type === 'earning';
                const Icon = earning ? TrendingUp : TrendingDown;

                return (
                    <div className="flex items-center gap-3">
                        <span
                            className={cn(
                                'flex size-10 shrink-0 items-center justify-center rounded-lg',
                                earning
                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950'
                                    : 'bg-red-50 text-red-600 dark:bg-red-950',
                            )}
                        >
                            <Icon className="size-5" />
                        </span>
                        <div className="min-w-48">
                            <div className="font-medium">{c.name}</div>
                            <ClampedText text={c.description} />
                        </div>
                    </div>
                );
            },
        },
        {
            key: 'type',
            label: 'Type',
            render: (c) => <StatusBadge status={c.type} />,
        },
        {
            key: 'default_amount',
            label: 'Amount',
            render: (c) => (
                <div className="whitespace-nowrap">
                    <div className="font-medium">
                        {c.calculation_type === 'percentage'
                            ? `${Number(c.percentage_of_basic).toFixed(2)}%`
                            : money(Number(c.default_amount))}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {t(
                            c.calculation_type === 'percentage'
                                ? 'Of basic salary'
                                : 'Fixed amount',
                        )}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (c) => <StatusBadge status={c.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Salary Components')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Salary Components"
                    description="Define earnings and deductions used in payroll calculations."
                />

                <SideFormLayout
                    form={
                        showForm && (
                            <SideForm
                                title={
                                    editing
                                        ? 'Edit Component'
                                        : 'Add New Component'
                                }
                                description={
                                    editing
                                        ? 'Update the details of this salary component'
                                        : 'Fill in the details to create a new salary component'
                                }
                                submitLabel={
                                    editing
                                        ? 'Update Component'
                                        : 'Add Component'
                                }
                                processing={form.processing}
                                onSubmit={submit}
                                onCancel={
                                    editing ? () => openForm(null) : undefined
                                }
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="component-name">
                                        {t('Component Name')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="component-name"
                                        required
                                        placeholder={t(
                                            'e.g., Basic Salary, Housing Allowance, PCB',
                                        )}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="component-description">
                                        {t('Description')}
                                    </Label>
                                    <textarea
                                        id="component-description"
                                        rows={3}
                                        placeholder={t(
                                            'Brief description of the component',
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
                                <div className="grid gap-2">
                                    <Label htmlFor="component-type">
                                        {t('Type')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="component-type"
                                        value={form.data.type}
                                        onChange={(e) =>
                                            form.setData(
                                                'type',
                                                e.target
                                                    .value as SalaryComponent['type'],
                                            )
                                        }
                                    >
                                        <option value="earning">
                                            {t('Earning')}
                                        </option>
                                        <option value="deduction">
                                            {t('Deduction')}
                                        </option>
                                    </SelectField>
                                    <InputError message={form.errors.type} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="component-calculation">
                                        {t('Calculation Type')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="component-calculation"
                                        value={form.data.calculation_type}
                                        onChange={(e) =>
                                            form.setData(
                                                'calculation_type',
                                                e.target
                                                    .value as SalaryComponent['calculation_type'],
                                            )
                                        }
                                    >
                                        <option value="fixed">
                                            {t('Fixed Amount')}
                                        </option>
                                        <option value="percentage">
                                            {t('Percentage of Basic')}
                                        </option>
                                    </SelectField>
                                    <InputError
                                        message={form.errors.calculation_type}
                                    />
                                </div>
                                {form.data.calculation_type === 'fixed' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="component-amount">
                                            {t('Fixed Amount')}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="component-amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            required
                                            value={form.data.default_amount}
                                            onChange={(e) =>
                                                form.setData(
                                                    'default_amount',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.default_amount}
                                        />
                                    </div>
                                ) : (
                                    <div className="grid gap-2">
                                        <Label htmlFor="component-percentage">
                                            {t('Percentage of Basic')} (%)
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="component-percentage"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            required
                                            value={
                                                form.data.percentage_of_basic
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'percentage_of_basic',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors.percentage_of_basic
                                            }
                                        />
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="component-status">
                                        {t('Status')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="component-status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target
                                                    .value as SalaryComponent['status'],
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
                        data={salaryComponents}
                        columns={columns}
                        filters={filters}
                        url={url}
                        toolbar={
                            <>
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="type"
                                    label="All Types"
                                    options={[
                                        { id: 'earning', name: t('Earning') },
                                        {
                                            id: 'deduction',
                                            name: t('Deduction'),
                                        },
                                    ]}
                                />
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="calculation_type"
                                    label="All Calculations"
                                    options={[
                                        {
                                            id: 'fixed',
                                            name: t('Fixed Amount'),
                                        },
                                        {
                                            id: 'percentage',
                                            name: t('Percentage of Basic'),
                                        },
                                    ]}
                                />
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
                            </>
                        }
                        actions={(component) => (
                            <>
                                {can('edit-salary-components') && (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edit Component')}
                                            onClick={() => openForm(component)}
                                        >
                                            <SquarePen />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t(
                                                component.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                            title={t(
                                                component.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                            onClick={() =>
                                                router.put(
                                                    componentRoutes.toggleStatus(
                                                        component.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {component.status === 'active' ? (
                                                <Lock />
                                            ) : (
                                                <LockOpen />
                                            )}
                                        </Button>
                                    </>
                                )}
                                {can('delete-salary-components') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete Component')}
                                        onClick={() => setDeleting(component)}
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
                description="This salary component will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(componentRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

SalaryComponents.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payroll Management', href: componentRoutes.index() },
        { title: 'Salary Components', href: componentRoutes.index() },
    ],
};
