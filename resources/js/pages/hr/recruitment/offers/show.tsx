import { Head, Link } from '@inertiajs/react';
import { Banknote, Briefcase, CalendarDays, Mail } from 'lucide-react';
import {
    DetailPage,
    Fields,
    Summary,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { PersonCell, UserAvatar } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import candidateRoutes from '@/routes/hr/recruitment/candidates';
import offerRoutes from '@/routes/hr/recruitment/offers';

type Offer = {
    id: number;
    offer_date: string;
    salary: string;
    bonus: string | null;
    benefits: string | null;
    start_date: string;
    expiration_date: string;
    status: string;
    response_date: string | null;
    decline_reason: string | null;
    candidate: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        phone: string | null;
        gender: 'male' | 'female' | 'other' | null;
        status: string;
    };
    job: {
        id: number;
        title: string;
        job_code: string | null;
        department: { id: number; name: string } | null;
    } | null;
    template: { id: number; name: string } | null;
    approver: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
    } | null;
};

export default function OfferShow({
    offer: o,
    letter,
}: {
    offer: Offer;
    /** The template filled in for this offer; null without a template. */
    letter: string | null;
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const can = useCan();
    const candidate = `${o.candidate.first_name} ${o.candidate.last_name}`;

    return (
        <>
            <Head title={candidate} />
            <DetailPage
                title={candidate}
                description="View the offer and its letter."
                back={offerRoutes.index()}
                summary={
                    <Summary
                        media={
                            <UserAvatar
                                name={candidate}
                                gender={o.candidate.gender}
                                className="size-32"
                            />
                        }
                        title={candidate}
                        subtitle={o.job?.title}
                        status={o.status}
                        facts={[
                            [Mail, o.candidate.email],
                            [Briefcase, o.job?.department?.name],
                            [Banknote, money(Number(o.salary))],
                            [
                                CalendarDays,
                                `${t('Expires')}: ${date(o.expiration_date)}`,
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Offer Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        [
                                            'Candidate',
                                            can('view-candidates') ? (
                                                <Link
                                                    href={candidateRoutes.show(
                                                        o.candidate.id,
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
                                            'Job Posting',
                                            o.job && (
                                                <span className="flex flex-wrap items-center gap-2">
                                                    {o.job.title}
                                                    {o.job.job_code && (
                                                        <IdBadge>
                                                            {o.job.job_code}
                                                        </IdBadge>
                                                    )}
                                                </span>
                                            ),
                                        ],
                                        ['Department', o.job?.department?.name],
                                        ['Offer Template', o.template?.name],
                                        ['Salary', money(Number(o.salary))],
                                        [
                                            'Bonus',
                                            o.bonus && money(Number(o.bonus)),
                                        ],
                                        [
                                            'Offer Date',
                                            <DateCell
                                                key="od"
                                                value={o.offer_date}
                                            />,
                                        ],
                                        [
                                            'Start Date',
                                            <DateCell
                                                key="sd"
                                                value={o.start_date}
                                            />,
                                        ],
                                        [
                                            'Expiration Date',
                                            <DateCell
                                                key="ed"
                                                value={o.expiration_date}
                                            />,
                                        ],
                                        [
                                            'Response Date',
                                            <DateCell
                                                key="rd"
                                                value={o.response_date}
                                            />,
                                        ],
                                        [
                                            'Approved By',
                                            o.approver && (
                                                <PersonCell
                                                    name={o.approver.name}
                                                    detail={o.approver.email}
                                                    src={o.approver.avatar}
                                                />
                                            ),
                                        ],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="s"
                                                status={o.status}
                                            />,
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Benefits"
                                    value={o.benefits}
                                />
                                {o.decline_reason && (
                                    <TextBlock
                                        label="Decline Reason"
                                        value={o.decline_reason}
                                    />
                                )}
                            </div>
                        ),
                    },
                    {
                        label: 'Offer Letter',
                        content:
                            letter === null ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('This offer has no template.')}
                                </p>
                            ) : (
                                // Sandboxed: template HTML can't run scripts or reach the app.
                                <iframe
                                    title={t('Offer Letter')}
                                    sandbox=""
                                    className="h-[60dvh] w-full rounded-md border bg-white"
                                    srcDoc={`<body style="font-family:system-ui,sans-serif;font-size:14px;white-space:pre-wrap;margin:16px;color:#111">${letter}</body>`}
                                />
                            ),
                    },
                ]}
            />
        </>
    );
}

OfferShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: offerRoutes.index() },
        { title: 'Offers', href: offerRoutes.index() },
        { title: 'Offer Details', href: offerRoutes.index() },
    ],
};
