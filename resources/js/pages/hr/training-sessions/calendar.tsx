import { Head } from '@inertiajs/react';
import { CalendarDays, CalendarRange, List } from 'lucide-react';
import { useState } from 'react';
import { MonthCalendar } from '@/components/month-calendar';
import { ymd } from '@/lib/dates';
import type { MonthCalendarEvent } from '@/components/month-calendar';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect } from '@/components/table-filters';
import { UserAvatar } from '@/components/user-avatar';
import { ViewToggle } from '@/components/view-toggle';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import trainingSessionRoutes from '@/routes/hr/training-sessions';
import type { TableFilters } from '@/types';

type SessionEvent = MonthCalendarEvent & {
    status: string;
    program: string | null;
    location: string | null;
    trainers: {
        id: number;
        name: string;
        avatar: string | null;
        gender: 'male' | 'female' | 'other' | null;
    }[];
};

const STATUSES: Record<string, { label: string; dot: string; chip: string }> = {
    scheduled: {
        label: 'Scheduled',
        dot: 'bg-blue-600',
        chip: 'border-blue-600/20 bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    },
    in_progress: {
        label: 'In Progress',
        dot: 'bg-amber-500',
        chip: 'border-amber-500/20 bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    },
    completed: {
        label: 'Completed',
        dot: 'bg-green-600',
        chip: 'border-green-600/20 bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
    },
    cancelled: {
        label: 'Cancelled',
        dot: 'bg-red-600',
        chip: 'border-red-600/20 bg-red-50 text-red-700 line-through dark:bg-red-950 dark:text-red-300',
    },
};

export default function TrainingSessionCalendar({
    calendarEvents,
    trainingPrograms,
    filters,
}: {
    calendarEvents: SessionEvent[];
    trainingPrograms: { id: number; name: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const [cursor, setCursor] = useState(() => new Date());
    const url = trainingSessionRoutes.calendar();
    const today = ymd(new Date());

    const upcoming = calendarEvents
        .filter((e) => e.end >= today && e.status !== 'cancelled')
        .sort((a, b) => a.start.localeCompare(b.start))
        .slice(0, 6);

    return (
        <>
            <Head title={t('Training Calendar')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Training Calendar"
                    description="Training sessions by month."
                    action={
                        <ViewToggle
                            current="Calendar"
                            views={[
                                {
                                    label: 'List',
                                    href: trainingSessionRoutes.index(),
                                    icon: List,
                                },
                                {
                                    label: 'Calendar',
                                    href: url,
                                    icon: CalendarRange,
                                },
                            ]}
                        />
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="training_program_id"
                        label="All Programs"
                        options={trainingPrograms}
                    />
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="status"
                        label="All Statuses"
                        options={Object.entries(STATUSES).map(
                            ([id, { label }]) => ({ id, name: t(label) }),
                        )}
                    />
                </div>

                <div className="grid items-start gap-6 xl:grid-cols-[1fr_16rem]">
                    <MonthCalendar
                        events={calendarEvents}
                        cursor={cursor}
                        onCursorChange={setCursor}
                        chipClass={(e) => STATUSES[e.status]?.chip ?? ''}
                        legend={Object.values(STATUSES)}
                        describe={(e) =>
                            [e.program, e.location].filter(Boolean).join(' · ')
                        }
                    />

                    <aside className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <h3 className="flex items-center gap-2 border-b px-4 py-3 font-semibold">
                            <CalendarDays className="size-4 text-primary" />
                            {t('Upcoming Sessions')}
                        </h3>
                        <ul className="divide-y">
                            {upcoming.map((e) => (
                                <li key={e.id} className="grid gap-1 px-4 py-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <span className="text-sm font-medium">
                                            {e.title}
                                        </span>
                                        <StatusBadge status={e.status} />
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {date(e.start)}
                                        {e.start_time &&
                                            ` · ${time(e.start_time)}`}
                                        {e.program && ` · ${e.program}`}
                                    </div>
                                    {e.trainers.length > 0 && (
                                        <div className="flex items-center">
                                            {e.trainers.map((tr) => (
                                                <span
                                                    key={tr.id}
                                                    title={tr.name}
                                                    className="-ms-1.5 rounded-full ring-2 ring-card first:ms-0"
                                                >
                                                    <UserAvatar
                                                        name={tr.name}
                                                        src={tr.avatar}
                                                        gender={tr.gender}
                                                        className="size-6"
                                                    />
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </li>
                            ))}
                            {upcoming.length === 0 && (
                                <li className="px-4 py-6 text-center text-sm text-muted-foreground">
                                    {t('No upcoming sessions')}
                                </li>
                            )}
                        </ul>
                    </aside>
                </div>
            </div>
        </>
    );
}

TrainingSessionCalendar.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Training Sessions', href: trainingSessionRoutes.index() },
        { title: 'Calendar', href: trainingSessionRoutes.calendar() },
    ],
};
