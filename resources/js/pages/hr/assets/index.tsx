import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChartColumn,
    Eye,
    Plus,
    SquarePen,
    Trash2,
    Undo2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import {
    AssignAssetDialog,
    ReturnAssetDialog,
} from '@/components/asset-assignment-dialogs';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ExportButton, ImportButton } from '@/components/import-export';
import { DateCell, IdBadge } from '@/components/table-cells';
import {
    DateRangeFilter,
    FilterSelect,
    StatusTabs,
} from '@/components/table-filters';
import { PersonCell } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import assetRoutes from '@/routes/hr/assets';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Assignment = {
    id: number;
    assigned_at: string;
    returned_at: string | null;
    notes: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | null;
        user: { name: string; email: string; avatar: string | null };
    };
};

type Asset = {
    id: number;
    name: string;
    asset_type_id: number | null;
    serial_number: string | null;
    asset_code: string | null;
    purchase_date: string | null;
    purchase_cost: string;
    salvage_value: string;
    useful_life_years: number;
    current_value: number;
    status: 'available' | 'assigned' | 'under_maintenance' | 'disposed';
    condition: string;
    location: string | null;
    description: string | null;
    asset_type: Option | null;
    assignments: Assignment[];
};

const STATUS_LABELS: Record<string, string> = {
    all: 'All',
    available: 'Available',
    assigned: 'Assigned',
    under_maintenance: 'Under Maintenance',
    disposed: 'Disposed',
};

const CONDITIONS = ['new', 'good', 'fair', 'poor'];

const blank = {
    name: '',
    asset_type_id: '',
    serial_number: '',
    asset_code: '',
    purchase_date: '',
    purchase_cost: '',
    salvage_value: '',
    useful_life_years: '5',
    status: 'available',
    condition: 'good',
    location: '',
    description: '',
};

const TEXT_FIELDS = [
    ['serial_number', 'Serial Number', 'text'],
    ['asset_code', 'Asset Code', 'text'],
    ['purchase_date', 'Purchase Date', 'date'],
    ['purchase_cost', 'Purchase Cost', 'number'],
    ['salvage_value', 'Salvage Value', 'number'],
    ['useful_life_years', 'Useful Life (Years)', 'number'],
    ['location', 'Location', 'text'],
] as const;

const current = (asset: Asset) =>
    asset.assignments.find((a) => a.returned_at === null);

export default function Assets({
    assets,
    assetTypes,
    employees,
    statusCounts,
    filters,
}: {
    assets: Paginated<Asset>;
    assetTypes: Option[];
    employees: Option[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const url = assetRoutes.index();
    const [editing, setEditing] = useState<Asset | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Asset | null>(null);
    const [assigning, setAssigning] = useState<Asset | null>(null);
    const [returning, setReturning] = useState<Asset | null>(null);
    const form = useForm(blank);

    const openForm = (asset: Asset | null) => {
        setEditing(asset);
        form.clearErrors();
        form.setData(
            asset
                ? (Object.fromEntries(
                      Object.keys(blank).map((key) => [
                          key,
                          String(
                              key === 'status' && asset.status === 'assigned'
                                  ? 'available'
                                  : (asset[key as keyof typeof blank] ?? ''),
                          ),
                      ]),
                  ) as typeof blank)
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Asset>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (a) => (
                <div className="min-w-40">
                    <div className="font-medium">{a.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {a.asset_type?.name}
                    </div>
                </div>
            ),
        },
        {
            key: 'assigned_to',
            label: 'Assigned To',
            render: (a) => {
                const employee = current(a)?.employee;

                return employee ? (
                    <PersonCell
                        name={employee.user.name}
                        detail={employee.user.email}
                        src={employee.user.avatar}
                        gender={employee.gender}
                    />
                ) : (
                    <span className="text-muted-foreground">—</span>
                );
            },
        },
        {
            key: 'asset_code',
            label: 'Asset Code',
            render: (a) => (
                <div className="grid justify-items-start gap-1">
                    {a.asset_code && <IdBadge>{a.asset_code}</IdBadge>}
                    {a.serial_number && (
                        <span className="text-xs text-muted-foreground">
                            {a.serial_number}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (a) => <StatusBadge status={a.status} />,
        },
        {
            key: 'purchase_date',
            label: 'Purchase Date',
            sortable: true,
            render: (a) => <DateCell value={a.purchase_date} />,
        },
        {
            key: 'purchase_cost',
            label: 'Purchase Cost',
            sortable: true,
            render: (a) => (
                <span className="whitespace-nowrap">
                    {money(Number(a.purchase_cost))}
                </span>
            ),
        },
        {
            key: 'location',
            label: 'Location',
            render: (a) => a.location ?? '—',
        },
    ];

    return (
        <>
            <Head title={t('Assets')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Assets"
                    description="Manage company assets and their assignments."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {can('export-assets') && (
                                <ExportButton
                                    href={assetRoutes.export({
                                        query: filters,
                                    })}
                                />
                            )}
                            {can('import-assets') && (
                                <ImportButton
                                    title="Import Assets from CSV/Excel"
                                    action={assetRoutes.import()}
                                    templateHref={assetRoutes.download.template()}
                                    notes={t(
                                        'Ensure that the Asset Type matches an existing asset type. Status must be available, under_maintenance or disposed, and Condition new, good, fair or poor. Dates use the YYYY-MM-DD format.',
                                    )}
                                />
                            )}
                            <Button variant="outline" asChild>
                                <Link href={assetRoutes.dashboard()}>
                                    <ChartColumn /> {t('Dashboard')}
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={assetRoutes.depreciationReport()}>
                                    <ChartColumn /> {t('Depreciation Report')}
                                </Link>
                            </Button>
                            {can('create-assets') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Add Asset')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={assets}
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
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="condition"
                                label="All Conditions"
                                options={CONDITIONS.map((c) => ({
                                    id: c,
                                    name: t(
                                        c.charAt(0).toUpperCase() + c.slice(1),
                                    ),
                                }))}
                            />
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    moreFilters={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="asset_type_id"
                            label="All Types"
                            options={assetTypes}
                        />
                    }
                    actions={(asset) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={assetRoutes.show(asset.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {can('assign-assets') &&
                                asset.status === 'available' && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Assign')}
                                        onClick={() => setAssigning(asset)}
                                    >
                                        <UserPlus />
                                    </Button>
                                )}
                            {can('assign-assets') &&
                                asset.status === 'assigned' && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Return')}
                                        onClick={() => setReturning(asset)}
                                    >
                                        <Undo2 />
                                    </Button>
                                )}
                            {can('edit-assets') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(asset)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-assets') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(asset)}
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
                title={editing ? 'Edit Asset' : 'Add Asset'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? assetRoutes.update(editing.id)
                            : assetRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="asset-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="asset-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="asset-type">
                            {t('Asset Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="asset-type"
                            required
                            value={form.data.asset_type_id}
                            onChange={(e) =>
                                form.setData('asset_type_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Type')}</option>
                            {assetTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.asset_type_id} />
                    </div>
                    {TEXT_FIELDS.map(([key, label, type]) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={`asset-${key}`}>
                                {t(label)}
                                {(key === 'purchase_cost' ||
                                    key === 'useful_life_years') && (
                                    <span className="text-destructive">*</span>
                                )}
                            </Label>
                            <Input
                                id={`asset-${key}`}
                                type={type}
                                min={type === 'number' ? 0 : undefined}
                                step={
                                    key === 'useful_life_years'
                                        ? 1
                                        : type === 'number'
                                          ? '0.01'
                                          : undefined
                                }
                                required={
                                    key === 'purchase_cost' ||
                                    key === 'useful_life_years'
                                }
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="asset-condition">
                            {t('Condition')}
                        </Label>
                        <SelectField
                            id="asset-condition"
                            value={form.data.condition}
                            onChange={(e) =>
                                form.setData('condition', e.target.value)
                            }
                        >
                            {CONDITIONS.map((condition) => (
                                <option key={condition} value={condition}>
                                    {t(
                                        condition.charAt(0).toUpperCase() +
                                            condition.slice(1),
                                    )}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.condition} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="asset-status">{t('Status')}</Label>
                        <SelectField
                            id="asset-status"
                            value={form.data.status}
                            disabled={editing?.status === 'assigned'}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {['available', 'under_maintenance', 'disposed'].map(
                                (status) => (
                                    <option key={status} value={status}>
                                        {t(STATUS_LABELS[status])}
                                    </option>
                                ),
                            )}
                        </SelectField>
                        {editing?.status === 'assigned' && (
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Return the asset before changing its status.',
                                )}
                            </p>
                        )}
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="asset-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="asset-description"
                            rows={3}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>
            </FormDialog>

            <AssignAssetDialog
                key={`assign-${assigning?.id}`}
                asset={assigning}
                employees={employees}
                onClose={() => setAssigning(null)}
            />
            <ReturnAssetDialog
                key={`return-${returning?.id}`}
                asset={returning}
                onClose={() => setReturning(null)}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This asset and its assignment history will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(assetRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Assets.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Asset Management', href: assetRoutes.index() },
        { title: 'Assets', href: assetRoutes.index() },
    ],
};
