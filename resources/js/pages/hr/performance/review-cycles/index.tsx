import { Head, router, useForm } from '@inertiajs/react';
import { RefreshCw, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SideForm, SideFormLayout } from '@/components/side-form';
import { StatusBadge } from '@/components/status-badge';
import { ClampedText } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import cycleRoutes from '@/routes/hr/performance/review-cycles';
import type { Paginated, TableFilters } from '@/types';

type ReviewCycle = {
    id: number;
    name: string;
    frequency: string;
    description: string | null;
    status: 'active' | 'inactive';
};

const blank = {
    name: '',
    frequency: '',
    description: '',
    status: 'active' as ReviewCycle['status'],
};

export default function ReviewCycles({
    reviewCycles,
    frequencies,
    filters,
}: {
    reviewCycles: Paginated<ReviewCycle>;
    frequencies: string[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<ReviewCycle | null>(null);
    const [deleting, setDeleting] = useState<ReviewCycle | null>(null);
    const form = useForm(blank);
    const url = cycleRoutes.index();
    const showForm = editing
        ? can('edit-review-cycles')
        : can('create-review-cycles');

    const edit = (cycle: ReviewCycle | null) => {
        setEditing(cycle);
        form.clearErrors();
        form.setData(
            cycle
                ? {
                      name: cycle.name,
                      frequency: cycle.frequency,
                      description: cycle.description ?? '',
                      status: cycle.status,
                  }
                : blank,
        );
    };

    return (
        <>
            <Head title={t('Review Cycles')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Review Cycles"
                    description="Manage performance review cycles and their schedules."
                />

                <SideFormLayout
                    form={
                        showForm && (
                            <SideForm
                                title={
                                    editing
                                        ? 'Edit Review Cycle'
                                        : 'Add New Review Cycle'
                                }
                                description={
                                    editing
                                        ? 'Update the details of this review cycle'
                                        : 'Fill in the details to create a new review cycle'
                                }
                                submitLabel={
                                    editing
                                        ? 'Update Review Cycle'
                                        : 'Add Review Cycle'
                                }
                                processing={form.processing}
                                onSubmit={() =>
                                    form.submit(
                                        editing
                                            ? cycleRoutes.update(editing.id)
                                            : cycleRoutes.store(),
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
                                    <Label htmlFor="cycle-name">
                                        {t('Review Cycle Name')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="cycle-name"
                                        required
                                        placeholder={t(
                                            'e.g., Annual Review 2026',
                                        )}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="cycle-frequency">
                                        {t('Frequency')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="cycle-frequency"
                                        required
                                        value={form.data.frequency}
                                        onChange={(e) =>
                                            form.setData(
                                                'frequency',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('Select frequency')}
                                        </option>
                                        {frequencies.map((f) => (
                                            <option key={f} value={f}>
                                                {t(f)}
                                            </option>
                                        ))}
                                    </SelectField>
                                    <InputError
                                        message={form.errors.frequency}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="cycle-description">
                                        {t('Description')}
                                    </Label>
                                    <textarea
                                        id="cycle-description"
                                        rows={3}
                                        placeholder={t(
                                            'Brief description of the review cycle',
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
                                    <Label htmlFor="cycle-status">
                                        {t('Status')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="cycle-status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target
                                                    .value as ReviewCycle['status'],
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
                        data={reviewCycles}
                        filters={filters}
                        url={url}
                        columns={[
                            {
                                key: 'name',
                                label: 'Name',
                                sortable: true,
                                render: (row) => (
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                                            <RefreshCw className="size-5" />
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
                                key: 'frequency',
                                label: 'Frequency',
                                sortable: true,
                                render: (row) => (
                                    <Badge
                                        variant="outline"
                                        className="border-blue-200 bg-blue-50 text-blue-700"
                                    >
                                        {t(row.frequency)}
                                    </Badge>
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
                            <>
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="frequency"
                                    label="All Frequencies"
                                    options={frequencies.map((f) => ({
                                        id: f,
                                        name: t(f),
                                    }))}
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
                        actions={(cycle) => (
                            <>
                                {can('edit-review-cycles') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => edit(cycle)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {can('delete-review-cycles') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(cycle)}
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
                description="This review cycle will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(cycleRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

ReviewCycles.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: cycleRoutes.index() },
        { title: 'Review Cycles', href: cycleRoutes.index() },
    ],
};
