import { Head, Link } from '@inertiajs/react';
import { Briefcase, Mail, Phone, UserRound } from 'lucide-react';
import {
    DetailPage,
    Fields,
    ProgressBar,
    RecordList,
    Summary,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { UserAvatar } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import onboardingRoutes from '@/routes/hr/recruitment/candidate-onboarding';
import candidateRoutes from '@/routes/hr/recruitment/candidates';
import interviewRoutes from '@/routes/hr/recruitment/interviews';
import offerRoutes from '@/routes/hr/recruitment/offers';

type Named = { id: number; name: string } | null;

type Candidate = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    gender: 'male' | 'female' | 'other' | null;
    date_of_birth: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    zip_code: string | null;
    country: string | null;
    current_company: string | null;
    current_position: string | null;
    experience_years: number;
    current_salary: string | null;
    expected_salary: string | null;
    final_salary: string | null;
    notice_period: string | null;
    skills: string | null;
    education: string | null;
    portfolio_url: string | null;
    linkedin_url: string | null;
    status: string;
    application_date: string | null;
    job: { id: number; title: string; job_code: string | null } | null;
    source: Named;
};

type Interview = {
    id: number;
    scheduled_date: string;
    scheduled_time: string;
    duration: number;
    status: string;
    round: Named;
    interview_type: Named;
    interviewers: { id: number; name: string; avatar: string | null }[];
};

type Assessment = {
    id: number;
    assessment_name: string;
    assessment_date: string;
    score: string | null;
    max_score: string;
    pass_fail_status: string;
    comments: string | null;
    conductor: Named;
};

type Offer = {
    id: number;
    offer_date: string;
    salary: string;
    start_date: string;
    expiration_date: string;
    status: string;
};

type Onboarding = {
    id: number;
    start_date: string;
    status: string;
    progress: number;
    checklist: Named;
    tasks: { id: number }[];
} | null;

function ExternalLink({ href }: { href: string | null }) {
    return href ? (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className="text-primary hover:underline"
        >
            {href}
        </a>
    ) : null;
}

export default function CandidateShow({
    candidate: c,
    interviews,
    assessments,
    offers,
    onboarding,
}: {
    candidate: Candidate;
    interviews: Interview[];
    assessments: Assessment[];
    offers: Offer[];
    onboarding: Onboarding;
}) {
    const { t } = useTranslation();
    const { date, time, money } = useFormat();
    const can = useCan();
    const name = `${c.first_name} ${c.last_name}`;
    const amount = (value: string | null) =>
        value === null ? null : money(Number(value));

    return (
        <>
            <Head title={name} />
            <DetailPage
                title={name}
                description="View the candidate's profile and application progress."
                back={candidateRoutes.index()}
                summary={
                    <Summary
                        media={
                            <UserAvatar
                                name={name}
                                gender={c.gender}
                                className="size-32"
                            />
                        }
                        title={name}
                        subtitle={c.current_position}
                        status={c.status}
                        facts={[
                            [Mail, c.email],
                            [Phone, c.phone],
                            [Briefcase, c.job?.title],
                            [
                                UserRound,
                                t(':years years experience', {
                                    years: c.experience_years,
                                }),
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Profile',
                        heading: 'Personal Information',
                        content: (
                            <Fields
                                items={[
                                    ['First Name', c.first_name],
                                    ['Last Name', c.last_name],
                                    ['Email', c.email],
                                    ['Phone', c.phone],
                                    [
                                        'Gender',
                                        c.gender &&
                                            t(
                                                c.gender
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                    c.gender.slice(1),
                                            ),
                                    ],
                                    [
                                        'Date of Birth',
                                        <DateCell
                                            key="dob"
                                            value={c.date_of_birth}
                                        />,
                                    ],
                                    ['Address', c.address],
                                    ['City', c.city],
                                    ['State', c.state],
                                    ['Postcode', c.zip_code],
                                    ['Country', c.country],
                                    ['Education', c.education],
                                ]}
                            />
                        ),
                    },
                    {
                        label: 'Application',
                        heading: 'Application Details',
                        content: (
                            <Fields
                                items={[
                                    [
                                        'Job Posting',
                                        c.job && (
                                            <span className="flex flex-wrap items-center gap-2">
                                                {c.job.title}
                                                {c.job.job_code && (
                                                    <IdBadge>
                                                        {c.job.job_code}
                                                    </IdBadge>
                                                )}
                                            </span>
                                        ),
                                    ],
                                    ['Source', c.source?.name],
                                    [
                                        'Application Date',
                                        <DateCell
                                            key="ad"
                                            value={c.application_date}
                                        />,
                                    ],
                                    [
                                        'Status',
                                        <StatusBadge
                                            key="s"
                                            status={c.status}
                                        />,
                                    ],
                                    ['Current Company', c.current_company],
                                    ['Current Position', c.current_position],
                                    ['Experience (Years)', c.experience_years],
                                    ['Notice Period', c.notice_period],
                                    [
                                        'Current Salary',
                                        amount(c.current_salary),
                                    ],
                                    [
                                        'Expected Salary',
                                        amount(c.expected_salary),
                                    ],
                                    ['Final Salary', amount(c.final_salary)],
                                    ['Skills', c.skills],
                                    [
                                        'Portfolio URL',
                                        <ExternalLink
                                            key="pf"
                                            href={c.portfolio_url}
                                        />,
                                    ],
                                    [
                                        'LinkedIn URL',
                                        <ExternalLink
                                            key="li"
                                            href={c.linkedin_url}
                                        />,
                                    ],
                                ]}
                            />
                        ),
                    },
                    {
                        label: 'Interviews',
                        content: (
                            <RecordList
                                items={interviews}
                                empty="No interviews yet"
                                render={(i) => (
                                    <>
                                        <div>
                                            <Link
                                                href={interviewRoutes.show(
                                                    i.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {i.round?.name ??
                                                    t('Interview')}
                                            </Link>
                                            <div className="text-sm whitespace-nowrap text-muted-foreground">
                                                {date(i.scheduled_date)}{' '}
                                                {time(i.scheduled_time)} ·{' '}
                                                {t(':minutes min', {
                                                    minutes: i.duration,
                                                })}
                                                {i.interview_type &&
                                                    ` · ${i.interview_type.name}`}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <div className="flex -space-x-2">
                                                {i.interviewers.map((u) => (
                                                    <UserAvatar
                                                        key={u.id}
                                                        name={u.name}
                                                        src={u.avatar}
                                                        className="size-8 border-2 border-card"
                                                    />
                                                ))}
                                            </div>
                                            <StatusBadge status={i.status} />
                                        </div>
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Assessments',
                        content: (
                            <RecordList
                                items={assessments}
                                empty="No assessments yet"
                                render={(a) => (
                                    <>
                                        <div>
                                            <div className="font-medium">
                                                {a.assessment_name}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {date(a.assessment_date)}
                                                {a.conductor &&
                                                    ` · ${a.conductor.name}`}
                                                {a.comments &&
                                                    ` · ${a.comments}`}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="font-medium whitespace-nowrap">
                                                {a.score === null
                                                    ? '-'
                                                    : `${Number(a.score)} / ${Number(a.max_score)}`}
                                            </span>
                                            <StatusBadge
                                                status={a.pass_fail_status}
                                            />
                                        </div>
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Offers',
                        content: (
                            <RecordList
                                items={offers}
                                empty="No offers yet"
                                render={(o) => (
                                    <>
                                        <div>
                                            {can('view-offers') ? (
                                                <Link
                                                    href={offerRoutes.show(
                                                        o.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {money(Number(o.salary))}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {money(Number(o.salary))}
                                                </span>
                                            )}
                                            <div className="text-sm whitespace-nowrap text-muted-foreground">
                                                {t('Offered :date', {
                                                    date: date(o.offer_date),
                                                })}{' '}
                                                ·{' '}
                                                {t('Starts :date', {
                                                    date: date(o.start_date),
                                                })}
                                            </div>
                                        </div>
                                        <StatusBadge status={o.status} />
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Onboarding',
                        content: onboarding ? (
                            <div className="grid gap-5">
                                <Fields
                                    items={[
                                        [
                                            'Checklist',
                                            onboarding.checklist?.name,
                                        ],
                                        [
                                            'Start Date',
                                            <DateCell
                                                key="sd"
                                                value={onboarding.start_date}
                                            />,
                                        ],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="s"
                                                status={onboarding.status}
                                            />,
                                        ],
                                        ['Tasks', onboarding.tasks.length],
                                    ]}
                                />
                                <ProgressBar value={onboarding.progress} />
                                {can('manage-candidate-onboarding') && (
                                    <Link
                                        href={onboardingRoutes.show(
                                            onboarding.id,
                                        )}
                                        className="text-sm font-medium text-primary hover:underline"
                                    >
                                        {t('View onboarding checklist')}
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('Onboarding has not started yet')}
                            </p>
                        ),
                    },
                ]}
            />
        </>
    );
}

CandidateShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: candidateRoutes.index() },
        { title: 'Candidates', href: candidateRoutes.index() },
        { title: 'Candidate Details', href: candidateRoutes.index() },
    ],
};
