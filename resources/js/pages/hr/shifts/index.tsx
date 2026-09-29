import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CircleCheck,
    Clock,
    Eye,
    Lock,
    LockOpen,
    Moon,
    Plus,
    SquarePen,
    Sun,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatCards } from '@/components/stat-cards';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import shiftRoutes from '@/routes/hr/shifts';
import type { Paginated, TableFilters } from '@/types';

type Shift = {
    id: number;
    name: string;
    description: string | null;
    start_time: string;
    end_time: string;
    break_duration: number;
    break_start_time: string | null;
    break_end_time: string | null;
    grace_period: number;
    is_night_shift: boolean;
    status: 'active' | 'inactive';
    created_at: string;
};

const blank = {
    name: '',
    description: '',
    start_time: '',
    end_time: '',
    break_duration: 0 as number | string,
    break_start_time: '',
    break_end_time: '',
    grace_period: 0 as number | string,
    is_night_shift: false,
    status: 'active' as Shift['status'],
};

// The DB returns "09:00:00"; <input type="time"> and the API use "09:00".
const hm = (value: string | null) => (value ?? '').slice(0, 5);

const minutes = (value: string) => {
    const [h, m] = value.split(':').map(Number);

    return h * 60 + m;
};

/** Paid hours: start to end (wrapping past midnight) minus the break. */
const workingHours = (shift: Shift) =>
    (((minutes(shift.end_time) - minutes(shift.start_time) + 1440) % 1440 ||
        1440) -
        shift.break_duration) /
    60;

export default function Shifts({
    shifts,
    typeCounts,
    statusCounts,
    filters,
}: {
    shifts: Paginated<Shift>;
    typeCounts: { night: number; day: number };
    statusCounts: { all: number; active: number; inactive: number };
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { time } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Shift | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [viewing, setViewing] = useState<Shift | null>(null);
    const [deleting, setDeleting] = useState<Shift | null>(null);
    const form = useForm(blank);

    const openForm = (shift: Shift | null) => {
        setEditing(shift);
        form.clearErrors();
        form.setData(
            shift
                ? {
                      name: shift.name,
                      description: shift.description ?? '',
                      start_time: hm(shift.start_time),
                      end_time: hm(shift.end_time),
                      break_duration: shift.break_duration,
                      break_start_time: hm(shift.break_start_time),
                      break_end_time: hm(shift.break_end_time),
                      grace_period: shift.grace_period,
                      is_night_shift: shift.is_night_shift,
                      status: shift.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? shiftRoutes.update(editing.id) : shiftRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const range = (start: string | null, end: string | null) =>
        start && end ? `${time(start)} - ${time(end)}` : '—';

    const textField = (
        key:
            | 'name'
            | 'start_time'
            | 'end_time'
            | 'break_start_time'
            | 'break_end_time',
        label: string,
        type: 'text' | 'time',
        required = false,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`shift-${key}`}>
                {t(label)}
                {required && <span className="text-destructive">*</span>}
            </Label>
            <Input
                id={`shift-${key}`}
                type={type}
                required={required}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
            />
            <InputError message={form.errors[key]} />
        </div>
    );

    const numberField = (
        key: 'break_duration' | 'grace_period',
        label: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`shift-${key}`}>{t(label)}</Label>
            <Input
                id={`shift-${key}`}
                type="number"
                min={0}
                required
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
            />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <>
            <Head title={t('Shifts')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Shifts"
                    description="Manage work shifts and schedules."
                    action={
                        can('create-shifts') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Shift')}
                            </Button>
                        )
                    }
                />

                <StatCards
                    stats={[
                        {
                            label: 'Total Shifts',
                            value: statusCounts.all,
                            note: 'All shifts',
                            icon: Users,
                            tone: 'bg-muted text-muted-foreground',
                        },
                        {
                            label: 'Active Shifts',
                            value: statusCounts.active,
                            note: 'Currently active',
                            icon: CircleCheck,
                            tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950',
                        },
                        {
                            label: 'Night Shifts',
                            value: typeCounts.night,
                            note: 'Night schedule',
                            icon: Moon,
                            tone: 'bg-slate-100 text-slate-600 dark:bg-slate-900',
                        },
                        {
                            label: 'Day Shifts',
                            value: typeCounts.day,
                            note: 'Day schedule',
                            icon: Sun,
                            tone: 'bg-blue-100 text-blue-600 dark:bg-blue-950',
                        },
                    ]}
                />

                <DataTable
                    cardsOnly
                    data={shifts}
                    columns={[]}
                    renderCard={(s, actions) => (
                        <div className="flex h-full flex-col gap-4 rounded-xl border bg-card p-5 shadow-sm">
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                    {s.is_night_shift ? (
                                        <Moon className="size-5" />
                                    ) : (
                                        <Sun className="size-5" />
                                    )}
                                </span>
                                <div className="grid min-w-0 flex-1 justify-items-start gap-1.5">
                                    <div className="text-lg leading-tight font-semibold">
                                        {s.name}
                                    </div>
                                    <StatusBadge
                                        status={
                                            s.is_night_shift
                                                ? 'night_shift'
                                                : 'day_shift'
                                        }
                                    />
                                    <StatusBadge status={s.status} />
                                </div>
                                {actions}
                            </div>
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                {(
                                    [
                                        [
                                            Clock,
                                            range(s.start_time, s.end_time),
                                            'Shift Hours',
                                        ],
                                        [
                                            null,
                                            t(':minutes minutes', {
                                                minutes: s.break_duration,
                                            }),
                                            'Break Duration',
                                        ],
                                        [
                                            CalendarDays,
                                            t(':hours hours', {
                                                hours: workingHours(s).toFixed(
                                                    1,
                                                ),
                                            }),
                                            'Working Time',
                                        ],
                                        [
                                            null,
                                            t(':minutes minutes', {
                                                minutes: s.grace_period,
                                            }),
                                            'Grace Period',
                                        ],
                                    ] as const
                                ).map(([Icon, value, label]) => (
                                    <div
                                        key={label}
                                        className="flex items-start gap-2"
                                    >
                                        {Icon && (
                                            <Icon className="mt-0.5 size-4 text-muted-foreground" />
                                        )}
                                        <div>
                                            <dd className="font-semibold tabular-nums">
                                                {value}
                                            </dd>
                                            <dt className="text-xs text-muted-foreground">
                                                {t(label)}
                                            </dt>
                                        </div>
                                    </div>
                                ))}
                            </dl>
                            {s.description && (
                                <p className="border-t pt-3 text-sm text-muted-foreground">
                                    {s.description}
                                </p>
                            )}
                        </div>
                    )}
                    filters={filters}
                    url={shiftRoutes.index()}
                    tabs={
                        <StatusTabs
                            url={shiftRoutes.index()}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    toolbar={
                        <FilterSelect
                            url={shiftRoutes.index()}
                            filters={filters}
                            name="shift_type"
                            label="All Types"
                            options={[
                                { id: 'day', name: t('Day Shift') },
                                { id: 'night', name: t('Night Shift') },
                            ]}
                        />
                    }
                    actions={(shift) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(shift)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-shifts') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(shift)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('edit-shifts') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        shift.status === 'active'
                                            ? 'Deactivate'
                                            : 'Activate',
                                    )}
                                    title={t(
                                        shift.status === 'active'
                                            ? 'Deactivate'
                                            : 'Activate',
                                    )}
                                    onClick={() =>
                                        router.put(
                                            shiftRoutes.toggleStatus(shift.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {shift.status === 'active' ? (
                                        <Lock />
                                    ) : (
                                        <LockOpen />
                                    )}
                                </Button>
                            )}
                            {can('delete-shifts') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(shift)}
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
                title={editing ? 'Edit Shift' : 'Add Shift'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        {textField('name', 'Name', 'text', true)}
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="shift-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="shift-description"
                            rows={3}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    {textField('start_time', 'Start Time', 'time', true)}
                    {textField('end_time', 'End Time', 'time', true)}
                    {textField('break_start_time', 'Break Start Time', 'time')}
                    {textField('break_end_time', 'Break End Time', 'time')}
                    {numberField('break_duration', 'Break Duration (minutes)')}
                    {numberField('grace_period', 'Grace Period (minutes)')}
                    <div className="grid gap-2">
                        <Label htmlFor="shift-status">{t('Status')}</Label>
                        <SelectField
                            id="shift-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Shift['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="flex items-center gap-3 self-end pb-1.5">
                        <Switch
                            id="shift-night"
                            checked={form.data.is_night_shift}
                            onCheckedChange={(checked) =>
                                form.setData('is_night_shift', checked)
                            }
                        />
                        <Label htmlFor="shift-night">{t('Night Shift')}</Label>
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{viewing?.name}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Description')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.description || '—'}
                                </dd>
                            </div>
                            {(
                                [
                                    [
                                        'Shift Timing',
                                        range(
                                            viewing.start_time,
                                            viewing.end_time,
                                        ),
                                    ],
                                    [
                                        'Break Timing',
                                        range(
                                            viewing.break_start_time,
                                            viewing.break_end_time,
                                        ),
                                    ],
                                    [
                                        'Break Duration',
                                        t(':minutes min', {
                                            minutes: viewing.break_duration,
                                        }),
                                    ],
                                    [
                                        'Grace Period',
                                        t(':minutes min', {
                                            minutes: viewing.grace_period,
                                        }),
                                    ],
                                    [
                                        'Shift Type',
                                        t(
                                            viewing.is_night_shift
                                                ? 'Night Shift'
                                                : 'Day Shift',
                                        ),
                                    ],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium">{value}</dd>
                                </div>
                            ))}
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.status} />
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This shift will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(shiftRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Shifts.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: shiftRoutes.index() },
        { title: 'Shifts', href: shiftRoutes.index() },
    ],
};
