import { Head, router, useForm } from '@inertiajs/react';
import { Clock, Lock, LockOpen, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { SideForm, SideFormLayout } from '@/components/side-form';
import { StatusBadge } from '@/components/status-badge';
import { ClampedText } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import typeRoutes from '@/routes/meetings/meeting-types';
import type { Paginated, TableFilters } from '@/types';

type MeetingType = {
    id: number;
    name: string;
    description: string | null;
    color: string;
    default_duration: number;
    status: 'active' | 'inactive';
    meetings_count: number;
    created_at: string;
};

const blank = {
    name: '',
    description: '',
    color: '#3B82F6',
    default_duration: '60',
    status: 'active' as MeetingType['status'],
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function MeetingTypes({
    meetingTypes,
    filters,
}: {
    meetingTypes: Paginated<MeetingType>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = typeRoutes.index();
    const [editing, setEditing] = useState<MeetingType | null>(null);
    const [deleting, setDeleting] = useState<MeetingType | null>(null);
    const form = useForm(blank);
    const showForm = editing
        ? can('edit-meeting-types')
        : can('create-meeting-types');

    const edit = (type: MeetingType | null) => {
        setEditing(type);
        form.clearErrors();
        form.setData(
            type
                ? {
                      name: type.name,
                      description: type.description ?? '',
                      color: type.color,
                      default_duration: String(type.default_duration),
                      status: type.status,
                  }
                : blank,
        );
    };

    return (
        <>
            <Head title={t('Meeting Types')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Meeting Types"
                    description="Define categories for organizing meetings."
                />

                <SideFormLayout
                    form={
                        showForm && (
                            <SideForm
                                title={
                                    editing
                                        ? 'Edit Meeting Type'
                                        : 'Add New Meeting Type'
                                }
                                description={
                                    editing
                                        ? 'Update the details of this meeting type'
                                        : 'Fill in the details to create a new meeting type'
                                }
                                submitLabel={
                                    editing
                                        ? 'Update Meeting Type'
                                        : 'Add Meeting Type'
                                }
                                processing={form.processing}
                                onSubmit={() =>
                                    form.submit(
                                        editing
                                            ? typeRoutes.update(editing.id)
                                            : typeRoutes.store(),
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
                                    <Label htmlFor="type-name">
                                        {t('Name')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="type-name"
                                        required
                                        placeholder={t(
                                            'e.g., Client Call, Team Sync',
                                        )}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="type-description">
                                        {t('Description')}
                                    </Label>
                                    <textarea
                                        id="type-description"
                                        rows={3}
                                        placeholder={t(
                                            'Brief description of the meeting type',
                                        )}
                                        className={textareaClass}
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
                                    <Label htmlFor="type-color">
                                        {t('Color')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="type-color"
                                            type="color"
                                            className="w-14 p-1"
                                            value={form.data.color}
                                            onChange={(e) =>
                                                form.setData(
                                                    'color',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <Input
                                            aria-label={t('Color')}
                                            className="font-mono"
                                            value={form.data.color}
                                            onChange={(e) =>
                                                form.setData(
                                                    'color',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <InputError message={form.errors.color} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="type-duration">
                                        {t('Default Duration (minutes)')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="type-duration"
                                        type="number"
                                        min={5}
                                        step={5}
                                        required
                                        value={form.data.default_duration}
                                        onChange={(e) =>
                                            form.setData(
                                                'default_duration',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.default_duration}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="type-status">
                                        {t('Status')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="type-status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target
                                                    .value as MeetingType['status'],
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
                        data={meetingTypes}
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
                                            className="mt-1.5 size-3 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: row.color,
                                            }}
                                        />
                                        <div>
                                            <div className="font-medium">
                                                {row.name}
                                            </div>
                                            <div className="max-w-sm">
                                                <ClampedText
                                                    text={row.description}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                key: 'default_duration',
                                label: 'Duration',
                                render: (row) => (
                                    <span className="flex items-center gap-1.5 whitespace-nowrap">
                                        <Clock className="size-4 text-muted-foreground" />
                                        {row.default_duration} {t('min')}
                                    </span>
                                ),
                            },
                            {
                                key: 'meetings_count',
                                label: 'Meetings',
                                render: (row) => (
                                    <Badge variant="outline">
                                        {row.meetings_count}
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
                        actions={(type) => (
                            <>
                                {can('edit-meeting-types') && (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edit Meeting Type')}
                                            onClick={() => edit(type)}
                                        >
                                            <SquarePen />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t(
                                                type.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                            title={t(
                                                type.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                            onClick={() =>
                                                router.put(
                                                    typeRoutes.toggleStatus(
                                                        type.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {type.status === 'active' ? (
                                                <Lock />
                                            ) : (
                                                <LockOpen />
                                            )}
                                        </Button>
                                    </>
                                )}
                                {can('delete-meeting-types') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete Meeting Type')}
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
                description="This meeting type will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(typeRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

MeetingTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Meetings', href: typeRoutes.index() },
        { title: 'Meeting Types', href: typeRoutes.index() },
    ],
};
