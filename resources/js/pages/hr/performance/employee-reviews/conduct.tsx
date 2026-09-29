import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Star } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import reviewRoutes from '@/routes/hr/performance/employee-reviews';

type Indicator = {
    id: number;
    name: string;
    description: string | null;
    measurement_unit: string | null;
    target_value: string | null;
    category: { id: number; name: string } | null;
};

type Review = {
    id: number;
    review_date: string;
    comments: string | null;
    employee: { id: number; user: { name: string } };
    review_cycle: { id: number; name: string } | null;
    ratings: {
        performance_indicator_id: number;
        rating: number;
        comments: string | null;
    }[];
};

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function ConductReview({
    review,
    indicators,
}: {
    review: Review;
    indicators: Indicator[];
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const saved = new Map(
        review.ratings.map((r) => [r.performance_indicator_id, r]),
    );
    // Every active indicator starts at "Average" (3), or at the rating saved so far.
    const form = useForm({
        comments: review.comments ?? '',
        ratings: indicators.map((indicator) => ({
            performance_indicator_id: indicator.id,
            rating: Number(saved.get(indicator.id)?.rating ?? 3),
            comments: saved.get(indicator.id)?.comments ?? '',
        })),
    });

    const setRating = (
        index: number,
        changes: Partial<(typeof form.data.ratings)[number]>,
    ) =>
        form.setData(
            'ratings',
            form.data.ratings.map((rating, i) =>
                i === index ? { ...rating, ...changes } : rating,
            ),
        );

    // Indicators grouped by category, keeping each one's index into form.data.ratings.
    const groups = new Map<string, { indicator: Indicator; index: number }[]>();
    indicators.forEach((indicator, index) => {
        const category = indicator.category?.name ?? t('Other');
        groups.set(category, [
            ...(groups.get(category) ?? []),
            { indicator, index },
        ]);
    });

    const ratingError = Object.entries(form.errors).find(([key]) =>
        key.startsWith('ratings'),
    )?.[1];

    return (
        <>
            <Head title={t('Conduct Review')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Conduct Performance Review"
                    description="Fill in and submit the performance review for this employee."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={reviewRoutes.show(review.id)}>
                                <ArrowLeft /> {t('Back')}
                            </Link>
                        </Button>
                    }
                />

                <section className="rounded-xl border bg-card p-6 shadow-sm">
                    <h2 className="text-xl font-semibold">
                        {t('Review Information')}
                    </h2>
                    <p className="mb-6 text-sm text-muted-foreground">
                        {t('You are conducting a performance review for:')}
                    </p>
                    <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        {(
                            [
                                ['Employee', review.employee.user.name],
                                ['Review Date', date(review.review_date)],
                                ['Review Cycle', review.review_cycle?.name],
                            ] as const
                        ).map(([text, value]) => (
                            <div key={text}>
                                <dt className="text-sm text-muted-foreground">
                                    {t(text)}
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {value ?? '—'}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(reviewRoutes.submitConduct(review.id));
                    }}
                    className="rounded-xl border bg-card p-6 shadow-sm"
                >
                    <h2 className="text-xl font-semibold">
                        {t('Performance Ratings')}
                    </h2>
                    <p className="mb-6 text-sm text-muted-foreground">
                        {t('Rate the employee on each performance indicator')}
                    </p>
                    {indicators.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'There are no active performance indicators to rate.',
                            )}
                        </p>
                    )}
                    <div className="grid gap-6">
                        {[...groups.entries()].map(([category, items]) => (
                            <div key={category} className="grid gap-3">
                                <h3 className="text-lg font-semibold">
                                    {category}
                                </h3>
                                {items.map(({ indicator, index }) => {
                                    const rating = form.data.ratings[index];

                                    return (
                                        <div
                                            key={indicator.id}
                                            className="grid gap-4 rounded-xl border p-4"
                                        >
                                            <div className="grid justify-items-start gap-1">
                                                <span className="font-semibold">
                                                    {indicator.name}
                                                </span>
                                                {indicator.description && (
                                                    <span className="text-sm text-muted-foreground">
                                                        {indicator.description}
                                                    </span>
                                                )}
                                                <div className="flex flex-wrap gap-2">
                                                    {indicator.measurement_unit && (
                                                        <Badge variant="outline">
                                                            {t('Measurement')}:{' '}
                                                            {
                                                                indicator.measurement_unit
                                                            }
                                                        </Badge>
                                                    )}
                                                    {indicator.target_value && (
                                                        <Badge variant="outline">
                                                            {t('Target')}:{' '}
                                                            {
                                                                indicator.target_value
                                                            }
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="grid gap-2">
                                                <div className="flex items-center justify-between">
                                                    <Label
                                                        htmlFor={`rating-${indicator.id}`}
                                                    >
                                                        {t('Rating')}
                                                    </Label>
                                                    <span className="flex items-center gap-1 text-xl font-semibold tabular-nums">
                                                        {rating.rating}
                                                        <Star className="size-5 fill-amber-400 text-amber-400" />
                                                    </span>
                                                </div>
                                                <input
                                                    id={`rating-${indicator.id}`}
                                                    type="range"
                                                    min={1}
                                                    max={5}
                                                    step={0.5}
                                                    value={rating.rating}
                                                    onChange={(e) =>
                                                        setRating(index, {
                                                            rating: Number(
                                                                e.target.value,
                                                            ),
                                                        })
                                                    }
                                                    className="w-full accent-primary"
                                                />
                                                <div className="flex justify-between text-xs text-muted-foreground">
                                                    <span>{t('Poor')}</span>
                                                    <span>{t('Average')}</span>
                                                    <span>
                                                        {t('Excellent')}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`rating-comments-${indicator.id}`}
                                                >
                                                    {t('Comments')}
                                                </Label>
                                                <textarea
                                                    id={`rating-comments-${indicator.id}`}
                                                    rows={3}
                                                    placeholder={t(
                                                        'Add specific feedback for this indicator',
                                                    )}
                                                    className={textareaClass}
                                                    value={rating.comments}
                                                    onChange={(e) =>
                                                        setRating(index, {
                                                            comments:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>

                    <div className="mt-6 grid gap-2 border-t pt-6">
                        <Label htmlFor="review-comments">
                            {t('Overall Comments')}
                        </Label>
                        <textarea
                            id="review-comments"
                            rows={4}
                            placeholder={t(
                                'Summarise the employee’s overall performance',
                            )}
                            className={textareaClass}
                            value={form.data.comments}
                            onChange={(e) =>
                                form.setData('comments', e.target.value)
                            }
                        />
                        <InputError message={form.errors.comments} />
                        <InputError message={ratingError} />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="outline" asChild>
                            <Link href={reviewRoutes.show(review.id)}>
                                {t('Cancel')}
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || indicators.length === 0
                            }
                        >
                            {t('Submit Review')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ConductReview.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: reviewRoutes.index() },
        { title: 'Employee Reviews', href: reviewRoutes.index() },
        { title: 'Conduct Review', href: reviewRoutes.index() },
    ],
};
