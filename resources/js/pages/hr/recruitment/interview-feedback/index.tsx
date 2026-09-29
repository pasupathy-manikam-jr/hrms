import { Head, router, useForm } from '@inertiajs/react';
import { Plus, SquarePen, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import feedbackRoutes from '@/routes/hr/recruitment/interview-feedback';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type CandidateName = { id: number; first_name: string; last_name: string };

type InterviewOption = {
    id: number;
    scheduled_date: string;
    candidate: CandidateName | null;
    round: Option | null;
    interviewers: Option[];
};

type Feedback = {
    id: number;
    interview_id: number;
    interviewer_id: number | null;
    technical_rating: number | null;
    communication_rating: number | null;
    cultural_fit_rating: number | null;
    overall_rating: number;
    recommendation: string;
    strengths: string | null;
    weaknesses: string | null;
    comments: string | null;
    interview: {
        id: number;
        scheduled_date: string;
        candidate: CandidateName | null;
        job: { id: number; title: string } | null;
        round: Option | null;
    } | null;
    interviewer: (Option & { email: string; avatar: string | null }) | null;
    created_at: string;
};

const RECOMMENDATIONS = [
    'Strong Hire',
    'Hire',
    'Maybe',
    'Reject',
    'Strong Reject',
];

const RATINGS = [
    ['technical_rating', 'Technical Rating'],
    ['communication_rating', 'Communication Rating'],
    ['cultural_fit_rating', 'Cultural Fit Rating'],
    ['overall_rating', 'Overall Rating'],
] as const;

const TEXTS = [
    ['strengths', 'Strengths'],
    ['weaknesses', 'Weaknesses'],
    ['comments', 'Comments'],
] as const;

const blank = {
    interview_id: '' as number | '',
    interviewer_id: '' as number | '',
    technical_rating: '' as number | '',
    communication_rating: '' as number | '',
    cultural_fit_rating: '' as number | '',
    overall_rating: '' as number | '',
    recommendation: 'Hire',
    strengths: '',
    weaknesses: '',
    comments: '',
};

const candidateName = (c: CandidateName | null | undefined) =>
    c ? `${c.first_name} ${c.last_name}` : '—';

export default function InterviewFeedbackIndex({
    interviewFeedback,
    interviews,
    interviewers,
    filters,
}: {
    interviewFeedback: Paginated<Feedback>;
    interviews: InterviewOption[];
    interviewers: Option[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    // Interviewers (manage-own) always write feedback as themselves; the server ignores interviewer_id.
    const pickInterviewer = can('manage-any-interview-feedback');
    const [editing, setEditing] = useState<Feedback | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Feedback | null>(null);
    const form = useForm(blank);
    const url = feedbackRoutes.index();

    const panel =
        interviews.find((i) => i.id === form.data.interview_id)?.interviewers ??
        [];

    const openForm = (feedback: Feedback | null) => {
        setEditing(feedback);
        form.clearErrors();
        form.setData(
            feedback
                ? {
                      interview_id: feedback.interview_id,
                      interviewer_id: feedback.interviewer_id ?? '',
                      technical_rating: feedback.technical_rating ?? '',
                      communication_rating: feedback.communication_rating ?? '',
                      cultural_fit_rating: feedback.cultural_fit_rating ?? '',
                      overall_rating: feedback.overall_rating,
                      recommendation: feedback.recommendation,
                      strengths: feedback.strengths ?? '',
                      weaknesses: feedback.weaknesses ?? '',
                      comments: feedback.comments ?? '',
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? feedbackRoutes.update(editing.id)
                : feedbackRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<Feedback>[] = [
        {
            key: 'candidate',
            label: 'Candidate',
            render: (row) => (
                <PersonCell
                    name={candidateName(row.interview?.candidate)}
                    detail={row.interview?.job?.title}
                />
            ),
        },
        {
            key: 'round',
            label: 'Round',
            render: (row) => row.interview?.round?.name ?? '—',
        },
        {
            key: 'interviewer',
            label: 'Interviewer',
            render: (row) =>
                row.interviewer ? (
                    <PersonCell
                        name={row.interviewer.name}
                        detail={row.interviewer.email}
                        src={row.interviewer.avatar}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'overall_rating',
            label: 'Overall Rating',
            sortable: true,
            render: (row) => (
                <span className="flex items-center gap-1 whitespace-nowrap">
                    <Star className="size-4 fill-amber-400 text-amber-400" />
                    {row.overall_rating}/5
                </span>
            ),
        },
        {
            key: 'recommendation',
            label: 'Recommendation',
            render: (row) => <StatusBadge status={row.recommendation} />,
        },
        {
            key: 'created_at',
            label: 'Submitted',
            sortable: true,
            render: (row) => <DateCell value={row.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Interview Feedback')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Interview Feedback"
                    description="Ratings and hiring recommendations from interviewers."
                    action={
                        can('create-interview-feedback') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Feedback')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={interviewFeedback}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="recommendation"
                                label="All Recommendations"
                                options={RECOMMENDATIONS.map((r) => ({
                                    id: r,
                                    name: t(r),
                                }))}
                            />
                            {pickInterviewer && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="interviewer_id"
                                    label="All Interviewers"
                                    options={interviewers}
                                />
                            )}
                        </>
                    }
                    actions={(feedback) => (
                        <>
                            {can('edit-interview-feedback') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(feedback)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-interview-feedback') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(feedback)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Feedback' : 'Add Feedback'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="feedback-interview">
                            {t('Interview')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="feedback-interview"
                            required
                            value={form.data.interview_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    interview_id: e.target.value
                                        ? +e.target.value
                                        : '',
                                    interviewer_id: '',
                                })
                            }
                        >
                            <option value="">{t('Select Interview')}</option>
                            {interviews.map((i) => (
                                <option key={i.id} value={i.id}>
                                    {candidateName(i.candidate)} —{' '}
                                    {i.round?.name} ({date(i.scheduled_date)})
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.interview_id} />
                    </div>
                    {pickInterviewer && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="feedback-interviewer">
                                {t('Interviewer')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="feedback-interviewer"
                                required
                                value={form.data.interviewer_id}
                                onChange={(e) =>
                                    form.setData(
                                        'interviewer_id',
                                        e.target.value ? +e.target.value : '',
                                    )
                                }
                            >
                                <option value="">
                                    {t('Select Interviewer')}
                                </option>
                                {panel.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={form.errors.interviewer_id} />
                        </div>
                    )}
                    {RATINGS.map(([name, label]) => (
                        <div key={name} className="grid gap-2">
                            <Label htmlFor={`feedback-${name}`}>
                                {t(label)}
                                {name === 'overall_rating' && (
                                    <span className="text-destructive">*</span>
                                )}
                            </Label>
                            <SelectField
                                id={`feedback-${name}`}
                                required={name === 'overall_rating'}
                                value={form.data[name]}
                                onChange={(e) =>
                                    form.setData(
                                        name,
                                        e.target.value ? +e.target.value : '',
                                    )
                                }
                            >
                                <option value="">—</option>
                                {[1, 2, 3, 4, 5].map((n) => (
                                    <option key={n} value={n}>
                                        {n}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={form.errors[name]} />
                        </div>
                    ))}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="feedback-recommendation">
                            {t('Recommendation')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="feedback-recommendation"
                            value={form.data.recommendation}
                            onChange={(e) =>
                                form.setData('recommendation', e.target.value)
                            }
                        >
                            {RECOMMENDATIONS.map((r) => (
                                <option key={r} value={r}>
                                    {t(r)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.recommendation} />
                    </div>
                    {TEXTS.map(([name, label]) => (
                        <div key={name} className="grid gap-2 sm:col-span-2">
                            <Label htmlFor={`feedback-${name}`}>
                                {t(label)}
                            </Label>
                            <textarea
                                id={`feedback-${name}`}
                                rows={2}
                                className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                value={form.data[name]}
                                onChange={(e) =>
                                    form.setData(name, e.target.value)
                                }
                            />
                            <InputError message={form.errors[name]} />
                        </div>
                    ))}
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This feedback will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(feedbackRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

InterviewFeedbackIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: feedbackRoutes.index() },
        { title: 'Interview Feedback', href: feedbackRoutes.index() },
    ],
};
