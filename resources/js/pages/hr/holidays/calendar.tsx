import { Head } from '@inertiajs/react';
import { CalendarDays, CalendarRange, List } from 'lucide-react';
import { useState } from 'react';
import { MonthCalendar } from '@/components/month-calendar';
import { ymd } from '@/lib/dates';
import type { MonthCalendarEvent } from '@/components/month-calendar';
import { PageHeader } from '@/components/page-header';
import { FilterSelect } from '@/components/table-filters';
import { ViewToggle } from '@/components/view-toggle';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import holidayRoutes from '@/routes/hr/holidays';
import type { TableFilters } from '@/types';

type HolidayEvent = MonthCalendarEvent & {
    category: string;
    description: string | null;
    is_paid: boolean;
    is_half_day: boolean;
    is_recurring: boolean;
    branches: string[];
};

const COLOURS: Record<string, { dot: string; chip: string }> = {
    national: {
        dot: 'bg-blue-600',
        chip: 'border-blue-600/20 bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    },
    religious: {
        dot: 'bg-violet-600',
        chip: 'border-violet-600/20 bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    },
    'company-specific': {
        dot: 'bg-green-600',
        chip: 'border-green-600/20 bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
    },
    regional: {
        dot: 'bg-orange-600',
        chip: 'border-orange-600/20 bg-orange-50 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
    },
};

const categoryLabel = (category: string) =>
    category.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function HolidayCalendar({
    calendarEvents,
    branches,
    categories,
    filters,
}: {
    calendarEvents: HolidayEvent[];
    branches: { id: number; name: string }[];
    categories: string[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const [cursor, setCursor] = useState(() => new Date());
    const url = holidayRoutes.calendar();
    const today = ymd(new Date());

    const upcoming = calendarEvents
        .filter((e) => e.end >= today)
        .sort((a, b) => a.start.localeCompare(b.start))
        .slice(0, 6);

    return (
        <>
            <Head title={t('Holiday Calendar')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Holiday Calendar"
                    description="Company holidays by month, including recurring ones."
                    action={
                        <ViewToggle
                            current="Calendar"
                            views={[
                                {
                                    label: 'List',
                                    href: holidayRoutes.index(),
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
                        name="category"
                        label="All Categories"
                        options={categories.map((c) => ({
                            id: c,
                            name: t(categoryLabel(c)),
                        }))}
                    />
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="branch_id"
                        label="All Branches"
                        options={branches}
                    />
                </div>

                <div className="grid items-start gap-6 xl:grid-cols-[1fr_16rem]">
                    <MonthCalendar
                        events={calendarEvents}
                        cursor={cursor}
                        onCursorChange={setCursor}
                        chipClass={(e) => COLOURS[e.category]?.chip ?? ''}
                        legend={categories.map((c) => ({
                            label: categoryLabel(c),
                            dot: COLOURS[c]?.dot ?? 'bg-muted-foreground',
                        }))}
                        describe={(e) =>
                            [
                                t(categoryLabel(e.category)),
                                e.is_half_day && t('Half Day'),
                                e.is_paid ? t('Paid') : t('Unpaid'),
                                e.branches.join(', '),
                            ]
                                .filter(Boolean)
                                .join(' · ')
                        }
                    />

                    <aside className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <h3 className="flex items-center gap-2 border-b px-4 py-3 font-semibold">
                            <CalendarDays className="size-4 text-primary" />
                            {t('Upcoming Holidays')}
                        </h3>
                        <ul className="divide-y">
                            {upcoming.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex items-start gap-3 px-4 py-3"
                                >
                                    <span
                                        className={cn(
                                            'mt-1.5 size-2.5 shrink-0 rounded-full',
                                            COLOURS[e.category]?.dot,
                                        )}
                                    />
                                    <div className="min-w-0">
                                        <div className="text-sm font-medium">
                                            {e.title}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {date(e.start)}
                                            {e.end !== e.start &&
                                                ` – ${date(e.end)}`}
                                        </div>
                                    </div>
                                </li>
                            ))}
                            {upcoming.length === 0 && (
                                <li className="px-4 py-6 text-center text-sm text-muted-foreground">
                                    {t('No upcoming holidays')}
                                </li>
                            )}
                        </ul>
                    </aside>
                </div>
            </div>
        </>
    );
}

HolidayCalendar.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Holidays', href: holidayRoutes.index() },
        { title: 'Calendar', href: holidayRoutes.calendar() },
    ],
};
