import { Head, router, useForm } from '@inertiajs/react';
import { Boxes, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SideForm, SideFormLayout } from '@/components/side-form';
import { ClampedText } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import assetTypeRoutes from '@/routes/hr/asset-types';
import type { Paginated, TableFilters } from '@/types';

type AssetType = {
    id: number;
    name: string;
    description: string | null;
    assets_count: number;
    created_at: string;
};

const blank = { name: '', description: '' };

export default function AssetTypes({
    assetTypes,
    filters,
}: {
    assetTypes: Paginated<AssetType>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<AssetType | null>(null);
    const [deleting, setDeleting] = useState<AssetType | null>(null);
    const form = useForm(blank);
    const showForm = editing
        ? can('edit-asset-types')
        : can('create-asset-types');

    const edit = (type: AssetType | null) => {
        setEditing(type);
        form.clearErrors();
        form.setData(
            type
                ? { name: type.name, description: type.description ?? '' }
                : blank,
        );
    };

    return (
        <>
            <Head title={t('Asset Types')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Asset Types"
                    description="Categorize assets by type for better organization."
                />

                <SideFormLayout
                    form={
                        showForm && (
                            <SideForm
                                title={
                                    editing
                                        ? 'Edit Asset Type'
                                        : 'Add New Asset Type'
                                }
                                description={
                                    editing
                                        ? 'Update the details of this asset type'
                                        : 'Fill in the details to create a new asset type'
                                }
                                submitLabel={
                                    editing
                                        ? 'Update Asset Type'
                                        : 'Add Asset Type'
                                }
                                processing={form.processing}
                                onSubmit={() =>
                                    form.submit(
                                        editing
                                            ? assetTypeRoutes.update(editing.id)
                                            : assetTypeRoutes.store(),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => edit(null),
                                        },
                                    )
                                }
                                onCancel={
                                    editing ? () => edit(null) : undefined
                                }
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="asset-type-name">
                                        {t('Asset Type Name')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="asset-type-name"
                                        required
                                        placeholder={t(
                                            'e.g., Laptop, Furniture, Vehicle',
                                        )}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="asset-type-description">
                                        {t('Description')}
                                    </Label>
                                    <textarea
                                        id="asset-type-description"
                                        rows={3}
                                        placeholder={t(
                                            'Brief description of the asset type',
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
                            </SideForm>
                        )
                    }
                >
                    <DataTable
                        data={assetTypes}
                        filters={filters}
                        url={assetTypeRoutes.index()}
                        columns={[
                            {
                                key: 'name',
                                label: 'Name',
                                sortable: true,
                                render: (row) => (
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                                            <Boxes className="size-5" />
                                        </span>
                                        <div>
                                            <div className="font-medium">
                                                {row.name}
                                            </div>
                                            <div className="max-w-md">
                                                <ClampedText
                                                    text={row.description}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                key: 'assets_count',
                                label: 'Assets',
                                render: (row) => (
                                    <Badge variant="outline">
                                        {t(':count assets', {
                                            count: row.assets_count,
                                        })}
                                    </Badge>
                                ),
                            },
                        ]}
                        actions={(type) => (
                            <>
                                {can('edit-asset-types') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => edit(type)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {can('delete-asset-types') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(type)}
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
                description="This asset type will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(assetTypeRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

AssetTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Asset Management', href: assetTypeRoutes.index() },
        { title: 'Asset Types', href: assetTypeRoutes.index() },
    ],
};
