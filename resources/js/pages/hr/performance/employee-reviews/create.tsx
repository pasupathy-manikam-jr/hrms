import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import reviewRoutes from '@/routes/hr/performance/employee-reviews';

type Option = { id: number; name: string };

const label = (status: string) =>
    status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function ScheduleReview({
    employees,
    reviewers,
    reviewCycles,
    statuses,
}: {
    employees: (Option & { employee_id: string })[];
    reviewers: Option[];
    reviewCycles: Option[];
    statuses: string[];
}) {
    const { t } = useTranslation();
    const form = useForm({
        employee_id: '',
        reviewer_id: '',
        review_cycle_id: '',
        review_date: '',
        // Scheduling never completes a review; completing happens on the conduct page.
        status: 'scheduled',
    });

    const field = (
        name: keyof typeof form.data,
        text: string,
        input: ReactNode,
        required = true,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`review-${name}`}>
                {t(text)}
                {required && <span className="text-destructive">*</span>}
            </Label>
            {input}
            <InputError message={form.errors[name]} />
        </div>
    );

    const select = (
        name: 'employee_id' | 'reviewer_id' | 'review_cycle_id',
        placeholder: string,
        options: Option[],
    ) => (
        <SelectField
            id={`review-${name}`}
            required
            value={form.data[name]}
            onChange={(e) => form.setData(name, e.target.value)}
        >
            <option value="">{t(placeholder)}</option>
            {options.map((option) => (
                <option key={option.id} value={option.id}>
                    {option.name}
                </option>
            ))}
        </SelectField>
    );

    return (
        <>
            <Head title={t('Schedule Review')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Schedule Employee Review"
                    description="Schedule a new performance review for an employee."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={reviewRoutes.index()}>
                                <ArrowLeft /> {t('Back')}
                            </Link>
                        </Button>
                    }
                />

                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(reviewRoutes.store());
                    }}
                    className="rounded-xl border bg-card p-6 shadow-sm"
                >
                    <h2 className="text-xl font-semibold">
                        {t('Schedule New Review')}
                    </h2>
                    <p className="mb-6 text-sm text-muted-foreground">
                        {t('Create a new performance review for an employee')}
                    </p>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {field(
                            'employee_id',
                            'Employee',
                            select(
                                'employee_id',
                                'Select employee',
                                employees.map((e) => ({
                                    id: e.id,
                                    name: `${e.name} (${e.employee_id})`,
                                })),
                            ),
                        )}
                        {field(
                            'reviewer_id',
                            'Reviewer',
                            select('reviewer_id', 'Select reviewer', reviewers),
                        )}
                        {field(
                            'review_cycle_id',
                            'Review Cycle',
                            select(
                                'review_cycle_id',
                                'Select review cycle',
                                reviewCycles,
                            ),
                        )}
                        {field(
                            'review_date',
                            'Review Date',
                            <Input
                                id="review-review_date"
                                type="date"
                                required
                                value={form.data.review_date}
                                onChange={(e) =>
                                    form.setData('review_date', e.target.value)
                                }
                            />,
                        )}
                        {field(
                            'status',
                            'Status',
                            <SelectField
                                id="review-status"
                                value={form.data.status}
                                onChange={(e) =>
                                    form.setData('status', e.target.value)
                                }
                            >
                                {statuses
                                    .filter((status) => status !== 'completed')
                                    .map((status) => (
                                        <option key={status} value={status}>
                                            {t(label(status))}
                                        </option>
                                    ))}
                            </SelectField>,
                            false,
                        )}
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="outline" asChild>
                            <Link href={reviewRoutes.index()}>
                                {t('Cancel')}
                            </Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('Schedule Review')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ScheduleReview.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: reviewRoutes.index() },
        { title: 'Employee Reviews', href: reviewRoutes.index() },
        { title: 'Schedule Review', href: reviewRoutes.create() },
    ],
};
