import { Head, Link } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ClipboardList } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import myRoutes from '@/routes/engagement/my';

type Campaign = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    closes_on: string | null;
    statements_count: number;
    answered_at: string | null;
    can_answer: boolean;
};

export default function MySurveys({
    campaigns,
    hasEmployeeRecord,
}: {
    campaigns: Campaign[];
    hasEmployeeRecord: boolean;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();

    return (
        <>
            <Head title={t('My Surveys')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="My Surveys"
                    description="Your answers are confidential: results are only reported for groups, never for individuals."
                />

                {!hasEmployeeRecord && (
                    <div className="rounded-xl border bg-card p-6 text-sm text-muted-foreground shadow-sm">
                        {t(
                            'Surveys are answered by employees. Your account has no employee record.',
                        )}
                    </div>
                )}

                {hasEmployeeRecord && campaigns.length === 0 && (
                    <div className="rounded-xl border bg-card p-6 text-sm text-muted-foreground shadow-sm">
                        {t('There are no surveys for you right now.')}
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    {campaigns.map((campaign) => (
                        <div
                            key={campaign.id}
                            className="flex flex-col gap-3 rounded-xl border bg-card p-5 shadow-sm"
                        >
                            <div className="flex items-start gap-3">
                                <ClipboardList className="mt-0.5 size-5 text-muted-foreground" />
                                <div>
                                    <div className="font-semibold">
                                        {campaign.name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {t(
                                            ':count statements · about :minutes minutes',
                                            {
                                                count: campaign.statements_count,
                                                minutes: Math.max(
                                                    5,
                                                    Math.round(
                                                        campaign.statements_count /
                                                            6,
                                                    ),
                                                ),
                                            },
                                        )}
                                    </div>
                                </div>
                            </div>
                            {campaign.description && (
                                <p className="text-sm whitespace-pre-line">
                                    {campaign.description}
                                </p>
                            )}
                            <div className="mt-auto flex items-center justify-between gap-3 text-sm">
                                {campaign.answered_at ? (
                                    <span className="flex items-center gap-2 text-emerald-700 dark:text-emerald-400">
                                        <CheckCircle2 className="size-4" />
                                        {t('Answered on :date', {
                                            date: date(campaign.answered_at),
                                        })}
                                    </span>
                                ) : campaign.closes_on ? (
                                    <span className="flex items-center gap-2 whitespace-nowrap text-muted-foreground">
                                        <CalendarDays className="size-4" />
                                        {t('Closes :date', {
                                            date: date(campaign.closes_on),
                                        })}
                                    </span>
                                ) : (
                                    <span />
                                )}
                                {campaign.can_answer && (
                                    <Button asChild>
                                        <Link href={myRoutes.show(campaign.id)}>
                                            {t('Start survey')}
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

MySurveys.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My Surveys', href: myRoutes.index() },
    ],
};
