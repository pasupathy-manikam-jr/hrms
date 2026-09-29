import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    MapPin,
    Plus,
    RefreshCw,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { UserAvatar } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { addDays, ymd } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import meetingRoutes from '@/routes/meetings/meetings';

const STATUSES = ['Scheduled', 'In Progress', 'Completed', 'Cancelled'];
const RECURRENCES = ['None', 'Daily', 'Weekly', 'Monthly'];

type Option = { id: number; name: string };

type Meeting = {
    id: number;
    title: string;
    description: string | null;
    type_id: number | null;
    room_id: number | null;
    meeting_date: string;
    start_time: string;
    end_time: string;
    duration: number;
    agenda: string | null;
    status: string;
    recurrence: string;
    recurrence_end_date: string | null;
    organizer_id: number | null;
    type: { id: number; name: string; color: string } | null;
    room: { id: number; name: string; type: string } | null;
    organizer: (Option & { email: string; avatar: string | null }) | null;
    attendees: (Option & { avatar: string | null })[];
};

const blank = {
    title: '',
    description: '',
    type_id: '',
    room_id: '',
    meeting_date: '',
    start_time: '',
    end_time: '',
    agenda: '',
    status: 'Scheduled',
    recurrence: 'None',
    recurrence_end_date: '',
    organizer_id: '',
    attendee_ids: [] as number[],
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

const SUMMARY: [string, string][] = [
    ['Scheduled', 'bg-blue-500'],
    ['In Progress', 'bg-amber-500'],
    ['Completed', 'bg-emerald-500'],
    ['Cancelled', 'bg-red-500'],
];

export default function Meetings({
    meetings,
    meetingTypes,
    meetingRooms,
    users,
}: {
    meetings: Meeting[];
    meetingTypes: (Option & { default_duration: number })[];
    meetingRooms: (Option & { type: string })[];
    users: Option[];
}) {
    const { t } = useTranslation();
    const { time, date } = useFormat();
    const can = useCan();
    const { auth } = usePage().props;
    const [editing, setEditing] = useState<Meeting | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Meeting | null>(null);
    const [changing, setChanging] = useState<Meeting | null>(null);
    const statusForm = useForm({ status: '' });
    const today = ymd(new Date());
    const [selected, setSelected] = useState(today);
    const [cursor, setCursor] = useState(() => {
        const now = new Date();

        return new Date(now.getFullYear(), now.getMonth(), 1);
    });
    const form = useForm(blank);
    const startDay =
        usePage().props.globalSettings.calendarStartDay === 'monday' ? 1 : 0;

    // Meeting counts per day, for the calendar dots.
    const perDay = meetings.reduce<Record<string, number>>((days, m) => {
        const day = m.meeting_date.slice(0, 10);
        days[day] = (days[day] ?? 0) + 1;

        return days;
    }, {});
    const dayMeetings = meetings.filter(
        (m) => m.meeting_date.slice(0, 10) === selected,
    );

    const gridStart = addDays(cursor, -((cursor.getDay() - startDay + 7) % 7));
    const daysInMonth = new Date(
        cursor.getFullYear(),
        cursor.getMonth() + 1,
        0,
    ).getDate();
    const cells = Array.from(
        {
            length:
                Math.ceil(
                    (((cursor.getDay() - startDay + 7) % 7) + daysInMonth) / 7,
                ) * 7,
        },
        (_, i) => addDays(gridStart, i),
    );

    const minutes = (m: Meeting) => {
        const [sh, sm] = m.start_time.split(':').map(Number);
        const [eh, em] = m.end_time.split(':').map(Number);

        return m.duration || eh * 60 + em - (sh * 60 + sm);
    };

    const openForm = (meeting: Meeting | null) => {
        setEditing(meeting);
        form.clearErrors();
        form.setData(
            meeting
                ? {
                      title: meeting.title,
                      description: meeting.description ?? '',
                      type_id: String(meeting.type_id ?? ''),
                      room_id: String(meeting.room_id ?? ''),
                      meeting_date: meeting.meeting_date,
                      start_time: meeting.start_time.slice(0, 5),
                      end_time: meeting.end_time.slice(0, 5),
                      agenda: meeting.agenda ?? '',
                      status: meeting.status,
                      recurrence: meeting.recurrence,
                      recurrence_end_date: meeting.recurrence_end_date ?? '',
                      organizer_id: String(meeting.organizer_id ?? ''),
                      attendee_ids: meeting.attendees.map((a) => a.id),
                  }
                : { ...blank, organizer_id: String(auth.user.id) },
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? meetingRoutes.update(editing.id) : meetingRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const toggleAttendee = (id: number, checked: boolean) =>
        form.setData(
            'attendee_ids',
            checked
                ? [...form.data.attendee_ids, id]
                : form.data.attendee_ids.filter((a) => a !== id),
        );

    return (
        <>
            <Head title={t('Meetings')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Meetings"
                    description="Schedule and manage meetings across teams."
                    action={
                        can('create-meetings') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Schedule Meeting')}
                            </Button>
                        )
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <section className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <CalendarDays className="size-5 text-muted-foreground" />
                                {date(selected)}
                                {selected === today && ` (${t('Today')})`}
                            </h2>
                            <Badge
                                variant="outline"
                                className="border-emerald-200 bg-emerald-50 text-emerald-700"
                            >
                                {t(':count Meetings', {
                                    count: dayMeetings.length,
                                })}
                            </Badge>
                        </div>
                        {dayMeetings.length === 0 && (
                            <p className="py-16 text-center text-sm text-muted-foreground">
                                {t('No meetings on this day')}
                            </p>
                        )}
                        <ul className="divide-y">
                            {dayMeetings.map((m) => (
                                <li
                                    key={m.id}
                                    className="flex flex-wrap gap-4 px-5 py-4 sm:flex-nowrap"
                                >
                                    <div className="grid w-20 shrink-0 justify-items-end self-start border-e pe-4 text-sm font-medium whitespace-nowrap tabular-nums">
                                        <span>{time(m.start_time)}</span>
                                        <span className="text-muted-foreground">
                                            ↓
                                        </span>
                                        <span>{time(m.end_time)}</span>
                                    </div>
                                    {m.organizer && (
                                        <UserAvatar
                                            name={m.organizer.name}
                                            src={m.organizer.avatar}
                                            className="size-10 shrink-0"
                                        />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-semibold">
                                                {m.title}
                                            </span>
                                            <StatusBadge status={m.status} />
                                        </div>
                                        {m.organizer && (
                                            <div className="text-sm text-muted-foreground">
                                                {m.organizer.name} •{' '}
                                                {m.organizer.email}
                                            </div>
                                        )}
                                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                            {m.room && (
                                                <span className="flex items-center gap-1">
                                                    <MapPin className="size-3.5" />
                                                    {m.room.name}
                                                </span>
                                            )}
                                            {m.type && (
                                                <Badge variant="outline">
                                                    {m.type.name}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    <div className="grid shrink-0 justify-items-end gap-2">
                                        <div className="flex">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('View')}
                                                asChild
                                            >
                                                <Link
                                                    href={meetingRoutes.show(
                                                        m.id,
                                                    )}
                                                >
                                                    <Eye />
                                                </Link>
                                            </Button>
                                            {can('edit-meetings') && (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t('Edit')}
                                                        onClick={() =>
                                                            openForm(m)
                                                        }
                                                    >
                                                        <SquarePen />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t(
                                                            'Change Status',
                                                        )}
                                                        title={t(
                                                            'Change Status',
                                                        )}
                                                        onClick={() => {
                                                            statusForm.setData(
                                                                'status',
                                                                m.status,
                                                            );
                                                            statusForm.clearErrors();
                                                            setChanging(m);
                                                        }}
                                                    >
                                                        <RefreshCw />
                                                    </Button>
                                                </>
                                            )}
                                            {can('delete-meetings') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('Delete')}
                                                    onClick={() =>
                                                        setDeleting(m)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                            {m.attendees.length > 0 && (
                                                <div className="flex items-center -space-x-2">
                                                    {m.attendees
                                                        .slice(0, 4)
                                                        .map((a) => (
                                                            <UserAvatar
                                                                key={a.id}
                                                                name={a.name}
                                                                src={a.avatar}
                                                                className="size-7 ring-2 ring-card"
                                                            />
                                                        ))}
                                                    {m.attendees.length > 4 && (
                                                        <span className="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-medium ring-2 ring-card">
                                                            +
                                                            {m.attendees
                                                                .length - 4}
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                            <span className="flex items-center gap-1 whitespace-nowrap">
                                                <Clock className="size-3.5" />
                                                {minutes(m)}m
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <div className="grid content-start gap-6">
                        <section className="rounded-xl border bg-card p-4 shadow-sm">
                            <div className="mb-3 flex items-center justify-between">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Previous month')}
                                    onClick={() =>
                                        setCursor(
                                            new Date(
                                                cursor.getFullYear(),
                                                cursor.getMonth() - 1,
                                                1,
                                            ),
                                        )
                                    }
                                >
                                    <ChevronLeft className="rtl:rotate-180" />
                                </Button>
                                <span className="font-semibold">
                                    {cursor.toLocaleDateString(undefined, {
                                        month: 'long',
                                        year: 'numeric',
                                    })}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Next month')}
                                    onClick={() =>
                                        setCursor(
                                            new Date(
                                                cursor.getFullYear(),
                                                cursor.getMonth() + 1,
                                                1,
                                            ),
                                        )
                                    }
                                >
                                    <ChevronRight className="rtl:rotate-180" />
                                </Button>
                            </div>
                            <div className="grid grid-cols-7 gap-y-1 text-center text-sm">
                                {Array.from({ length: 7 }, (_, i) => (
                                    <span
                                        key={i}
                                        className="py-1 text-xs font-medium text-muted-foreground"
                                    >
                                        {t(WEEKDAYS[(i + startDay) % 7])}
                                    </span>
                                ))}
                                {cells.map((day) => {
                                    const key = ymd(day);
                                    const inMonth =
                                        day.getMonth() === cursor.getMonth();

                                    return inMonth ? (
                                        <button
                                            key={key}
                                            type="button"
                                            onClick={() => setSelected(key)}
                                            aria-pressed={key === selected}
                                            className={cn(
                                                'mx-auto flex size-9 flex-col items-center justify-center rounded-lg text-sm hover:bg-muted',
                                                key === selected &&
                                                    'bg-primary font-semibold text-primary-foreground hover:bg-primary',
                                                key === today &&
                                                    key !== selected &&
                                                    'font-semibold text-primary',
                                            )}
                                        >
                                            {day.getDate()}
                                            <span
                                                className={cn(
                                                    'size-1 rounded-full',
                                                    perDay[key]
                                                        ? key === selected
                                                            ? 'bg-primary-foreground'
                                                            : 'bg-emerald-500'
                                                        : 'bg-transparent',
                                                )}
                                            />
                                        </button>
                                    ) : (
                                        <span key={key} />
                                    );
                                })}
                            </div>
                        </section>

                        <section className="rounded-xl border bg-card shadow-sm">
                            <div className="flex items-center justify-between border-b px-4 py-3">
                                <h2 className="font-semibold">
                                    {t('Meeting Summary')}
                                </h2>
                                <span className="text-sm text-muted-foreground">
                                    ({date(selected)})
                                </span>
                            </div>
                            <dl className="divide-y">
                                {SUMMARY.map(([status, dot]) => (
                                    <div
                                        key={status}
                                        className="flex items-center justify-between px-4 py-2.5 text-sm"
                                    >
                                        <dt className="flex items-center gap-2">
                                            <span
                                                className={cn(
                                                    'size-2 rounded-full',
                                                    dot,
                                                )}
                                            />
                                            {t(status)}
                                        </dt>
                                        <dd className="font-semibold">
                                            {
                                                dayMeetings.filter(
                                                    (m) => m.status === status,
                                                ).length
                                            }
                                        </dd>
                                    </div>
                                ))}
                                <div className="flex items-center justify-between px-4 py-2.5 text-sm font-semibold">
                                    <dt>{t('Total Meetings')}</dt>
                                    <dd>{dayMeetings.length}</dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                </div>
            </div>

            <FormDialog
                open={changing !== null}
                onOpenChange={(open) => !open && setChanging(null)}
                title="Change Meeting Status"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (changing) {
                        statusForm.submit(
                            meetingRoutes.changeStatus(changing.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setChanging(null),
                            },
                        );
                    }
                }}
                processing={statusForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="meeting-status-change">{t('Status')}</Label>
                    <SelectField
                        id="meeting-status-change"
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData('status', e.target.value)
                        }
                    >
                        {STATUSES.map((status) => (
                            <option key={status} value={status}>
                                {t(status)}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={statusForm.errors.status} />
                </div>
            </FormDialog>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Meeting' : 'Schedule Meeting'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="meeting-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="meeting-title"
                            required
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meeting-type">
                            {t('Meeting Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="meeting-type"
                            required
                            value={form.data.type_id}
                            onChange={(e) =>
                                form.setData('type_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Type')}</option>
                            {meetingTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.type_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meeting-room">
                            {t('Meeting Room')}
                        </Label>
                        <SelectField
                            id="meeting-room"
                            value={form.data.room_id}
                            onChange={(e) =>
                                form.setData('room_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Room')}</option>
                            {meetingRooms.map((room) => (
                                <option key={room.id} value={room.id}>
                                    {room.name} ({t(room.type)})
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.room_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meeting-date">
                            {t('Meeting Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="meeting-date"
                            type="date"
                            required
                            value={form.data.meeting_date}
                            onChange={(e) =>
                                form.setData('meeting_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.meeting_date} />
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="grid gap-2">
                            <Label htmlFor="meeting-start">
                                {t('Start Time')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="meeting-start"
                                type="time"
                                required
                                value={form.data.start_time}
                                onChange={(e) =>
                                    form.setData('start_time', e.target.value)
                                }
                            />
                            <InputError message={form.errors.start_time} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="meeting-end">
                                {t('End Time')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="meeting-end"
                                type="time"
                                required
                                value={form.data.end_time}
                                onChange={(e) =>
                                    form.setData('end_time', e.target.value)
                                }
                            />
                            <InputError message={form.errors.end_time} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meeting-organizer">
                            {t('Organizer')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="meeting-organizer"
                            required
                            value={form.data.organizer_id}
                            onChange={(e) =>
                                form.setData('organizer_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Organizer')}</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.organizer_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meeting-status">{t('Status')}</Label>
                        <SelectField
                            id="meeting-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {STATUSES.map((s) => (
                                <option key={s} value={s}>
                                    {t(s)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meeting-recurrence">
                            {t('Recurrence')}
                        </Label>
                        <SelectField
                            id="meeting-recurrence"
                            value={form.data.recurrence}
                            onChange={(e) =>
                                form.setData('recurrence', e.target.value)
                            }
                        >
                            {RECURRENCES.map((r) => (
                                <option key={r} value={r}>
                                    {t(r)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.recurrence} />
                    </div>
                    {form.data.recurrence !== 'None' && (
                        <div className="grid gap-2">
                            <Label htmlFor="meeting-recurrence-end">
                                {t('Recurrence End Date')}
                            </Label>
                            <Input
                                id="meeting-recurrence-end"
                                type="date"
                                value={form.data.recurrence_end_date}
                                onChange={(e) =>
                                    form.setData(
                                        'recurrence_end_date',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.recurrence_end_date}
                            />
                        </div>
                    )}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label>{t('Attendees')}</Label>
                        <div className="grid max-h-40 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                            {users.map((user) => (
                                <label
                                    key={user.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.attendee_ids.includes(
                                            user.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleAttendee(
                                                user.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {user.name}
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.attendee_ids} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="meeting-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="meeting-description"
                            rows={2}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="meeting-agenda">{t('Agenda')}</Label>
                        <textarea
                            id="meeting-agenda"
                            rows={3}
                            className={textareaClass}
                            value={form.data.agenda}
                            onChange={(e) =>
                                form.setData('agenda', e.target.value)
                            }
                        />
                        <InputError message={form.errors.agenda} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This meeting and its action items will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(meetingRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Meetings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Meetings', href: meetingRoutes.index() },
    ],
};
