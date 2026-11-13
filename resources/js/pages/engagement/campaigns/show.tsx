import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Gauge, ShieldAlert, TrendingUp, Users } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatCards } from '@/components/stat-cards';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import campaignRoutes from '@/routes/engagement/campaigns';

type Tier = {
    level: number;
    name: string;
    min_score: string;
    need: string | null;
    profile: string | null;
    action: string | null;
    count: number;
    share: number;
};

type Summary = {
    respondents: number;
    invited: number;
    response_rate: number | null;
    suppressed: boolean;
    overall: number | null;
    elements: { id: number; name: string; score: number }[];
    tiers: Tier[];
    statements: {
        id: number;
        text: string;
        element: string | null;
        score: number;
    }[];
};

type Breakdown = {
    rows: {
        label: string;
        respondents: number;
        overall: number;
        elements: Record<number, number>;
    }[];
    hidden: number;
};

const DIMENSION_LABELS: Record<string, string> = {
    department: 'Department',
    designation: 'Designation',
    branch: 'Branch',
    gender: 'Gender',
    age_band: 'Age',
    tenure_band: 'Years of service',
};

const percent = (score: number | null) =>
    score === null ? '—' : `${Math.round(score * 100)}%`;

/** Same colour bands as the tiers: green engaged, amber in between, red at risk. */
const tone = (score: number) =>
    score >= 0.81
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
        : score >= 0.66
          ? 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-200'
          : score >= 0.51
            ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
            : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';

const barTone = (score: number) =>
    score >= 0.81
        ? 'bg-emerald-500'
        : score >= 0.66
          ? 'bg-lime-500'
          : score >= 0.51
            ? 'bg-amber-500'
            : 'bg-red-500';

export default function EngagementCampaignResults({
    campaign,
    summary,
    by,
    dimensions,
    breakdown,
    elements,
}: {
    campaign: {
        id: number;
        name: string;
        status: string;
        min_group_size: number;
        statements_count: number;
    };
    summary: Summary;
    by: string;
    dimensions: string[];
    breakdown: Breakdown | null;
    elements: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const overallTier =
        summary.overall === null
            ? null
            : summary.tiers.find(
                  (tier) => summary.overall! >= Number(tier.min_score),
              );
    const shownElements = elements.filter((element) =>
        summary.elements.some((e) => e.id === element.id),
    );

    return (
        <>
            <Head title={campaign.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={campaign.name}
                    description={t(':count statements · results', {
                        count: campaign.statements_count,
                    })}
                    action={
                        <div className="flex items-center gap-3">
                            <StatusBadge status={campaign.status} />
                            <Button variant="outline" asChild>
                                <Link href={campaignRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <StatCards
                    stats={[
                        {
                            label: 'Respondents',
                            value: summary.respondents,
                            note: t('of :count employees', {
                                count: summary.invited,
                            }),
                            icon: Users,
                            tone: 'bg-sky-100 text-sky-600',
                        },
                        {
                            label: 'Response Rate',
                            value: percent(summary.response_rate),
                            note: 'Share of current employees',
                            icon: TrendingUp,
                            tone: 'bg-violet-100 text-violet-600',
                        },
                        {
                            label: 'Engagement Score',
                            value: percent(summary.overall),
                            note: 'Favourable answers overall',
                            icon: Gauge,
                            tone: 'bg-emerald-100 text-emerald-600',
                        },
                        {
                            label: 'Overall Tier',
                            value: overallTier?.name ?? '—',
                            note: overallTier?.action ?? '',
                            icon: ShieldAlert,
                            tone: 'bg-amber-100 text-amber-600',
                        },
                    ]}
                />

                {summary.suppressed ? (
                    <div className="rounded-xl border bg-card p-6 text-sm text-muted-foreground shadow-sm">
                        {t(
                            'Results appear once at least :count employees have answered, so no one can be identified.',
                            { count: campaign.min_group_size },
                        )}
                    </div>
                ) : (
                    <>
                        <div className="grid gap-6 lg:grid-cols-2">
                            <section className="rounded-xl border bg-card p-5 shadow-sm">
                                <h2 className="mb-4 font-semibold">
                                    {t('Score by element')}
                                </h2>
                                <div className="grid gap-3">
                                    {summary.elements.map((element) => (
                                        <div key={element.id}>
                                            <div className="mb-1 flex justify-between text-sm">
                                                <span>{element.name}</span>
                                                <span className="font-medium">
                                                    {percent(element.score)}
                                                </span>
                                            </div>
                                            <div className="h-2 rounded-full bg-muted">
                                                <div
                                                    className={`h-2 rounded-full ${barTone(element.score)}`}
                                                    style={{
                                                        width: `${element.score * 100}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="rounded-xl border bg-card p-5 shadow-sm">
                                <h2 className="mb-4 font-semibold">
                                    {t('Employees by tier')}
                                </h2>
                                <div className="grid gap-3">
                                    {summary.tiers.map((tier) => (
                                        <div key={tier.level}>
                                            <div className="mb-1 flex justify-between text-sm">
                                                <span>
                                                    <span className="font-medium">
                                                        {tier.name}
                                                    </span>{' '}
                                                    <span className="text-muted-foreground">
                                                        · {tier.need}
                                                    </span>
                                                </span>
                                                <span>
                                                    {percent(tier.share)} (
                                                    {tier.count})
                                                </span>
                                            </div>
                                            <div className="h-2 rounded-full bg-muted">
                                                <div
                                                    className={`h-2 rounded-full ${barTone(Number(tier.min_score))}`}
                                                    style={{
                                                        width: `${tier.share * 100}%`,
                                                    }}
                                                />
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {tier.action}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>

                        <section className="rounded-xl border bg-card shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-3 p-5">
                                <h2 className="font-semibold">
                                    {t('Breakdown')}
                                </h2>
                                <SelectField
                                    aria-label={t('Break down by')}
                                    className="w-auto min-w-48"
                                    value={by}
                                    onChange={(e) =>
                                        router.get(
                                            campaignRoutes.show(campaign.id),
                                            { by: e.target.value },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    }
                                >
                                    {dimensions.map((dimension) => (
                                        <option
                                            key={dimension}
                                            value={dimension}
                                        >
                                            {t(DIMENSION_LABELS[dimension])}
                                        </option>
                                    ))}
                                </SelectField>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y bg-muted/50 text-start">
                                        <tr>
                                            <th className="px-4 py-2 text-start font-medium">
                                                {t(DIMENSION_LABELS[by])}
                                            </th>
                                            <th className="px-3 py-2 text-end font-medium">
                                                {t('Respondents')}
                                            </th>
                                            <th className="px-3 py-2 text-center font-medium">
                                                {t('Overall')}
                                            </th>
                                            {shownElements.map((element) => (
                                                <th
                                                    key={element.id}
                                                    className="px-3 py-2 text-center font-medium"
                                                >
                                                    {element.name}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {breakdown?.rows.map((row) => (
                                            <tr
                                                key={row.label}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-2 font-medium whitespace-nowrap">
                                                    {row.label}
                                                </td>
                                                <td className="px-3 py-2 text-end">
                                                    {row.respondents}
                                                </td>
                                                <td className="px-2 py-1 text-center">
                                                    <span
                                                        className={`inline-block min-w-12 rounded px-2 py-1 font-medium ${tone(row.overall)}`}
                                                    >
                                                        {percent(row.overall)}
                                                    </span>
                                                </td>
                                                {shownElements.map(
                                                    (element) => {
                                                        const score =
                                                            row.elements[
                                                                element.id
                                                            ];

                                                        return (
                                                            <td
                                                                key={element.id}
                                                                className="px-2 py-1 text-center"
                                                            >
                                                                {score ===
                                                                undefined ? (
                                                                    '—'
                                                                ) : (
                                                                    <span
                                                                        className={`inline-block min-w-12 rounded px-2 py-1 ${tone(score)}`}
                                                                    >
                                                                        {percent(
                                                                            score,
                                                                        )}
                                                                    </span>
                                                                )}
                                                            </td>
                                                        );
                                                    },
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {breakdown && breakdown.hidden > 0 && (
                                <p className="border-t px-5 py-3 text-xs text-muted-foreground">
                                    {t(
                                        ':count groups with fewer than :min respondents are hidden.',
                                        {
                                            count: breakdown.hidden,
                                            min: campaign.min_group_size,
                                        },
                                    )}
                                </p>
                            )}
                        </section>

                        <div className="grid gap-6 lg:grid-cols-2">
                            {(
                                [
                                    [
                                        'Lowest-scoring statements',
                                        summary.statements.slice(0, 5),
                                    ],
                                    [
                                        'Highest-scoring statements',
                                        [...summary.statements]
                                            .reverse()
                                            .slice(0, 5),
                                    ],
                                ] as const
                            ).map(([title, rows]) => (
                                <section
                                    key={title}
                                    className="rounded-xl border bg-card p-5 shadow-sm"
                                >
                                    <h2 className="mb-3 font-semibold">
                                        {t(title)}
                                    </h2>
                                    <ul className="grid gap-3">
                                        {rows.map((statement) => (
                                            <li
                                                key={statement.id}
                                                className="flex items-start justify-between gap-4 text-sm"
                                            >
                                                <div>
                                                    <div>{statement.text}</div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {statement.element ??
                                                            t('Overall')}
                                                    </div>
                                                </div>
                                                <span
                                                    className={`shrink-0 rounded px-2 py-1 font-medium ${tone(statement.score)}`}
                                                >
                                                    {percent(statement.score)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

EngagementCampaignResults.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Engagement Survey', href: campaignRoutes.index() },
        { title: 'Results', href: campaignRoutes.index() },
    ],
};
