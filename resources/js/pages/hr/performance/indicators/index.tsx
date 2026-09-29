import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Plus, SquarePen, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell } from '@/components/table-cells';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import indicatorRoutes from '@/routes/hr/performance/indicators';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Indicator = {
    id: number;
    category_id: number;
    name: string;
    description: string | null;
    measurement_unit: string;
    target_value: string | null;
    status: 'active' | 'inactive';
    created_at: string;
    category: Option;
};

const blank = {
    category_id: '' as number | string,
    name: '',
    description: '',
    measurement_unit: 'Rating',
    target_value: '',
    status: 'active' as Indicator['status'],
};

export default function Indicators({
    indicators,
    categories,
    units,
    statusCounts,
    filters,
}: {
    indicators: Paginated<Indicator>;
    categories: Option[];
    units: string[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<Indicator | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Indicator | null>(null);
    const form = useForm(blank);
    const url = indicatorRoutes.index();

    const openForm = (indicator: Indicator | null) => {
        setEditing(indicator);
        form.clearErrors();
        form.setData(
            indicator
                ? {
                      category_id: indicator.category_id,
                      name: indicator.name,
                      description: indicator.description ?? '',
                      measurement_unit: indicator.measurement_unit,
                      target_value: indicator.target_value ?? '',
                      status: indicator.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Indicator>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => (
                <div>
                    <div className="font-medium">{row.name}</div>
                    <div className="line-clamp-1 max-w-md text-muted-foreground">
                        {row.category.name}
                    </div>
                </div>
            ),
        },
        {
            key: 'measurement_unit',
            label: 'Measurement Unit',
            sortable: true,
            render: (row) => t(row.measurement_unit),
        },
        {
            key: 'target_value',
            label: 'Target Value',
            render: (row) => row.target_value ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (row) => <DateCell value={row.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Indicators')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Indicators"
                    description="Define the measurable indicators employees are rated on."
                    action={
                        can('create-performance-indicators') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Indicator')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={indicators}
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
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="category_id"
                            label="All Categories"
                            options={categories}
                        />
                    }
                    actions={(indicator) => (
                        <>
                            {can('edit-performance-indicators') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(indicator)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            indicator.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                indicatorRoutes.toggleStatus(
                                                    indicator.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {indicator.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <Unlock />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-performance-indicators') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(indicator)}
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
                title={editing ? 'Edit Indicator' : 'Add Indicator'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? indicatorRoutes.update(editing.id)
                            : indicatorRoutes.store(),
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
                        <Label htmlFor="indicator-category">
                            {t('Category')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="indicator-category"
                            required
                            value={form.data.category_id}
                            onChange={(e) =>
                                form.setData('category_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Category')}</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.category_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="indicator-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="indicator-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="indicator-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="indicator-description"
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
                        <Label htmlFor="indicator-unit">
                            {t('Measurement Unit')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="indicator-unit"
                            value={form.data.measurement_unit}
                            onChange={(e) =>
                                form.setData('measurement_unit', e.target.value)
                            }
                        >
                            {units.map((unit) => (
                                <option key={unit} value={unit}>
                                    {t(unit)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.measurement_unit} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="indicator-target">
                            {t('Target Value')}
                        </Label>
                        <Input
                            id="indicator-target"
                            placeholder="4/5, 95%, 10"
                            value={form.data.target_value}
                            onChange={(e) =>
                                form.setData('target_value', e.target.value)
                            }
                        />
                        <InputError message={form.errors.target_value} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="indicator-status">{t('Status')}</Label>
                        <SelectField
                            id="indicator-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Indicator['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This indicator will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(indicatorRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Indicators.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: indicatorRoutes.index() },
        { title: 'Indicators', href: indicatorRoutes.index() },
    ],
};
