import { Link, router } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { Bell, ChevronRight, RefreshCw, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import hr from '@/routes/hr';
import meetingRoutes from '@/routes/meetings';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

export type Announcement = {
    id: number;
    title: string;
    category: string;
    start_date: string;
    is_high_priority: boolean;
};

export type Meeting = {
    id: number;
    title: string;
    meeting_date: string;
    start_time: string;
    end_time: string;
    status: string;
};

export function greeting() {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 18
          ? 'Good afternoon'
          : 'Good evening';
}

export function DashboardHeader({ description }: { description: string }) {
    const { t } = useTranslation();
    return (
        <div className="flex items-start justify-between gap-4">
            <div>
                <h1 className="text-2xl font-bold">{t('Dashboard')}</h1>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <Button
                variant="outline"
                onClick={() => router.reload({ only: ['dashboardData'] })}
            >
                <RefreshCw /> {t('Refresh')}
            </Button>
        </div>
    );
}

export function Panel({
    title,
    description,
    action,
    className,
    children,
}: {
    title: string;
    description: string;
    action?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            className={cn(
                'rounded-xl border bg-card shadow-sm dark:border-gray-800',
                className,
            )}
        >
            <header className="flex items-start justify-between gap-4 border-b px-5 py-4 dark:border-gray-800">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {action}
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

export function ViewAll({
    href,
}: {
    href: NonNullable<InertiaLinkProps['href']>;
}) {
    const { t } = useTranslation();
    return (
        <Link
            href={href}
            className="flex shrink-0 items-center gap-1 text-sm font-medium text-primary hover:underline"
        >
            {t('View all')} <ChevronRight className="size-4" />
        </Link>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return (
        <p className="py-10 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

export function AnnouncementsPanel({ items }: { items: Announcement[] }) {
    const { t } = useTranslation();
    const { date } = useFormat();
    return (
        <Panel
            title={t('Recent Announcements')}
            description={t('Latest company announcements')}
            action={<ViewAll href={hr.announcements.index()} />}
        >
            {items.length === 0 && <Empty>{t('No announcements')}</Empty>}
            <ul className="divide-y dark:divide-gray-800">
                {items.map((item) => (
                    <li
                        key={item.id}
                        className="flex items-center gap-3 py-2.5"
                    >
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950">
                            <Bell className="size-4" />
                        </div>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <span className="truncate font-medium">
                                    {item.title}
                                </span>
                                {item.is_high_priority && (
                                    <StatusBadge status={t('Urgent')} />
                                )}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {item.category} • {date(item.start_date)}
                            </div>
                        </div>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}

export function MeetingsPanel({ items }: { items: Meeting[] }) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    return (
        <Panel
            title={t('Upcoming Meetings')}
            description={t('Scheduled meetings from today onwards')}
            action={<ViewAll href={meetingRoutes.meetings.index()} />}
        >
            {items.length === 0 && <Empty>{t('No upcoming meetings')}</Empty>}
            <ul className="divide-y dark:divide-gray-800">
                {items.map((meeting) => (
                    <li
                        key={meeting.id}
                        className="flex items-center gap-3 py-2.5"
                    >
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950">
                            <Users className="size-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="truncate font-medium">
                                {meeting.title}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {date(meeting.meeting_date)} •{' '}
                                {time(meeting.start_time)} -{' '}
                                {time(meeting.end_time)}
                            </div>
                        </div>
                        <StatusBadge status={meeting.status} />
                    </li>
                ))}
            </ul>
        </Panel>
    );
}
