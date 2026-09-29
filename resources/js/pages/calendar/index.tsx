import { Head } from '@inertiajs/react';
import { CalendarDays, Cake, Plane, TreePalm, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { MonthCalendar, monthPrefix } from '@/components/month-calendar';
import { ymd } from '@/lib/dates';
import { PageHeader } from '@/components/page-header';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import calendarRoutes from '@/routes/calendar';

type EventType = 'meeting' | 'holiday' | 'leave' | 'birthday';

type CalendarEvent = {
    id: string;
    title: string;
    type: EventType;
    /** Y-m-d, inclusive. */
    start: string;
    end: string;
    start_time?: string | null;
    end_time?: string | null;
    status?: string;
    category?: string;
};

// Same order and colours as the demo's legend.
const TYPES: Record<
    EventType,
    { label: string; dot: string; chip: string; icon: LucideIcon }
> = {
    meeting: {
        label: 'Meetings',
        dot: 'bg-blue-600',
        chip: 'border-blue-600/20 bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        icon: Users,
    },
    holiday: {
        label: 'Holidays',
        dot: 'bg-green-600',
        chip: 'border-green-600/20 bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
        icon: TreePalm,
    },
    leave: {
        label: 'Leaves',
        dot: 'bg-yellow-600',
        chip: 'border-yellow-600/20 bg-yellow-50 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',
        icon: Plane,
    },
    birthday: {
        label: 'Birthdays',
        dot: 'bg-pink-600',
        chip: 'border-pink-600/20 bg-pink-50 text-pink-700 dark:bg-pink-950 dark:text-pink-300',
        icon: Cake,
    },
};

export default function Calendar({ events }: { events: CalendarEvent[] }) {
    const { t } = useTranslation();
    const { date } = useFormat();

    // Everything is loaded up front; navigating only moves this cursor (no URL change).
    const [cursor, setCursor] = useState(() => new Date());

    const today = ymd(new Date());

    const upcoming = events
        .filter((e) => e.end >= today)
        .sort((a, b) => a.start.localeCompare(b.start))
        .slice(0, 5);

    const month = monthPrefix(cursor);
    const monthCounts = (Object.keys(TYPES) as EventType[]).map((type) => ({
        type,
        count: events.filter(
            (e) =>
                e.type === type &&
                e.start.slice(0, 7) <= month &&
                e.end.slice(0, 7) >= month,
        ).length,
    }));

    return (
        <>
            <Head title={t('Calendar')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Calendar"
                    description="View your scheduled events and activities."
                />

                <div className="grid items-start gap-6 xl:grid-cols-[1fr_16rem]">
                    <MonthCalendar
                        events={events}
                        cursor={cursor}
                        onCursorChange={setCursor}
                        chipClass={(e) => TYPES[e.type].chip}
                        legend={(Object.keys(TYPES) as EventType[]).map(
                            (type) => TYPES[type],
                        )}
                        describe={(e) =>
                            `${t(TYPES[e.type].label)}${e.category ? ` · ${t(e.category)}` : ''}`
                        }
                    />

                    <aside className="grid gap-6">
                        <section className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <h3 className="flex items-center gap-2 border-b px-4 py-3 font-semibold">
                                <CalendarDays className="size-4 text-primary" />
                                {t('Upcoming Events')}
                            </h3>
                            <ul className="divide-y">
                                {upcoming.map((e) => {
                                    const Icon = TYPES[e.type].icon;

                                    return (
                                        <li
                                            key={e.id}
                                            className="flex items-start gap-3 px-4 py-3"
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-10 shrink-0 items-center justify-center rounded-lg border',
                                                    TYPES[e.type].chip,
                                                )}
                                            >
                                                <Icon className="size-4" />
                                            </span>
                                            <div className="min-w-0">
                                                <div className="text-sm font-medium">
                                                    {e.title}
                                                </div>
                                                <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                                    <CalendarDays className="size-3" />
                                                    {date(e.start)}
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                                {upcoming.length === 0 && (
                                    <li className="px-4 py-6 text-center text-sm text-muted-foreground">
                                        {t('No upcoming events')}
                                    </li>
                                )}
                            </ul>
                        </section>
                        <section className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <h3 className="border-b px-4 py-3 font-semibold">
                                {t('This Month')}
                            </h3>
                            <ul className="grid gap-3 p-4 text-sm">
                                {monthCounts.map(({ type, count }) => (
                                    <li
                                        key={type}
                                        className="flex items-center justify-between"
                                    >
                                        <span className="text-muted-foreground">
                                            {t(TYPES[type].label)}
                                        </span>
                                        <span
                                            className={cn(
                                                'font-semibold',
                                                TYPES[type].chip,
                                                'border-0 bg-transparent',
                                            )}
                                        >
                                            {count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

Calendar.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Calendar', href: calendarRoutes.index() },
    ],
};
