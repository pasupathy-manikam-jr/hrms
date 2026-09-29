import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Star } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import reviewRoutes from '@/routes/hr/performance/employee-reviews';

type Rating = {
    id: number;
    rating: number;
    comments: string | null;
    indicator: {
        id: number;
        name: string;
        description: string | null;
        measurement_unit: string | null;
        category: { id: number; name: string } | null;
    };
};

type Review = {
    id: number;
    review_date: string;
    overall_rating: number | null;
    comments: string | null;
    status: string;
    employee: { id: number; user: { name: string } };
    reviewer: { id: number; name: string } | null;
    review_cycle: { id: number; name: string } | null;
    ratings: Rating[];
};

/** Big score with a star, as on the demo's review page. */
function Score({ value, size }: { value: number; size: 'lg' | 'md' }) {
    return (
        <span
            className={`flex shrink-0 items-center gap-1.5 font-semibold tabular-nums ${size === 'lg' ? 'text-2xl' : 'text-xl'}`}
        >
            {Number(value).toFixed(1)}
            <Star
                className={`fill-amber-400 text-amber-400 ${size === 'lg' ? 'size-6' : 'size-5'}`}
            />
        </span>
    );
}

function Card({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <section className="rounded-xl border bg-card p-6 shadow-sm">
            <h2 className="text-lg font-semibold">{t(title)}</h2>
            <p className="mb-6 text-sm text-muted-foreground">
                {t(description)}
            </p>
            {children}
        </section>
    );
}

export default function EmployeeReviewShow({ review: r }: { review: Review }) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    // Unrated reviews (scheduled / in progress) show just the review information, like the demo.
    const rated = r.ratings.length > 0 || r.overall_rating !== null;

    // Ratings grouped by indicator category, in the order the server sent them.
    const groups = new Map<string, Rating[]>();
    r.ratings.forEach((rating) => {
        const category = rating.indicator.category?.name ?? t('Other');
        groups.set(category, [...(groups.get(category) ?? []), rating]);
    });

    const facts: [string, ReactNode][] = [
        ['Employee', r.employee.user.name],
        ['Review Date', date(r.review_date)],
        ['Reviewer', r.reviewer?.name ?? '—'],
        ['Status', <StatusBadge key="status" status={r.status} />],
        ['Review Cycle', r.review_cycle?.name ?? '—'],
    ];

    return (
        <>
            <Head title={t('Review Details')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Review Details"
                    description="View the full results and feedback for this performance review."
                    action={
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={reviewRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {r.status !== 'completed' &&
                                can('edit-employee-reviews') && (
                                    <Button asChild>
                                        <Link href={reviewRoutes.conduct(r.id)}>
                                            <ClipboardList />{' '}
                                            {t('Conduct Review')}
                                        </Link>
                                    </Button>
                                )}
                        </div>
                    }
                />

                <Card
                    title="Review Information"
                    description="Details about this performance review"
                >
                    <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        {facts.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs text-muted-foreground">
                                    {t(label)}
                                </dt>
                                <dd className="mt-1 font-medium">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    {rated && (
                        <div className="mt-6 border-t pt-6">
                            <div className="flex items-center justify-between gap-4">
                                <h3 className="text-lg font-semibold">
                                    {t('Overall Rating')}
                                </h3>
                                {r.overall_rating !== null ? (
                                    <Score value={r.overall_rating} size="lg" />
                                ) : (
                                    <span className="text-muted-foreground">
                                        {t('Not rated yet')}
                                    </span>
                                )}
                            </div>
                            {r.comments && (
                                <div className="mt-4">
                                    <div className="text-sm text-muted-foreground">
                                        {t('Comments')}
                                    </div>
                                    <p className="mt-1 whitespace-pre-line">
                                        {r.comments}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                </Card>

                {rated && (
                    <Card
                        title="Performance Ratings"
                        description="Individual ratings for each performance indicator"
                    >
                        {groups.size === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('No indicators have been rated yet.')}
                            </p>
                        )}
                        <div className="grid gap-6">
                            {[...groups.entries()].map(
                                ([category, ratings]) => (
                                    <div key={category} className="grid gap-3">
                                        <h3 className="text-lg font-semibold">
                                            {category}
                                        </h3>
                                        {ratings.map((rating) => (
                                            <div
                                                key={rating.id}
                                                className="rounded-xl border bg-muted/30 p-4"
                                            >
                                                <div className="flex items-start justify-between gap-4">
                                                    <div className="grid justify-items-start gap-1">
                                                        <span className="font-semibold">
                                                            {
                                                                rating.indicator
                                                                    .name
                                                            }
                                                        </span>
                                                        {rating.indicator
                                                            .description && (
                                                            <span className="text-sm text-muted-foreground">
                                                                {
                                                                    rating
                                                                        .indicator
                                                                        .description
                                                                }
                                                            </span>
                                                        )}
                                                        {rating.indicator
                                                            .measurement_unit && (
                                                            <Badge variant="outline">
                                                                {
                                                                    rating
                                                                        .indicator
                                                                        .measurement_unit
                                                                }
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <Score
                                                        value={rating.rating}
                                                        size="md"
                                                    />
                                                </div>
                                                {rating.comments && (
                                                    <div className="mt-3 border-t pt-3">
                                                        <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                            {t('Comments')}
                                                        </div>
                                                        <p className="mt-1 text-sm">
                                                            {rating.comments}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                ),
                            )}
                        </div>
                    </Card>
                )}
            </div>
        </>
    );
}

EmployeeReviewShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: reviewRoutes.index() },
        { title: 'Employee Reviews', href: reviewRoutes.index() },
        { title: 'View Review', href: reviewRoutes.index() },
    ],
};
