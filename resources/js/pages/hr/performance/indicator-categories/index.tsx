import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Lock, Plus, SquarePen, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell } from '@/components/table-cells';
import { StatusBadge } from '@/components/status-badge';
import { ViewDialog } from '@/components/view-dialog';
import { StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import categoryRoutes from '@/routes/hr/performance/indicator-categories';
import type { Paginated, TableFilters } from '@/types';

type Category = {
    id: number;
    name: string;
    description: string | null;
    status: 'active' | 'inactive';
    created_at: string;
};

const blank = {
    name: '',
    description: '',
    status: 'active' as Category['status'],
};

export default function IndicatorCategories({
    categories,
    statusCounts,
    filters,
}: {
    categories: Paginated<Category>;
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [viewing, setViewing] = useState<Category | null>(null);
    const [editing, setEditing] = useState<Category | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Category | null>(null);
    const form = useForm(blank);
    const url = categoryRoutes.index();

    const openForm = (category: Category | null) => {
        setEditing(category);
        form.clearErrors();
        form.setData(
            category
                ? {
                      name: category.name,
                      description: category.description ?? '',
                      status: category.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Category>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'status',
            label: 'Status',
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
            <Head title={t('Indicator Categories')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Indicator Categories"
                    description="Manage categories used to group performance indicators."
                    action={
                        can('create-performance-indicator-categories') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Category')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={categories}
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
                    actions={(category) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(category)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-performance-indicator-categories') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(category)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            category.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                categoryRoutes.toggleStatus(
                                                    category.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {category.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <Unlock />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-performance-indicator-categories') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(category)}
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
                title={editing ? 'Edit Category' : 'Add Category'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? categoryRoutes.update(editing.id)
                            : categoryRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="category-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="category-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="category-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="category-description"
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
                        <Label htmlFor="category-status">{t('Status')}</Label>
                        <SelectField
                            id="category-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Category['status'],
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

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Indicator Category Details"
                fields={
                    viewing
                        ? [
                              ['Category Name', viewing.name],
                              [
                                  'Status',
                                  <StatusBadge
                                      key="status"
                                      status={viewing.status}
                                  />,
                              ],
                              ['Description', viewing.description, true],
                          ]
                        : []
                }
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This category and its indicators will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(categoryRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

IndicatorCategories.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: categoryRoutes.index() },
        { title: 'Indicator Categories', href: categoryRoutes.index() },
    ],
};
