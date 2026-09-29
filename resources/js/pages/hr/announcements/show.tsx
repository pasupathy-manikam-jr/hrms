import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChartNoAxesColumnIncreasing, Download } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import announcementRoutes from '@/routes/hr/announcements';

type Option = { id: number; name: string };

export type Announcement = {
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
    file_name: string | null;
    departments: Option[];
    branches: Option[];
};

export type Engagement = {
    totalEmployees: number;
    viewCount: number;
    viewPercentage: number;
};

/** Featured / High Priority badges, as on the demo's list. */
export function AnnouncementFlags({ a }: { a: Announcement }) {
    const { t } = useTranslation();

    return (
        <>
            {a.is_featured && (
                <Badge
                    variant="outline"
                    className="border-purple-200 bg-purple-50 text-purple-700"
                >
                    {t('Featured')}
                </Badge>
            )}
            {a.is_high_priority && (
                <Badge
                    variant="outline"
                    className="border-red-200 bg-red-50 text-red-700"
                >
                    {t('High Priority')}
                </Badge>
            )}
        </>
    );
}

export function Panel({ children }: { children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card p-6 shadow-sm">
            {children}
        </section>
    );
}

export default function AnnouncementShow({
    announcement: a,
    totalEmployees,
    viewCount,
    viewPercentage,
}: { announcement: Announcement } & Engagement) {
    const { t } = useTranslation();
    const { date } = useFormat();

    const audience = a.is_company_wide
        ? t('Company-wide')
        : [...a.branches, ...a.departments].map((o) => o.name).join(', ');

    return (
        <>
            <Head title={a.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Announcement Details"
                    description="View the full details and recipients of this announcement."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={announcementRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={announcementRoutes.dashboard()}>
                                    <ArrowLeft /> {t('Dashboard')}
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={announcementRoutes.statistics(a.id)}
                                >
                                    <ChartNoAxesColumnIncreasing />{' '}
                                    {t('Statistics')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <Panel>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <h2 className="text-xl font-semibold">{a.title}</h2>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">{t(a.category)}</Badge>
                            <AnnouncementFlags a={a} />
                        </div>
                    </div>
                    {a.description && (
                        <p className="mt-2 text-muted-foreground">
                            {a.description}
                        </p>
                    )}
                    <div className="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        {(
                            [
                                ['Start Date', date(a.start_date)],
                                [
                                    'End Date',
                                    a.end_date ? date(a.end_date) : '—',
                                ],
                                ['Audience', audience],
                            ] as const
                        ).map(([label, value]) => (
                            <span key={label}>
                                <span className="font-medium">{t(label)}:</span>{' '}
                                <span className="text-muted-foreground">
                                    {value}
                                </span>
                            </span>
                        ))}
                    </div>
                </Panel>

                <Panel>
                    <p className="text-lg leading-relaxed whitespace-pre-line">
                        {a.content}
                    </p>
                    {a.file_name && (
                        <Button variant="outline" className="mt-6" asChild>
                            <a href={announcementRoutes.document.url(a.id)}>
                                <Download /> {t('Download Attachment')}
                            </a>
                        </Button>
                    )}
                </Panel>

                <Panel>
                    <h2 className="text-xl font-semibold">
                        {t('Engagement Statistics')}
                    </h2>
                    <div className="mt-6 flex justify-between text-sm">
                        <span className="font-medium">{t('Views')}</span>
                        <span className="text-muted-foreground tabular-nums">
                            {viewCount}/{totalEmployees} ({viewPercentage}%)
                        </span>
                    </div>
                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-primary"
                            style={{ width: `${viewPercentage}%` }}
                        />
                    </div>
                    <div className="mt-6 flex justify-center">
                        <Button variant="outline" asChild>
                            <Link href={announcementRoutes.statistics(a.id)}>
                                <ChartNoAxesColumnIncreasing />{' '}
                                {t('View Detailed Statistics')}
                            </Link>
                        </Button>
                    </div>
                </Panel>
            </div>
        </>
    );
}

AnnouncementShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: announcementRoutes.index() },
        { title: 'Announcements', href: announcementRoutes.index() },
        { title: 'Details', href: announcementRoutes.index() },
    ],
};
