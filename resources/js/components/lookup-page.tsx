import { Head, router, useForm } from '@inertiajs/react';
import { Lock, LockOpen, SquarePen, Trash2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
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
import { useTranslation } from '@/hooks/use-translation';
import type { Paginated, TableFilters } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

export type LookupRecord = {
    id: number;
    name: string;
    description: string | null;
    status: 'active' | 'inactive';
};

type Routes = {
    index: () => RouteDefinition<'get'>;
    store: () => RouteDefinition<'post'>;
    update: (id: number) => RouteDefinition<'put'>;
    /** Omit where the demo has no lock (activate/deactivate) action. */
    toggleStatus?: (id: number) => RouteDefinition<'put'>;
    destroy: (id: number) => RouteDefinition<'delete'>;
};

const blank = {
    name: '',
    description: '',
    status: 'active' as LookupRecord['status'],
};

/**
 * The demo's name / description / status lookup screen (award types, job categories, job types, candidate
 * sources, interview types): an add/edit form beside the list, with lock to activate or deactivate.
 */
export function LookupPage({
    records,
    filters,
    routes,
    module,
    title,
    description,
    singular,
    nameLabel,
    namePlaceholder,
    icon: Icon,
    iconClass,
}: {
    records: Paginated<LookupRecord>;
    filters: TableFilters;
    routes: Routes;
    /** Permission suffix: create-{module}, edit-{module}, delete-{module}. */
    module: string;
    title: string;
    description: string;
    /** "Job Category" → "Add New Job Category", "Update Job Category"… */
    singular: string;
    nameLabel: string;
    namePlaceholder: string;
    icon: LucideIcon;
    /** Colours of the icon tile. */
    iconClass: string;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<LookupRecord | null>(null);
    const [deleting, setDeleting] = useState<LookupRecord | null>(null);
    const form = useForm(blank);
    const url = routes.index();
    const toggleStatus = routes.toggleStatus;
    const lower = singular.toLowerCase();
    const showForm = editing ? can(`edit-${module}`) : can(`create-${module}`);

    const edit = (record: LookupRecord | null) => {
        setEditing(record);
        form.clearErrors();
        form.setData(
            record
                ? {
                      name: record.name,
                      description: record.description ?? '',
                      status: record.status,
                  }
                : blank,
        );
    };

    return (
        <>
            <Head title={t(title)} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={title} description={description} />

                <SideFormLayout
                    form={
                        showForm && (
                            <SideForm
                                title={
                                    editing
                                        ? `Edit ${singular}`
                                        : `Add New ${singular}`
                                }
                                description={
                                    editing
                                        ? `Update the details of this ${lower}`
                                        : `Fill in the details to create a new ${lower}`
                                }
                                submitLabel={
                                    editing
                                        ? `Update ${singular}`
                                        : `Add ${singular}`
                                }
                                processing={form.processing}
                                onSubmit={() =>
                                    form.submit(
                                        editing
                                            ? routes.update(editing.id)
                                            : routes.store(),
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
                                    <Label htmlFor="lookup-name">
                                        {t(nameLabel)}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="lookup-name"
                                        required
                                        placeholder={t(namePlaceholder)}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="lookup-description">
                                        {t('Description')}
                                    </Label>
                                    <textarea
                                        id="lookup-description"
                                        rows={3}
                                        placeholder={t(
                                            `Brief description of the ${lower}`,
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
                                    <Label htmlFor="lookup-status">
                                        {t('Status')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="lookup-status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target
                                                    .value as LookupRecord['status'],
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
                        data={records}
                        filters={filters}
                        url={url}
                        columns={[
                            {
                                key: 'name',
                                label: 'Name',
                                sortable: true,
                                render: (row) => (
                                    <div className="flex items-start gap-3">
                                        <span
                                            className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${iconClass}`}
                                        >
                                            <Icon className="size-5" />
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
                                key: 'status',
                                label: 'Status',
                                render: (row) => (
                                    <StatusBadge status={row.status} />
                                ),
                            },
                        ]}
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
                        actions={(record) => (
                            <>
                                {can(`edit-${module}`) && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => edit(record)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {toggleStatus && can(`edit-${module}`) && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            record.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            record.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                toggleStatus(record.id),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {record.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                )}
                                {can(`delete-${module}`) && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(record)}
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
                description={`This ${lower} will be permanently deleted.`}
                onConfirm={() =>
                    deleting &&
                    router.delete(routes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}
