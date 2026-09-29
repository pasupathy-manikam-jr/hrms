import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Eye, TrendingUp, Users } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import announcementRoutes from '@/routes/hr/announcements';
import { AnnouncementFlags, Panel } from './show';
import type { Announcement, Engagement } from './show';

export default function AnnouncementStatistics({
    announcement: a,
    totalEmployees,
    viewCount,
    viewPercentage,
}: { announcement: Announcement } & Engagement) {
    const { t } = useTranslation();
    const { date } = useFormat();

    const facts: [string, string][] = [
        ['Category', t(a.category)],
        [
            'Target Branch',
            a.is_company_wide
                ? t('Company-wide')
                : a.branches.map((b) => b.name).join(', ') || '—',
        ],
        [
            'Target Department',
            a.is_company_wide
                ? t('All Departments')
                : a.departments.map((d) => d.name).join(', ') || '—',
        ],
        ['Start Date', date(a.start_date)],
        ['End Date', a.end_date ? date(a.end_date) : '—'],
    ];

    const tiles = [
        {
            label: 'Total Employees',
            value: totalEmployees,
            icon: Users,
            tone: 'bg-blue-50 text-blue-600',
        },
        {
            label: 'Views',
            value: viewCount,
            icon: Eye,
            tone: 'bg-emerald-50 text-emerald-600',
        },
        {
            label: 'View Rate',
            value: `${viewPercentage}%`,
            icon: TrendingUp,
            tone: 'bg-purple-50 text-purple-600',
        },
    ];

    return (
        <>
            <Head title={t('Announcement Statistics')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Announcement Statistics"
                    description="View read rates and engagement stats for your announcements."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={announcementRoutes.index()}>
                                <ArrowLeft /> {t('Back')}
                            </Link>
                        </Button>
                    }
                />

                <Panel>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <h2 className="text-xl font-semibold">{a.title}</h2>
                        <div className="flex flex-wrap gap-2">
                            <AnnouncementFlags a={a} />
                        </div>
                    </div>
                    <dl className="mt-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        {facts.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs text-muted-foreground">
                                    {t(label)}
                                </dt>
                                <dd className="mt-1 font-medium">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </Panel>

                <div className="grid gap-4 sm:grid-cols-3">
                    {tiles.map(({ label, value, icon: Icon, tone }) => (
                        <div
                            key={label}
                            className="flex items-center gap-4 rounded-xl border bg-card p-6 shadow-sm"
                        >
                            <span
                                className={`flex size-12 items-center justify-center rounded-full ${tone}`}
                            >
                                <Icon className="size-5" />
                            </span>
                            <div>
                                <div className="text-2xl font-bold tabular-nums">
                                    {value}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {t(label)}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

AnnouncementStatistics.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: announcementRoutes.index() },
        { title: 'Announcements', href: announcementRoutes.index() },
        { title: 'Statistics', href: announcementRoutes.index() },
    ],
};
