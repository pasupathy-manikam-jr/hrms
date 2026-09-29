import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    Eye,
    List,
    MapPin,
    MessageSquare,
    Plus,
    RefreshCw,
    SquareKanban,
    SquarePen,
    Trash2,
    Users,
    Video,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { ViewToggle } from '@/components/view-toggle';
import { StatCards } from '@/components/stat-cards';
import { CandidateCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
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
import { addDays, parseYmd, ymd } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import interviewRoutes from '@/routes/hr/recruitment/interviews';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Status = 'Scheduled' | 'Completed' | 'Cancelled' | 'No-show';
type CandidateOption = {
    id: number;
    first_name: string;
    last_name: string;
    job_id: number;
};

type Interview = {
    id: number;
    candidate_id: number;
    job_id: number;
    round_id: number | null;
    interview_type_id: number | null;
    scheduled_date: string;
    scheduled_time: string;
    duration: number;
    location: string | null;
    meeting_link: string | null;
    status: Status;
    feedback_submitted: boolean;
    candidate: (CandidateOption & { email: string }) | null;
    job: { id: number; title: string } | null;
    round: Option | null;
    interview_type: Option | null;
    interviewers: (Option & { avatar?: string | null })[];
};

type UpcomingInterview = Pick<
    Interview,
    | 'id'
    | 'scheduled_time'
    | 'duration'
    | 'location'
    | 'meeting_link'
    | 'candidate'
    | 'job'
    | 'round'
    | 'interview_type'
>;

const STATUSES: Status[] = ['Scheduled', 'Completed', 'Cancelled', 'No-show'];

const blank = {
    candidate_id: '' as number | '',
    round_id: '' as number | '',
    interview_type_id: '' as number | '',
    scheduled_date: '',
    scheduled_time: '',
    duration: 60 as number | '',
    location: '',
    meeting_link: '',
    interviewers: [] as number[],
    status: 'Scheduled' as Status,
};

const fullName = (c: { first_name: string; last_name: string }) =>
    `${c.first_name} ${c.last_name}`;

function RoundBadge({ name }: { name: string }) {
    return (
        <span className="inline-block rounded-md border border-primary/30 bg-primary/10 px-2 py-0.5 text-xs font-medium whitespace-nowrap text-primary">
            {name}
        </span>
    );
}

function Place({ interview }: { interview: UpcomingInterview }) {
    const { t } = useTranslation();

    return interview.meeting_link ? (
        <span className="flex items-center gap-1 text-xs text-primary">
            <Video className="size-3.5" /> {t('Online')}
        </span>
    ) : (
        <span className="flex items-center gap-1 text-xs text-muted-foreground">
            <MapPin className="size-3.5" /> {interview.location ?? '—'}
        </span>
    );
}

/** Month header plus the Monday-Sunday strip; picking a day narrows the list to it. */
function WeekStrip({
    weekStart,
    weekCounts,
    filters,
}: {
    weekStart: string;
    weekCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const url = interviewRoutes.index();
    const monday = parseYmd(weekStart);
    const days = Array.from({ length: 7 }, (_, i) => addDays(monday, i));
    const today = ymd(new Date());
    const selected = filters.selected_date as string | undefined;
    const goToWeek = (offset: number) =>
        applyFilters(url, filters, {
            week_start: ymd(addDays(monday, offset * 7)),
            selected_date: undefined,
        });
    const month = (d: Date, options: Intl.DateTimeFormatOptions) =>
        d.toLocaleDateString(undefined, options);

    return (
        <div className="grid gap-3">
            <div className="flex items-center justify-between rounded-xl border bg-card p-3 shadow-sm">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label={t('Previous week')}
                    onClick={() => goToWeek(-1)}
                >
                    <ChevronLeft />
                </Button>
                <span className="font-semibold">
                    {month(monday, { month: 'long', year: 'numeric' })}
                </span>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label={t('Next week')}
                    onClick={() => goToWeek(1)}
                >
                    <ChevronRight />
                </Button>
            </div>
            <div className="flex flex-wrap items-stretch overflow-hidden rounded-xl border bg-card shadow-sm">
                <div className="flex items-center gap-3 px-4 py-3">
                    <div className="text-center">
                        <div className="font-semibold whitespace-nowrap">
                            {month(monday, { day: 'numeric', month: 'short' })}{' '}
                            –{' '}
                            {month(days[6], {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                            })}
                        </div>
                        {selected && (
                            <button
                                type="button"
                                className="text-xs text-primary hover:underline"
                                onClick={() =>
                                    applyFilters(url, filters, {
                                        selected_date: undefined,
                                    })
                                }
                            >
                                {t('Show all days')}
                            </button>
                        )}
                    </div>
                </div>
                <div className="grid min-w-0 flex-1 grid-cols-7">
                    {days.map((day) => {
                        const key = ymd(day);
                        const isSelected = key === selected;
                        const count = weekCounts[key] ?? 0;

                        return (
                            <button
                                key={key}
                                type="button"
                                aria-pressed={isSelected}
                                onClick={() =>
                                    applyFilters(url, filters, {
                                        selected_date: isSelected
                                            ? undefined
                                            : key,
                                        week_start: weekStart,
                                        page: undefined,
                                    })
                                }
                                className={cn(
                                    'grid justify-items-center gap-1 border-s px-1 py-3 text-xs font-medium uppercase transition-colors',
                                    isSelected
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {month(day, { weekday: 'short' })}
                                <span
                                    className={cn(
                                        'flex size-8 items-center justify-center rounded-full text-base font-semibold',
                                        isSelected
                                            ? 'bg-white/20'
                                            : key === today
                                              ? 'text-primary'
                                              : 'text-foreground',
                                    )}
                                >
                                    {day.getDate()}
                                </span>
                                <span
                                    className={cn(
                                        'size-1.5 rounded-full',
                                        count > 0
                                            ? isSelected
                                                ? 'bg-white'
                                                : 'bg-primary'
                                            : 'bg-transparent',
                                    )}
                                    title={t(':count interviews', { count })}
                                />
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

/** The demo's right-hand column: the next day's interviews, then counts by status. */
function InterviewSidebar({
    upcoming,
    statusCounts,
    pendingFeedback,
}: {
    upcoming: { date: string | null; interviews: UpcomingInterview[] };
    statusCounts: Record<string, number>;
    pendingFeedback: number;
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const today = ymd(new Date());
    const dayLabel =
        upcoming.date === today
            ? t('Today')
            : upcoming.date === ymd(addDays(new Date(), 1))
              ? t('Tomorrow')
              : null;

    return (
        <div className="grid content-start gap-4">
            <div className="rounded-xl border bg-card shadow-sm">
                <div className="flex items-center gap-2 border-b px-4 py-3 font-semibold">
                    <CalendarDays className="size-4 text-primary" />
                    {upcoming.date
                        ? [dayLabel, date(upcoming.date)]
                              .filter(Boolean)
                              .join(' – ')
                        : t('Upcoming')}
                </div>
                <div className="divide-y">
                    {upcoming.interviews.map((interview) => (
                        <div key={interview.id} className="grid gap-2 p-4">
                            <div className="flex justify-between text-sm">
                                <span className="font-semibold">
                                    {time(interview.scheduled_time)}
                                </span>
                                <span className="text-muted-foreground">
                                    ({interview.duration} {t('min')})
                                </span>
                            </div>
                            {interview.candidate && (
                                <CandidateCell
                                    id={interview.candidate.id}
                                    name={fullName(interview.candidate)}
                                    detail={interview.job?.title}
                                />
                            )}
                            {interview.round && (
                                <div>
                                    <RoundBadge name={interview.round.name} />
                                </div>
                            )}
                            <div className="text-xs text-muted-foreground">
                                {interview.interview_type?.name}
                            </div>
                            <Place interview={interview} />
                            {interview.meeting_link && (
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={interview.meeting_link}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-primary"
                                    >
                                        <Video /> {t('Join Interview')}
                                    </a>
                                </Button>
                            )}
                        </div>
                    ))}
                    {upcoming.interviews.length === 0 && (
                        <p className="p-4 text-sm text-muted-foreground">
                            {t('No upcoming interviews')}
                        </p>
                    )}
                </div>
            </div>
            <div className="rounded-xl border bg-card p-4 shadow-sm">
                <div className="mb-2 font-semibold">{t('Summary')}</div>
                <dl className="grid gap-2 text-sm">
                    {(
                        [
                            ['Total', statusCounts.all],
                            ['Scheduled', statusCounts.Scheduled],
                            ['Completed', statusCounts.Completed],
                            ['Pending Feedback', pendingFeedback],
                            ['Cancelled', statusCounts.Cancelled],
                            ['No-show', statusCounts['No-show']],
                        ] as const
                    ).map(([label, value]) => (
                        <div key={label} className="flex justify-between">
                            <dt className="text-muted-foreground">
                                {t(label)}
                            </dt>
                            <dd className="font-medium">{value ?? 0}</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}

export default function Interviews({
    interviews,
    candidates,
    interviewRounds,
    interviewTypes,
    employees,
    weekStart,
    weekCounts,
    upcoming,
    pendingFeedback,
    statusCounts,
    filters,
}: {
    interviews: Paginated<Interview>;
    candidates: CandidateOption[];
    interviewRounds: (Option & { job_id: number })[];
    interviewTypes: Option[];
    employees: Option[];
    weekStart: string;
    weekCounts: Record<string, number>;
    upcoming: { date: string | null; interviews: UpcomingInterview[] };
    pendingFeedback: number;
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Interview | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Interview | null>(null);
    const [statusFor, setStatusFor] = useState<Interview | null>(null);
    const form = useForm(blank);
    const statusForm = useForm({ status: 'Scheduled' as Status });
    const url = interviewRoutes.index();

    const jobId = candidates.find(
        (c) => c.id === form.data.candidate_id,
    )?.job_id;
    const rounds = interviewRounds.filter((r) => r.job_id === jobId);

    const openForm = (interview: Interview | null) => {
        setEditing(interview);
        form.clearErrors();
        form.setData(
            interview
                ? {
                      candidate_id: interview.candidate_id,
                      round_id: interview.round_id ?? '',
                      interview_type_id: interview.interview_type_id ?? '',
                      scheduled_date: interview.scheduled_date,
                      scheduled_time: interview.scheduled_time.slice(0, 5),
                      duration: interview.duration,
                      location: interview.location ?? '',
                      meeting_link: interview.meeting_link ?? '',
                      interviewers: interview.interviewers.map((i) => i.id),
                      status: interview.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? interviewRoutes.update(editing.id)
                : interviewRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const toggleInterviewer = (id: number, checked: boolean) =>
        form.setData(
            'interviewers',
            checked
                ? [...form.data.interviewers, id]
                : form.data.interviewers.filter((i) => i !== id),
        );

    const columns: Column<Interview>[] = [
        {
            key: 'scheduled_time',
            label: 'Time',
            sortable: true,
            render: (row) => (
                <div className="grid text-xs whitespace-nowrap text-muted-foreground">
                    <span className="text-sm font-semibold text-foreground">
                        {time(row.scheduled_time)}
                    </span>
                    {row.duration} {t('min')}
                    {!filters.selected_date && (
                        <span>{date(row.scheduled_date)}</span>
                    )}
                </div>
            ),
        },
        {
            key: 'candidate',
            label: 'Candidate',
            render: (row) =>
                row.candidate ? (
                    <CandidateCell
                        id={row.candidate.id}
                        name={fullName(row.candidate)}
                        detail={row.job?.title}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'round',
            label: 'Round',
            render: (row) =>
                row.round ? <RoundBadge name={row.round.name} /> : '—',
        },
        {
            key: 'location',
            label: 'Type / Location',
            render: (row) => (
                <div className="grid gap-0.5">
                    <span>{row.interview_type?.name ?? '—'}</span>
                    <Place interview={row} />
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Interviews')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Interviews"
                    description="Schedule and manage candidate interviews."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <ViewToggle
                                current="List"
                                views={[
                                    {
                                        label: 'List',
                                        href: interviewRoutes.index(),
                                        icon: List,
                                    },
                                    {
                                        label: 'Kanban',
                                        href: interviewRoutes.kanban(),
                                        icon: SquareKanban,
                                    },
                                ]}
                            />
                            {can('create-interviews') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Schedule Interview')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <StatCards
                    stats={[
                        {
                            label: 'Total Interviews',
                            value: statusCounts.all ?? 0,
                            note: 'All time',
                            icon: Users,
                            tone: 'bg-muted text-muted-foreground',
                        },
                        {
                            label: 'Scheduled',
                            value: statusCounts.Scheduled ?? 0,
                            note: 'Upcoming interviews',
                            icon: CalendarClock,
                            tone: 'bg-blue-100 text-blue-600 dark:bg-blue-950',
                        },
                        {
                            label: 'Completed',
                            value: statusCounts.Completed ?? 0,
                            note: 'Successfully completed',
                            icon: CircleCheck,
                            tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950',
                        },
                        {
                            label: 'Pending Feedback',
                            value: pendingFeedback,
                            note: 'Awaiting feedback',
                            icon: MessageSquare,
                            tone: 'bg-orange-100 text-orange-600 dark:bg-orange-950',
                        },
                    ]}
                />

                <div className="grid items-start gap-6 xl:grid-cols-[1fr_18rem]">
                    <div className="grid min-w-0 gap-4">
                        <WeekStrip
                            weekStart={weekStart}
                            weekCounts={weekCounts}
                            filters={filters}
                        />
                        <DataTable
                            data={interviews}
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
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="candidate_id"
                                    label="All Candidates"
                                    options={candidates.map((c) => ({
                                        id: c.id,
                                        name: fullName(c),
                                    }))}
                                />
                            }
                            actions={(interview) => (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('View')}
                                        asChild
                                    >
                                        <Link
                                            href={interviewRoutes.show(
                                                interview.id,
                                            )}
                                        >
                                            <Eye />
                                        </Link>
                                    </Button>
                                    {can('edit-interviews') && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Update Status')}
                                                onClick={() => {
                                                    statusForm.setData(
                                                        'status',
                                                        interview.status,
                                                    );
                                                    setStatusFor(interview);
                                                }}
                                            >
                                                <RefreshCw />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Edit')}
                                                onClick={() =>
                                                    openForm(interview)
                                                }
                                            >
                                                <SquarePen />
                                            </Button>
                                        </>
                                    )}
                                    {can('delete-interviews') && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Delete')}
                                            onClick={() =>
                                                setDeleting(interview)
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </>
                            )}
                        />
                    </div>
                    <InterviewSidebar
                        upcoming={upcoming}
                        statusCounts={statusCounts}
                        pendingFeedback={pendingFeedback}
                    />
                </div>
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Interview' : 'Schedule Interview'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="interview-candidate">
                            {t('Candidate')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="interview-candidate"
                            required
                            value={form.data.candidate_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    candidate_id: e.target.value
                                        ? +e.target.value
                                        : '',
                                    round_id: '',
                                })
                            }
                        >
                            <option value="">{t('Select Candidate')}</option>
                            {candidates.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {fullName(c)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.candidate_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="interview-round">
                            {t('Interview Round')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="interview-round"
                            required
                            value={form.data.round_id}
                            onChange={(e) =>
                                form.setData(
                                    'round_id',
                                    e.target.value ? +e.target.value : '',
                                )
                            }
                        >
                            <option value="">{t('Select Round')}</option>
                            {rounds.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.round_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="interview-type">
                            {t('Interview Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="interview-type"
                            required
                            value={form.data.interview_type_id}
                            onChange={(e) =>
                                form.setData(
                                    'interview_type_id',
                                    e.target.value ? +e.target.value : '',
                                )
                            }
                        >
                            <option value="">{t('Select Type')}</option>
                            {interviewTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.interview_type_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="interview-date">
                            {t('Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="interview-date"
                            type="date"
                            required
                            value={form.data.scheduled_date}
                            onChange={(e) =>
                                form.setData('scheduled_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.scheduled_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="interview-time">
                            {t('Time')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="interview-time"
                            type="time"
                            required
                            value={form.data.scheduled_time}
                            onChange={(e) =>
                                form.setData('scheduled_time', e.target.value)
                            }
                        />
                        <InputError message={form.errors.scheduled_time} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="interview-duration">
                            {t('Duration (minutes)')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="interview-duration"
                            type="number"
                            min={5}
                            required
                            value={form.data.duration}
                            onChange={(e) =>
                                form.setData(
                                    'duration',
                                    e.target.value ? +e.target.value : '',
                                )
                            }
                        />
                        <InputError message={form.errors.duration} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="interview-status">{t('Status')}</Label>
                        <SelectField
                            id="interview-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value as Status)
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
                        <Label htmlFor="interview-location">
                            {t('Location')}
                        </Label>
                        <Input
                            id="interview-location"
                            value={form.data.location}
                            onChange={(e) =>
                                form.setData('location', e.target.value)
                            }
                        />
                        <InputError message={form.errors.location} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="interview-link">
                            {t('Meeting Link')}
                        </Label>
                        <Input
                            id="interview-link"
                            type="url"
                            value={form.data.meeting_link}
                            onChange={(e) =>
                                form.setData('meeting_link', e.target.value)
                            }
                        />
                        <InputError message={form.errors.meeting_link} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label>
                            {t('Interviewers')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <div className="grid max-h-40 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                            {employees.map((user) => (
                                <label
                                    key={user.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.interviewers.includes(
                                            user.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleInterviewer(
                                                user.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {user.name}
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.interviewers} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={statusFor !== null}
                onOpenChange={(open) => !open && setStatusFor(null)}
                title="Update Status"
                onSubmit={(e) => {
                    e.preventDefault();

                    if (statusFor) {
                        statusForm.submit(
                            interviewRoutes.updateStatus(statusFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setStatusFor(null),
                            },
                        );
                    }
                }}
                processing={statusForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="interview-new-status">{t('Status')}</Label>
                    <SelectField
                        id="interview-new-status"
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData(
                                'status',
                                e.target.value as Status,
                            )
                        }
                    >
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {t(s)}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={statusForm.errors.status} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This interview will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(interviewRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Interviews.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: interviewRoutes.index() },
        { title: 'Interviews', href: interviewRoutes.index() },
    ],
};
