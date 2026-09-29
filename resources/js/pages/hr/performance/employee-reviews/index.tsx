import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ClipboardList,
    Eye,
    Plus,
    RefreshCw,
    Star,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { DateCell } from '@/components/table-cells';
import { StatusBadge } from '@/components/status-badge';
import {
    DateRangeFilter,
    FilterSelect,
    StatusTabs,
} from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import reviewRoutes from '@/routes/hr/performance/employee-reviews';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = Option & { employee_id: string };

type Indicator = {
    id: number;
    category_id: number;
    name: string;
    measurement_unit: string;
    target_value: string | null;
    category: Option;
};

type Rating = {
    id: number;
    performance_indicator_id: number;
    rating: number;
    comments: string | null;
    indicator: Indicator;
};

type Review = {
    id: number;
    employee_id: number;
    reviewer_id: number | null;
    review_cycle_id: number;
    review_date: string;
    completion_date: string | null;
    overall_rating: number | null;
    comments: string | null;
    status: string;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Person;
    };
    reviewer: Person | null;
    review_cycle: Option & { frequency: string };
    ratings: Rating[];
};

const label = (status: string) =>
    status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

function Score({ value }: { value: number | null }) {
    return value === null ? (
        <span className="text-muted-foreground">—</span>
    ) : (
        <span className="inline-flex items-center gap-1 font-medium tabular-nums">
            <Star className="size-4 fill-amber-400 text-amber-400" />
            {value.toFixed(1)}
        </span>
    );
}

export default function EmployeeReviews({
    reviews,
    employees,
    reviewers,
    reviewCycles,
    statuses,
    statusCounts,
    filters,
}: {
    reviews: Paginated<Review>;
    employees: EmployeeOption[];
    reviewers: Option[];
    reviewCycles: Option[];
    statuses: string[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();

    const can = useCan();
    const url = reviewRoutes.index();
    const [deleting, setDeleting] = useState<Review | null>(null);
    const [statusFor, setStatusFor] = useState<Review | null>(null);
    const statusForm = useForm({ status: '' });

    const columns: Column<Review>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (r) => (
                <PersonCell
                    name={r.employee.user.name}
                    detail={r.employee.user.email}
                    src={r.employee.user.avatar}
                    gender={r.employee.gender}
                />
            ),
        },
        {
            key: 'reviewer',
            label: 'Reviewer',
            render: (r) =>
                r.reviewer ? (
                    <PersonCell
                        name={r.reviewer.name}
                        detail={r.reviewer.email}
                        src={r.reviewer.avatar}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'review_cycle',
            label: 'Review Cycle',
            render: (r) => r.review_cycle.name,
        },
        {
            key: 'review_date',
            label: 'Review Date',
            sortable: true,
            render: (r) => <DateCell value={r.review_date} />,
        },
        {
            key: 'overall_rating',
            label: 'Rating',
            sortable: true,
            render: (r) => <Score value={r.overall_rating} />,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (r) => <StatusBadge status={r.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Employee Reviews')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employee Reviews"
                    description="Manage performance reviews for your employees."
                    action={
                        can('create-employee-reviews') && (
                            <Button asChild>
                                <Link href={reviewRoutes.create()}>
                                    <Plus /> {t('Schedule Review')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={reviews}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="review_cycle_id"
                                label="All Review Cycles"
                                options={reviewCycles}
                            />
                        </>
                    }
                    moreFilters={
                        <>
                            {employees.length > 0 && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="employee_id"
                                    label="All Employees"
                                    options={employees}
                                />
                            )}
                            {reviewers.length > 0 && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="reviewer_id"
                                    label="All Reviewers"
                                    options={reviewers}
                                />
                            )}
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(review) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={reviewRoutes.show(review.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {review.status !== 'completed' &&
                                can('edit-employee-reviews') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Conduct Review')}
                                        title={t('Conduct Review')}
                                        asChild
                                    >
                                        <Link
                                            href={reviewRoutes.conduct(
                                                review.id,
                                            )}
                                        >
                                            <ClipboardList />
                                        </Link>
                                    </Button>
                                )}
                            {can('edit-employee-reviews') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Change Status')}
                                    title={t('Change Status')}
                                    onClick={() => {
                                        statusForm.setData(
                                            'status',
                                            review.status,
                                        );
                                        statusForm.clearErrors();
                                        setStatusFor(review);
                                    }}
                                >
                                    <RefreshCw />
                                </Button>
                            )}
                            {review.status !== 'completed' &&
                                can('delete-employee-reviews') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(review)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={statusFor !== null}
                onOpenChange={(open) => !open && setStatusFor(null)}
                title="Change Status"
                description={statusFor?.employee.user.name}
                processing={statusForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (statusFor) {
                        statusForm.submit(
                            reviewRoutes.changeStatus(statusFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setStatusFor(null),
                            },
                        );
                    }
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="review-new-status">{t('Status')}</Label>
                    <SelectField
                        id="review-new-status"
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData('status', e.target.value)
                        }
                    >
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {t(label(status))}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={statusForm.errors.status} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This review and its ratings will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(reviewRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

EmployeeReviews.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: reviewRoutes.index() },
        { title: 'Employee Reviews', href: reviewRoutes.index() },
    ],
};
