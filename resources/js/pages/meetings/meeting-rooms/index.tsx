import { Head, router, useForm } from '@inertiajs/react';
import {
    DoorOpen,
    ExternalLink,
    MapPin,
    Monitor,
    Plus,
    SquarePen,
    Trash2,
    Users,
    Wrench,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import roomRoutes from '@/routes/meetings/meeting-rooms';
import type { Paginated, TableFilters } from '@/types';

type MeetingRoom = {
    id: number;
    name: string;
    description: string | null;
    type: 'Physical' | 'Virtual';
    location: string | null;
    capacity: number;
    equipment: string[] | null;
    booking_url: string | null;
    status: 'active' | 'inactive';
    meetings_count: number;
};

const blank = {
    name: '',
    description: '',
    type: 'Physical' as MeetingRoom['type'],
    location: '',
    capacity: '10',
    equipment: '',
    booking_url: '',
    status: 'active' as MeetingRoom['status'],
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function MeetingRooms({
    meetingRooms,
    statusCounts,
    stats,
    filters,
}: {
    meetingRooms: Paginated<MeetingRoom>;
    statusCounts: Record<string, number>;
    stats: { total: number; active: number; physical: number; virtual: number };
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<MeetingRoom | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<MeetingRoom | null>(null);
    const form = useForm(blank);

    // Equipment is typed as a comma-separated list and sent as an array.
    form.transform((data) => ({
        ...data,
        equipment: data.equipment
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean),
    }));

    const openForm = (room: MeetingRoom | null) => {
        setEditing(room);
        form.clearErrors();
        form.setData(
            room
                ? {
                      name: room.name,
                      description: room.description ?? '',
                      type: room.type,
                      location: room.location ?? '',
                      capacity: String(room.capacity),
                      equipment: (room.equipment ?? []).join(', '),
                      booking_url: room.booking_url ?? '',
                      status: room.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? roomRoutes.update(editing.id) : roomRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<MeetingRoom>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (r) => {
                const virtual = r.type === 'Virtual';
                const Icon = virtual ? Monitor : MapPin;

                return (
                    <div className="flex items-center gap-3">
                        <span
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                virtual
                                    ? 'bg-blue-50 text-blue-600 dark:bg-blue-950'
                                    : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950',
                            )}
                        >
                            <Icon className="size-4" />
                        </span>
                        <div>
                            <div className="font-medium">{r.name}</div>
                            <Badge
                                variant="outline"
                                className={
                                    virtual
                                        ? 'border-blue-200 bg-blue-50 text-blue-700'
                                        : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                }
                            >
                                {t(r.type)}
                            </Badge>
                        </div>
                    </div>
                );
            },
        },
        {
            key: 'location',
            label: 'Location',
            render: (r) =>
                r.type === 'Virtual' && r.booking_url ? (
                    <a
                        href={r.booking_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-blue-600 hover:underline"
                    >
                        {t('Join Link')}
                    </a>
                ) : (
                    (r.location ?? '—')
                ),
        },
        {
            key: 'capacity',
            label: 'Capacity',
            sortable: true,
            render: (r) => (
                <span className="flex items-center gap-2">
                    <Users className="size-4 text-muted-foreground" />
                    {r.capacity}
                </span>
            ),
        },
        {
            key: 'equipment',
            label: 'Equipment',
            render: (r) =>
                (r.equipment ?? []).length > 0 ? (
                    <Badge
                        variant="secondary"
                        title={(r.equipment ?? []).join(', ')}
                        className="whitespace-nowrap"
                    >
                        <Wrench className="size-3" />
                        {t(':count items', { count: r.equipment!.length })}
                    </Badge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'meetings_count',
            label: 'Meetings',
            render: (r) => r.meetings_count,
        },
        {
            key: 'status',
            label: 'Status',
            render: (r) => <StatusBadge status={r.status} />,
        },
    ];

    const statCards = [
        [
            'Total Rooms',
            stats.total,
            'All rooms',
            DoorOpen,
            'text-muted-foreground',
        ],
        [
            'Active Rooms',
            stats.active,
            'Currently active',
            DoorOpen,
            'text-emerald-600',
        ],
        [
            'Physical Rooms',
            stats.physical,
            'On-site rooms',
            MapPin,
            'text-emerald-600',
        ],
        [
            'Virtual Rooms',
            stats.virtual,
            'Online rooms',
            Monitor,
            'text-blue-600',
        ],
    ] as const;

    return (
        <>
            <Head title={t('Meeting Rooms')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Meeting Rooms"
                    description="Manage physical and virtual meeting rooms."
                    action={
                        can('create-meeting-rooms') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Meeting Room')}
                            </Button>
                        )
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {statCards.map(([label, value, note, Icon, tone]) => (
                        <div
                            key={label}
                            className="flex items-start justify-between rounded-xl border bg-card p-5 shadow-sm"
                        >
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {t(label)}
                                </div>
                                <div className="text-2xl font-bold">
                                    {value}
                                </div>
                                <div className={cn('text-xs', tone)}>
                                    {t(note)}
                                </div>
                            </div>
                            <Icon className={cn('size-6', tone)} />
                        </div>
                    ))}
                </div>

                <DataTable
                    data={meetingRooms}
                    columns={columns}
                    filters={filters}
                    url={roomRoutes.index()}
                    toolbar={
                        <>
                            <FilterSelect
                                url={roomRoutes.index()}
                                filters={filters}
                                name="type"
                                label="All Types"
                                options={[
                                    { id: 'Physical', name: t('Physical') },
                                    { id: 'Virtual', name: t('Virtual') },
                                ]}
                            />
                        </>
                    }
                    tabs={
                        <StatusTabs
                            url={roomRoutes.index()}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    actions={(room) => (
                        <>
                            {room.booking_url && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Open Booking Link')}
                                    asChild
                                >
                                    <a
                                        href={room.booking_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink />
                                    </a>
                                </Button>
                            )}
                            {can('edit-meeting-rooms') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(room)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-meeting-rooms') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(room)}
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
                title={editing ? 'Edit Meeting Room' : 'Add Meeting Room'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="room-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="room-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="room-type">{t('Type')}</Label>
                        <SelectField
                            id="room-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData(
                                    'type',
                                    e.target.value as MeetingRoom['type'],
                                )
                            }
                        >
                            <option value="Physical">{t('Physical')}</option>
                            <option value="Virtual">{t('Virtual')}</option>
                        </SelectField>
                        <InputError message={form.errors.type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="room-location">{t('Location')}</Label>
                        <Input
                            id="room-location"
                            value={form.data.location}
                            onChange={(e) =>
                                form.setData('location', e.target.value)
                            }
                        />
                        <InputError message={form.errors.location} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="room-capacity">
                            {t('Capacity')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="room-capacity"
                            type="number"
                            min={1}
                            required
                            value={form.data.capacity}
                            onChange={(e) =>
                                form.setData('capacity', e.target.value)
                            }
                        />
                        <InputError message={form.errors.capacity} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="room-equipment">
                            {t('Equipment (comma separated)')}
                        </Label>
                        <Input
                            id="room-equipment"
                            value={form.data.equipment}
                            onChange={(e) =>
                                form.setData('equipment', e.target.value)
                            }
                        />
                        <InputError message={form.errors.equipment} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="room-booking-url">
                            {t('Booking URL')}
                        </Label>
                        <Input
                            id="room-booking-url"
                            type="url"
                            value={form.data.booking_url}
                            onChange={(e) =>
                                form.setData('booking_url', e.target.value)
                            }
                        />
                        <InputError message={form.errors.booking_url} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="room-status">{t('Status')}</Label>
                        <SelectField
                            id="room-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as MeetingRoom['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="room-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="room-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This meeting room will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(roomRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

MeetingRooms.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Meetings', href: roomRoutes.index() },
        { title: 'Meeting Rooms', href: roomRoutes.index() },
    ],
};
