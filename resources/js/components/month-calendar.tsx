import { usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatPhpDate, useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { addDays, pad, ymd } from '@/lib/dates';

export type MonthCalendarEvent = {
    id: string | number;
    title: string;
    /** Y-m-d, inclusive. */
    start: string;
    end: string;
    start_time?: string | null;
    end_time?: string | null;
    status?: string;
};

type View = 'month' | 'week' | 'day';

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MAX_PER_DAY = 3;

/** "2026-09" for the cursor's month, to compare against event dates as text. */
export const monthPrefix = (d: Date) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;

/**
 * Month / week / day grid with a legend and a per-day dialog. Every event is passed in up
 * front and navigation only moves `cursor` (no URL change); the page owns the cursor so it
 * can show per-month summaries next to the grid.
 */
export function MonthCalendar<T extends MonthCalendarEvent>({
    events,
    cursor,
    onCursorChange,
    chipClass,
    legend,
    describe,
}: {
    events: T[];
    cursor: Date;
    onCursorChange: (cursor: Date) => void;
    /** Colour classes for an event's chip (and its row in the day dialog). */
    chipClass: (event: T) => string;
    legend?: { label: string; dot: string }[];
    /** Text before the dates in the day dialog, e.g. "Holidays · National". */
    describe?: (event: T) => ReactNode;
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const startDay =
        usePage().props.globalSettings.calendarStartDay === 'monday' ? 1 : 0;

    const [view, setView] = useState<View>('month');
    const [openDay, setOpenDay] = useState<string | null>(null);

    const today = ymd(new Date());
    const startOfWeek = (d: Date) =>
        addDays(d, -((d.getDay() - startDay + 7) % 7));

    // Y-m-d strings compare correctly as text.
    const eventsOn = (day: string) =>
        events
            .filter((e) => e.start <= day && e.end >= day)
            .sort((a, b) =>
                (a.start_time ?? '').localeCompare(b.start_time ?? ''),
            );

    const monthStart = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const daysInMonth = new Date(
        cursor.getFullYear(),
        cursor.getMonth() + 1,
        0,
    ).getDate();
    const leading = (monthStart.getDay() - startDay + 7) % 7;
    const days =
        view === 'day'
            ? [cursor]
            : view === 'week'
              ? Array.from({ length: 7 }, (_, i) =>
                    addDays(startOfWeek(cursor), i),
                )
              : Array.from(
                    { length: Math.ceil((leading + daysInMonth) / 7) * 7 },
                    (_, i) => addDays(monthStart, i - leading),
                );

    const move = (direction: 1 | -1) =>
        onCursorChange(
            view === 'month'
                ? new Date(
                      cursor.getFullYear(),
                      cursor.getMonth() + direction,
                      1,
                  )
                : addDays(cursor, direction * (view === 'week' ? 7 : 1)),
        );

    const title =
        view === 'month'
            ? `${t(formatPhpDate(cursor, 'F'))} ${cursor.getFullYear()}`
            : view === 'week'
              ? `${date(days[0])} – ${date(days[6])}`
              : date(cursor);

    const eventLabel = (e: T) =>
        e.start_time ? `${time(e.start_time)} ${e.title}` : e.title;

    const EventChip = ({ e }: { e: T }) => (
        <button
            type="button"
            title={eventLabel(e)}
            onClick={() =>
                setOpenDay(e.start < ymd(days[0]) ? ymd(days[0]) : e.start)
            }
            className={cn(
                'w-full truncate rounded border px-1 py-0.5 text-start text-xs',
                chipClass(e),
            )}
        >
            {eventLabel(e)}
        </button>
    );

    return (
        <>
            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                {legend && (
                    <div className="flex flex-wrap items-center gap-4 border-b bg-muted/40 px-4 py-2 text-xs">
                        <span className="text-muted-foreground">
                            {t('Legend')}:
                        </span>
                        {legend.map((item) => (
                            <span
                                key={item.label}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'size-2.5 rounded-full',
                                        item.dot,
                                    )}
                                />
                                {t(item.label)}
                            </span>
                        ))}
                    </div>
                )}

                <div className="grid gap-4 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <div className="flex overflow-hidden rounded-md bg-slate-800 text-white">
                                <button
                                    type="button"
                                    onClick={() => move(-1)}
                                    aria-label={t('Previous')}
                                    className="px-3 py-2 hover:bg-slate-700"
                                >
                                    <ChevronLeft className="size-5 rtl:rotate-180" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => move(1)}
                                    aria-label={t('Next')}
                                    className="px-3 py-2 hover:bg-slate-700"
                                >
                                    <ChevronRight className="size-5 rtl:rotate-180" />
                                </button>
                            </div>
                            <button
                                type="button"
                                onClick={() => onCursorChange(new Date())}
                                className="rounded-md bg-slate-500 px-3 py-2 text-white hover:bg-slate-600 disabled:opacity-60"
                            >
                                {t('Today')}
                            </button>
                        </div>
                        <h2 className="text-xl font-semibold md:text-2xl">
                            {title}
                        </h2>
                        <div
                            role="group"
                            className="flex overflow-hidden rounded-md bg-slate-800 text-white"
                        >
                            {(['month', 'week', 'day'] as View[]).map(
                                (option) => (
                                    <button
                                        key={option}
                                        type="button"
                                        aria-pressed={view === option}
                                        onClick={() => setView(option)}
                                        className={cn(
                                            'px-3 py-2 capitalize hover:bg-slate-700',
                                            view === option && 'bg-slate-950',
                                        )}
                                    >
                                        {t(
                                            option.charAt(0).toUpperCase() +
                                                option.slice(1),
                                        )}
                                    </button>
                                ),
                            )}
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-md border">
                        <div
                            className={cn(
                                'grid border-b text-center text-sm font-semibold',
                                view === 'day' ? 'grid-cols-1' : 'grid-cols-7',
                            )}
                        >
                            {(view === 'day' ? [cursor] : days.slice(0, 7)).map(
                                (day) => (
                                    <div
                                        key={ymd(day)}
                                        className="border-e px-1 py-1.5 last:border-e-0"
                                    >
                                        {t(WEEKDAYS[day.getDay()])}
                                        {view !== 'month' &&
                                            ` ${day.getDate()}`}
                                    </div>
                                ),
                            )}
                        </div>
                        <div
                            className={cn(
                                'grid',
                                view === 'day' ? 'grid-cols-1' : 'grid-cols-7',
                            )}
                        >
                            {days.map((day) => {
                                const key = ymd(day);
                                const dayEvents = eventsOn(key);
                                const muted =
                                    view === 'month' &&
                                    day.getMonth() !== cursor.getMonth();
                                const limit =
                                    view === 'month'
                                        ? MAX_PER_DAY
                                        : dayEvents.length;

                                return (
                                    <div
                                        key={key}
                                        className={cn(
                                            'flex min-w-0 flex-col gap-1 border-e border-b p-1 [&:nth-child(7n)]:border-e-0',
                                            view === 'month'
                                                ? 'min-h-28'
                                                : 'min-h-80',
                                            key === today &&
                                                'bg-amber-50 dark:bg-amber-950/30',
                                        )}
                                    >
                                        {view === 'month' && (
                                            <button
                                                type="button"
                                                onClick={() => setOpenDay(key)}
                                                aria-label={date(key)}
                                                className={cn(
                                                    'self-end px-1 text-base hover:underline',
                                                    muted &&
                                                        'text-muted-foreground/60',
                                                )}
                                            >
                                                {day.getDate()}
                                            </button>
                                        )}
                                        {dayEvents.slice(0, limit).map((e) => (
                                            <EventChip key={e.id} e={e} />
                                        ))}
                                        {dayEvents.length > limit && (
                                            <button
                                                type="button"
                                                onClick={() => setOpenDay(key)}
                                                className="text-start text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                +{dayEvents.length - limit}{' '}
                                                {t('more')}
                                            </button>
                                        )}
                                        {view !== 'month' &&
                                            dayEvents.length === 0 && (
                                                <p className="p-2 text-xs text-muted-foreground">
                                                    {t('No events')}
                                                </p>
                                            )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>

            <Dialog
                open={openDay !== null}
                onOpenChange={(open) => !open && setOpenDay(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{openDay && date(openDay)}</DialogTitle>
                    </DialogHeader>
                    {openDay && eventsOn(openDay).length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t('No events')}
                        </p>
                    )}
                    <ul className="grid gap-2">
                        {openDay &&
                            eventsOn(openDay).map((e) => (
                                <li
                                    key={e.id}
                                    className={cn(
                                        'grid gap-1 rounded-md border p-3 text-sm',
                                        chipClass(e),
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {e.title}
                                        </span>
                                        {e.status && (
                                            <StatusBadge status={e.status} />
                                        )}
                                    </div>
                                    <div className="text-xs opacity-80">
                                        {describe && (
                                            <>
                                                {describe(e)}
                                                {' · '}
                                            </>
                                        )}
                                        {date(e.start)}
                                        {e.end !== e.start &&
                                            ` – ${date(e.end)}`}
                                        {e.start_time &&
                                            ` · ${time(e.start_time)}${e.end_time ? ` – ${time(e.end_time)}` : ''}`}
                                    </div>
                                </li>
                            ))}
                    </ul>
                </DialogContent>
            </Dialog>
        </>
    );
}
