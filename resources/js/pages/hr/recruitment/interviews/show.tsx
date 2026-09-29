import { Head, Link } from '@inertiajs/react';
import { Briefcase, CalendarDays, Clock, Mail, Star } from 'lucide-react';
import {
    DetailPage,
    Fields,
    RecordList,
    Summary,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { PersonCell, UserAvatar } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import candidateRoutes from '@/routes/hr/recruitment/candidates';
import interviewRoutes from '@/routes/hr/recruitment/interviews';

type Named = { id: number; name: string } | null;
type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};

type Interview = {
    id: number;
    scheduled_date: string;
    scheduled_time: string;
    duration: number;
    location: string | null;
    meeting_link: string | null;
    status: string;
    feedback_submitted: boolean;
    candidate: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        phone: string | null;
        gender: 'male' | 'female' | 'other' | null;
        status: string;
    };
    job: { id: number; title: string; job_code: string | null } | null;
    round: (Named & { sequence_number: number }) | null;
    interview_type: Named;
    interviewers: Person[];
};

type Feedback = {
    id: number;
    technical_rating: number | null;
    communication_rating: number | null;
    cultural_fit_rating: number | null;
    overall_rating: number;
    recommendation: string | null;
    strengths: string | null;
    weaknesses: string | null;
    comments: string | null;
    interviewer: Person | null;
};

const rating = (value: number | null) =>
    value === null ? null : (
        <span className="flex items-center gap-1">
            <Star className="size-4 fill-amber-400 text-amber-400" />
            {value}/5
        </span>
    );

export default function InterviewShow({
    interview: i,
    feedback,
}: {
    interview: Interview;
    feedback: Feedback[];
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const can = useCan();
    const candidate = `${i.candidate.first_name} ${i.candidate.last_name}`;

    return (
        <>
            <Head title={candidate} />
            <DetailPage
                title={candidate}
                description="View interview details, the panel and feedback."
                back={interviewRoutes.index()}
                summary={
                    <Summary
                        media={
                            <UserAvatar
                                name={candidate}
                                gender={i.candidate.gender}
                                className="size-32"
                            />
                        }
                        title={candidate}
                        subtitle={i.round?.name}
                        status={i.status}
                        facts={[
                            [Mail, i.candidate.email],
                            [Briefcase, i.job?.title],
                            [CalendarDays, date(i.scheduled_date)],
                            [
                                Clock,
                                `${time(i.scheduled_time)} · ${t(':minutes min', { minutes: i.duration })}`,
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Interview Details',
                        content: (
                            <Fields
                                items={[
                                    [
                                        'Candidate',
                                        can('view-candidates') ? (
                                            <Link
                                                href={candidateRoutes.show(
                                                    i.candidate.id,
                                                )}
                                                className="text-primary hover:underline"
                                            >
                                                {candidate}
                                            </Link>
                                        ) : (
                                            candidate
                                        ),
                                    ],
                                    [
                                        'Candidate Status',
                                        <StatusBadge
                                            key="cs"
                                            status={i.candidate.status}
                                        />,
                                    ],
                                    [
                                        'Job Posting',
                                        i.job && (
                                            <span className="flex flex-wrap items-center gap-2">
                                                {i.job.title}
                                                {i.job.job_code && (
                                                    <IdBadge>
                                                        {i.job.job_code}
                                                    </IdBadge>
                                                )}
                                            </span>
                                        ),
                                    ],
                                    ['Interview Round', i.round?.name],
                                    ['Interview Type', i.interview_type?.name],
                                    [
                                        'Scheduled Date',
                                        <DateCell
                                            key="d"
                                            value={i.scheduled_date}
                                        />,
                                    ],
                                    ['Scheduled Time', time(i.scheduled_time)],
                                    [
                                        'Duration',
                                        t(':minutes min', {
                                            minutes: i.duration,
                                        }),
                                    ],
                                    ['Location', i.location],
                                    [
                                        'Meeting Link',
                                        i.meeting_link && (
                                            <a
                                                href={i.meeting_link}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="break-all text-primary hover:underline"
                                            >
                                                {i.meeting_link}
                                            </a>
                                        ),
                                    ],
                                    [
                                        'Status',
                                        <StatusBadge
                                            key="s"
                                            status={i.status}
                                        />,
                                    ],
                                    [
                                        'Feedback Submitted',
                                        t(i.feedback_submitted ? 'Yes' : 'No'),
                                    ],
                                ]}
                            />
                        ),
                    },
                    {
                        label: 'Interviewers',
                        content: (
                            <RecordList
                                items={i.interviewers}
                                empty="No interviewers assigned"
                                render={(u) => (
                                    <PersonCell
                                        name={u.name}
                                        detail={u.email}
                                        src={u.avatar}
                                    />
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Feedback',
                        content:
                            feedback.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No feedback yet')}
                                </p>
                            ) : (
                                <div className="grid gap-4">
                                    {feedback.map((f) => (
                                        <div
                                            key={f.id}
                                            className="grid gap-4 rounded-lg border p-4"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                {f.interviewer && (
                                                    <PersonCell
                                                        name={
                                                            f.interviewer.name
                                                        }
                                                        detail={
                                                            f.interviewer.email
                                                        }
                                                        src={
                                                            f.interviewer.avatar
                                                        }
                                                    />
                                                )}
                                                {f.recommendation && (
                                                    <StatusBadge
                                                        status={
                                                            f.recommendation
                                                        }
                                                    />
                                                )}
                                            </div>
                                            <Fields
                                                items={[
                                                    [
                                                        'Technical Rating',
                                                        rating(
                                                            f.technical_rating,
                                                        ),
                                                    ],
                                                    [
                                                        'Communication Rating',
                                                        rating(
                                                            f.communication_rating,
                                                        ),
                                                    ],
                                                    [
                                                        'Cultural Fit Rating',
                                                        rating(
                                                            f.cultural_fit_rating,
                                                        ),
                                                    ],
                                                    [
                                                        'Overall Rating',
                                                        rating(
                                                            f.overall_rating,
                                                        ),
                                                    ],
                                                    ['Strengths', f.strengths],
                                                    [
                                                        'Weaknesses',
                                                        f.weaknesses,
                                                    ],
                                                    ['Comments', f.comments],
                                                ]}
                                            />
                                        </div>
                                    ))}
                                </div>
                            ),
                    },
                ]}
            />
        </>
    );
}

InterviewShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: interviewRoutes.index() },
        { title: 'Interviews', href: interviewRoutes.index() },
        { title: 'Interview Details', href: interviewRoutes.index() },
    ],
};
