import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarRange,
    Eye,
    Link2,
    List,
    MapPin,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { ViewToggle } from '@/components/view-toggle';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { UserAvatar } from '@/components/user-avatar';
import {
    applyFilters,
    FilterSelect,
    StatusTabs,
} from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import trainingSessionRoutes from '@/routes/hr/training-sessions';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type EmployeeOption = Option & { employee_id: string };

const STATUSES = [
    'scheduled',
    'in_progress',
    'completed',
    'cancelled',
] as const;
const LOCATION_TYPES = ['physical', 'virtual'] as const;

type TrainingSession = {
    id: number;
    training_program_id: number;
    name: string;
    start_date: string;
    end_date: string;
    location_type: (typeof LOCATION_TYPES)[number];
    location: string | null;
    meeting_link: string | null;
    status: (typeof STATUSES)[number];
    notes: string | null;
    program: Option;
    trainers: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Option & { avatar: string | null };
    }[];
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    training_program_id: '' as number | string,
    name: '',
    start_date: '',
    end_date: '',
    location_type: 'physical' as TrainingSession['location_type'],
    location: '',
    meeting_link: '',
    status: 'scheduled' as TrainingSession['status'],
    notes: '',
    trainer_ids: [] as number[],
};

const label = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function TrainingSessions({
    trainingSessions,
    statusCounts,
    trainingPrograms,
    employees,
    filters,
}: {
    trainingSessions: Paginated<TrainingSession>;
    statusCounts: Record<string, number>;
    trainingPrograms: Option[];
    employees: EmployeeOption[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { time } = useFormat();
    const can = useCan();
    const url = trainingSessionRoutes.index();
    const [editing, setEditing] = useState<TrainingSession | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<TrainingSession | null>(null);
    const form = useForm(blank);

    const openForm = (session: TrainingSession | null) => {
        setEditing(session);
        form.clearErrors();
        form.setData(
            session
                ? {
                      training_program_id: session.training_program_id,
                      name: session.name,
                      start_date: session.start_date,
                      end_date: session.end_date,
                      location_type: session.location_type,
                      location: session.location ?? '',
                      meeting_link: session.meeting_link ?? '',
                      status: session.status,
                      notes: session.notes ?? '',
                      trainer_ids: session.trainers.map((tr) => tr.id),
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const toggleTrainer = (id: number, checked: boolean) =>
        form.setData(
            'trainer_ids',
            checked
                ? [...form.data.trainer_ids, id]
                : form.data.trainer_ids.filter((tr) => tr !== id),
        );

    const columns: Column<TrainingSession>[] = [
        {
            key: 'name',
            label: 'Program',
            sortable: true,
            render: (s) => (
                <div>
                    <div className="font-medium">{s.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {s.program.name}
                    </div>
                </div>
            ),
        },
        {
            key: 'start_date',
            label: 'Start Date',
            sortable: true,
            render: (s) => (
                <div>
                    <DateCell value={s.start_date.slice(0, 10)} />
                    <div className="ps-6 text-xs text-muted-foreground">
                        {time(s.start_date.slice(11, 16))}
                    </div>
                </div>
            ),
        },
        {
            key: 'end_date',
            label: 'End Date',
            sortable: true,
            render: (s) => (
                <div>
                    <DateCell value={s.end_date.slice(0, 10)} />
                    <div className="ps-6 text-xs text-muted-foreground">
                        {time(s.end_date.slice(11, 16))}
                    </div>
                </div>
            ),
        },
        {
            key: 'location',
            label: 'Location',
            render: (s) => (
                <div className="grid justify-items-start gap-1">
                    <span className="flex items-center gap-2">
                        {s.location_type === 'virtual' ? (
                            <Link2 className="size-4 text-muted-foreground" />
                        ) : (
                            <MapPin className="size-4 text-muted-foreground" />
                        )}
                        {s.location || t(label(s.location_type))}
                    </span>
                    <StatusBadge status={s.location_type} />
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (s) => <StatusBadge status={s.status} />,
        },
        {
            key: 'trainers',
            label: 'Trainers',
            render: (s) =>
                s.trainers.length === 0 ? (
                    '—'
                ) : (
                    <div className="flex items-center">
                        {s.trainers.slice(0, 4).map((tr) => (
                            <span
                                key={tr.id}
                                title={tr.user.name}
                                className="-ms-2 rounded-full ring-2 ring-card first:ms-0"
                            >
                                <UserAvatar
                                    name={tr.user.name}
                                    src={tr.user.avatar}
                                    gender={tr.gender}
                                    className="size-8"
                                />
                            </span>
                        ))}
                        {s.trainers.length > 4 && (
                            <span className="-ms-2 flex size-8 items-center justify-center rounded-full bg-muted text-xs font-medium ring-2 ring-card">
                                +{s.trainers.length - 4}
                            </span>
                        )}
                    </div>
                ),
        },
    ];

    return (
        <>
            <Head title={t('Training Sessions')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Training Sessions"
                    description="Schedule training sessions and assign trainers."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <ViewToggle
                                current="List"
                                views={[
                                    {
                                        label: 'List',
                                        href: trainingSessionRoutes.index(),
                                        icon: List,
                                    },
                                    {
                                        label: 'Calendar',
                                        href: trainingSessionRoutes.calendar(),
                                        icon: CalendarRange,
                                    },
                                ]}
                            />
                            {can('create-training-sessions') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Add Training Session')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={trainingSessions}
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
                    moreFilters={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="training_program_id"
                            label="All Programs"
                            options={trainingPrograms}
                        />
                    }
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="location_type"
                                label="All Locations"
                                options={LOCATION_TYPES.map((type) => ({
                                    id: type,
                                    name: t(label(type)),
                                }))}
                            />
                            <Input
                                type="date"
                                aria-label={t('From Date')}
                                className="w-auto"
                                value={filters.date_from ?? ''}
                                onChange={(e) =>
                                    applyFilters(url, filters, {
                                        date_from: e.target.value,
                                    })
                                }
                            />
                            <Input
                                type="date"
                                aria-label={t('To Date')}
                                className="w-auto"
                                value={filters.date_to ?? ''}
                                onChange={(e) =>
                                    applyFilters(url, filters, {
                                        date_to: e.target.value,
                                    })
                                }
                            />
                        </>
                    }
                    actions={(session) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link
                                    href={trainingSessionRoutes.show(
                                        session.id,
                                    )}
                                >
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-training-sessions') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(session)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-training-sessions') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(session)}
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
                title={
                    editing ? 'Edit Training Session' : 'Add Training Session'
                }
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? trainingSessionRoutes.update(editing.id)
                            : trainingSessionRoutes.store(),
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
                        <Label htmlFor="session-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="session-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="session-program">
                            {t('Training Program')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="session-program"
                            required
                            value={form.data.training_program_id}
                            onChange={(e) =>
                                form.setData(
                                    'training_program_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('Select Program')}</option>
                            {trainingPrograms.map((program) => (
                                <option key={program.id} value={program.id}>
                                    {program.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.training_program_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="session-start">
                            {t('Start')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="session-start"
                            type="datetime-local"
                            required
                            value={form.data.start_date}
                            onChange={(e) =>
                                form.setData('start_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.start_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="session-end">
                            {t('End')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="session-end"
                            type="datetime-local"
                            required
                            min={form.data.start_date || undefined}
                            value={form.data.end_date}
                            onChange={(e) =>
                                form.setData('end_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.end_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="session-location-type">
                            {t('Location Type')}
                        </Label>
                        <SelectField
                            id="session-location-type"
                            value={form.data.location_type}
                            onChange={(e) =>
                                form.setData(
                                    'location_type',
                                    e.target
                                        .value as TrainingSession['location_type'],
                                )
                            }
                        >
                            {LOCATION_TYPES.map((type) => (
                                <option key={type} value={type}>
                                    {t(label(type))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.location_type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="session-status">{t('Status')}</Label>
                        <SelectField
                            id="session-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as TrainingSession['status'],
                                )
                            }
                        >
                            {STATUSES.map((status) => (
                                <option key={status} value={status}>
                                    {t(label(status))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="session-location">
                            {t('Location')}
                        </Label>
                        <Input
                            id="session-location"
                            value={form.data.location}
                            onChange={(e) =>
                                form.setData('location', e.target.value)
                            }
                        />
                        <InputError message={form.errors.location} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="session-link">
                            {t('Meeting Link')}
                            {form.data.location_type === 'virtual' && (
                                <span className="text-destructive">*</span>
                            )}
                        </Label>
                        <Input
                            id="session-link"
                            type="url"
                            required={form.data.location_type === 'virtual'}
                            value={form.data.meeting_link}
                            onChange={(e) =>
                                form.setData('meeting_link', e.target.value)
                            }
                        />
                        <InputError message={form.errors.meeting_link} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label>{t('Trainers')}</Label>
                        <div className="grid max-h-40 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                            {employees.map((employee) => (
                                <label
                                    key={employee.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.trainer_ids.includes(
                                            employee.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleTrainer(
                                                employee.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {employee.name}
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.trainer_ids} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="session-notes">{t('Notes')}</Label>
                        <textarea
                            id="session-notes"
                            rows={3}
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This training session will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(trainingSessionRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

TrainingSessions.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Training & Development',
            href: trainingSessionRoutes.index(),
        },
        { title: 'Training Sessions', href: trainingSessionRoutes.index() },
    ],
};
