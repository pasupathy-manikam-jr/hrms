import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    CalendarDays,
    LayoutDashboard,
    List,
    Megaphone,
    Star,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Panel } from '@/components/dashboard-widgets';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect } from '@/components/table-filters';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ViewToggle } from '@/components/view-toggle';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import announcementRoutes from '@/routes/hr/announcements';
import type { TableFilters } from '@/types';

type Option = { id: number; name: string };

type Announcement = {
    id: number;
    title: string;
    category: string;
    description: string | null;
    content: string;
    start_date: string;
    end_date: string | null;
    is_featured: boolean;
    is_high_priority: boolean;
    is_company_wide: boolean;
    status: string;
    departments: Option[];
    branches: Option[];
};

export default function AnnouncementDashboard({
    allAnnouncements,
    featuredAnnouncements,
    highPriorityAnnouncements,
    upcomingAnnouncements,
    categories,
    departments,
    branches,
    filters,
}: {
    allAnnouncements: Announcement[];
    featuredAnnouncements: Announcement[];
    highPriorityAnnouncements: Announcement[];
    upcomingAnnouncements: Announcement[];
    categories: string[];
    departments: Option[];
    branches: Option[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const [viewing, setViewing] = useState<Announcement | null>(null);
    const url = announcementRoutes.dashboard();

    const stats: [string, number, LucideIcon][] = [
        ['Total Announcements', allAnnouncements.length, Megaphone],
        ['Featured', featuredAnnouncements.length, Star],
        ['High Priority', highPriorityAnnouncements.length, AlertTriangle],
        ['Upcoming', upcomingAnnouncements.length, CalendarClock],
    ];

    const Items = ({
        items,
        empty,
    }: {
        items: Announcement[];
        empty: string;
    }) =>
        items.length === 0 ? (
            <p className="py-10 text-center text-sm text-muted-foreground">
                {t(empty)}
            </p>
        ) : (
            <ul className="divide-y">
                {items.map((a) => (
                    <li key={a.id}>
                        <button
                            type="button"
                            onClick={() => setViewing(a)}
                            className="grid w-full gap-1 py-3 text-start hover:bg-muted/40"
                        >
                            <span className="flex items-center gap-2">
                                <span className="truncate font-medium">
                                    {a.title}
                                </span>
                                {a.is_featured && (
                                    <Star className="size-3.5 shrink-0 fill-amber-400 text-amber-400" />
                                )}
                                {a.is_high_priority && (
                                    <StatusBadge status="Urgent" />
                                )}
                                <span className="ms-auto">
                                    <StatusBadge status={a.status} />
                                </span>
                            </span>
                            {a.description && (
                                <span className="line-clamp-2 text-sm text-muted-foreground">
                                    {a.description}
                                </span>
                            )}
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                <CalendarDays className="size-3" />
                                {date(a.start_date)}
                                {a.end_date && ` – ${date(a.end_date)}`}
                                {' · '}
                                {t(a.category)}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        );

    return (
        <>
            <Head title={t('Announcement Dashboard')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Announcement Dashboard"
                    description="Featured, high-priority and upcoming announcements at a glance."
                    action={
                        <ViewToggle
                            current="Dashboard"
                            views={[
                                {
                                    label: 'List',
                                    href: announcementRoutes.index(),
                                    icon: List,
                                },
                                {
                                    label: 'Dashboard',
                                    href: url,
                                    icon: LayoutDashboard,
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
                            name: t(c),
                        }))}
                    />
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="department_id"
                        label="All Departments"
                        options={departments}
                    />
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="branch_id"
                        label="All Branches"
                        options={branches}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {stats.map(([label, value, Icon]) => (
                        <div
                            key={label}
                            className="flex items-center gap-4 rounded-xl border bg-card p-5 shadow-sm"
                        >
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Icon className="size-5" />
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {t(label)}
                                </div>
                                <div className="text-xl font-bold">{value}</div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title={t('Featured Announcements')}
                        description={t('Pinned by HR for everyone to see')}
                    >
                        <Items
                            items={featuredAnnouncements}
                            empty="No featured announcements"
                        />
                    </Panel>
                    <Panel
                        title={t('High Priority')}
                        description={t('Announcements that need attention')}
                    >
                        <Items
                            items={highPriorityAnnouncements}
                            empty="No high priority announcements"
                        />
                    </Panel>
                    <Panel
                        title={t('Upcoming Announcements')}
                        description={t('Scheduled to start later')}
                    >
                        <Items
                            items={upcomingAnnouncements}
                            empty="No upcoming announcements"
                        />
                    </Panel>
                    <Panel
                        title={t('All Announcements')}
                        description={t('Every announcement you can see')}
                    >
                        <Items
                            items={allAnnouncements}
                            empty="No announcements"
                        />
                    </Panel>
                </div>
            </div>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{viewing?.title}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <div className="grid gap-3 text-sm">
                            <div className="flex flex-wrap items-center gap-2 text-muted-foreground">
                                <StatusBadge status={viewing.status} />
                                {t(viewing.category)} ·{' '}
                                {date(viewing.start_date)}
                                {viewing.end_date &&
                                    ` – ${date(viewing.end_date)}`}
                            </div>
                            <p className="whitespace-pre-line">
                                {viewing.content}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {viewing.is_company_wide
                                    ? t('Company-wide')
                                    : [
                                          ...viewing.branches,
                                          ...viewing.departments,
                                      ]
                                          .map((o) => o.name)
                                          .join(', ')}
                            </p>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

AnnouncementDashboard.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Announcements', href: announcementRoutes.index() },
        { title: 'Dashboard', href: announcementRoutes.dashboard() },
    ],
};
