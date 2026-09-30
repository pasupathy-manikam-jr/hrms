import { Head } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    Download,
    Factory,
    MapPin,
    UserCheck,
    Users,
} from 'lucide-react';
import {
    DetailPage,
    Fields,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import participantRoutes from '@/routes/benchmark/participants';

type SalaryRow = {
    id: number;
    job_family: string;
    job_title: string;
    own_title: string | null;
    job_level: string;
    headcount: number;
    male: number;
    female: number;
    median_salary: string;
    avg_overall: string | null;
    guaranteed_bonus_months: string | null;
    total_allowances: string;
    market_median: number | null;
    market_companies: number;
};

type Benefit = {
    id: number;
    item: string;
    same_for_all: boolean | null;
    company_value: string | null;
    exec_value: string | null;
    manager_value: string | null;
    remarks: string | null;
};

type Attrition = Record<string, string | number | boolean | null>;

type Participant = {
    id: number;
    company_name: string;
    industry: string;
    state: string;
    employee_band: string;
    revenue_band: string | null;
    ownership_type: string | null;
    locations: number | null;
    listed_status: string | null;
    unionised: string | null;
    authorised_name: string | null;
    authorised_designation: string | null;
    consent_date: string | null;
    file_name: string | null;
    warnings: string[] | null;
    created_at: string;
    cycle: { id: number; name: string; min_companies: number };
    benefits: Benefit[];
    attrition: Attrition | null;
};

export default function SurveyParticipantShow({
    participant: p,
    hasFile,
    salaryRows,
    benefitLabels,
}: {
    participant: Participant;
    hasFile: boolean;
    salaryRows: SalaryRow[];
    benefitLabels: Record<string, string>;
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const a = p.attrition ?? {};

    const salaryTable = (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-start text-muted-foreground">
                    <tr className="border-b">
                        {[
                            'Job',
                            'Level',
                            'Headcount',
                            'Median Base',
                            'Market Median',
                            'vs Market',
                            'Allowances',
                            'Bonus (months)',
                        ].map((label) => (
                            <th
                                key={label}
                                className="px-2 py-2 text-start font-medium whitespace-nowrap"
                            >
                                {t(label)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {salaryRows.map((row) => {
                        const median = Number(row.median_salary);
                        const diff =
                            row.market_median !== null
                                ? ((median - row.market_median) /
                                      row.market_median) *
                                  100
                                : null;

                        return (
                            <tr key={row.id} className="border-b align-top">
                                <td className="px-2 py-2">
                                    <div className="font-medium">
                                        {row.job_title}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {row.own_title ?? row.job_family}
                                    </div>
                                </td>
                                <td className="px-2 py-2">{row.job_level}</td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {row.headcount}
                                    <span className="text-xs text-muted-foreground">
                                        {' '}
                                        ({row.male}M / {row.female}F)
                                    </span>
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {money(median)}
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {row.market_median !== null ? (
                                        money(row.market_median)
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            {t(
                                                'Insufficient data (:n companies)',
                                                { n: row.market_companies },
                                            )}
                                        </span>
                                    )}
                                </td>
                                <td
                                    className={cn(
                                        'px-2 py-2 font-medium whitespace-nowrap',
                                        diff !== null &&
                                            (diff >= 0
                                                ? 'text-emerald-600'
                                                : 'text-red-600'),
                                    )}
                                >
                                    {diff === null
                                        ? '-'
                                        : `${diff > 0 ? '+' : ''}${diff.toFixed(1)}%`}
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {money(Number(row.total_allowances))}
                                </td>
                                <td className="px-2 py-2">
                                    {row.guaranteed_bonus_months ?? '-'}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );

    const benefitsTable = (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-muted-foreground">
                    <tr className="border-b">
                        {[
                            'Benefit',
                            'All Levels',
                            'Non-Exec / Executive',
                            'Managerial & Above',
                            'Remarks',
                        ].map((label) => (
                            <th
                                key={label}
                                className="px-2 py-2 text-start font-medium"
                            >
                                {t(label)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {p.benefits.map((b) => (
                        <tr key={b.id} className="border-b align-top">
                            <td className="px-2 py-2 font-medium">
                                {t(benefitLabels[b.item] ?? b.item)}
                            </td>
                            <td className="px-2 py-2">
                                {b.company_value ?? '-'}
                            </td>
                            <td className="px-2 py-2">{b.exec_value ?? '-'}</td>
                            <td className="px-2 py-2">
                                {b.manager_value ?? '-'}
                            </td>
                            <td className="px-2 py-2 text-muted-foreground">
                                {b.remarks ?? ''}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );

    const joined = (...values: unknown[]) =>
        values.filter(Boolean).join(', ') || null;

    return (
        <>
            <Head title={p.company_name} />
            <DetailPage
                title={p.company_name}
                description={t('Submission for the :cycle survey', {
                    cycle: p.cycle.name,
                })}
                back={participantRoutes.index({ query: { cycle: p.cycle.id } })}
                summary={
                    <>
                        <Summary
                            media={<SummaryIcon icon={Building2} />}
                            title={p.company_name}
                            subtitle={p.industry}
                            facts={[
                                [MapPin, p.state],
                                [Users, `${p.employee_band} ${t('employees')}`],
                                [Factory, p.ownership_type],
                                [
                                    UserCheck,
                                    joined(
                                        p.authorised_name,
                                        p.authorised_designation,
                                    ),
                                ],
                                [
                                    CalendarDays,
                                    p.consent_date && date(p.consent_date),
                                ],
                            ]}
                        />
                        {hasFile && (
                            <Button variant="outline" className="mt-5" asChild>
                                <a
                                    href={participantRoutes.download.url(p.id)}
                                    download
                                >
                                    <Download /> {t('Original Workbook')}
                                </a>
                            </Button>
                        )}
                    </>
                }
                tabs={[
                    {
                        label: 'Salary Data',
                        heading: 'Salary Data vs Market',
                        content: (
                            <div className="grid gap-4">
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Market median is the median base salary of all companies in this cycle for the same job and level, shown when at least :min companies reported it.',
                                        { min: p.cycle.min_companies },
                                    )}
                                </p>
                                {salaryTable}
                            </div>
                        ),
                    },
                    { label: 'Benefits', content: benefitsTable },
                    {
                        label: 'Attrition & Hiring',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        ['Attrition rate %', a.attrition_rate],
                                        [
                                            'New-hire attrition rate %',
                                            a.new_hire_attrition_rate,
                                        ],
                                        ['Retirements', a.retirements],
                                        [
                                            'Involuntary terminations',
                                            a.involuntary_terminations,
                                        ],
                                        [
                                            'Contract non-renewals',
                                            a.contract_non_renewals,
                                        ],
                                        [
                                            'Time to fill (days)',
                                            a.time_to_fill_days,
                                        ],
                                        [
                                            'Hardest to hire',
                                            joined(
                                                a.hardest_to_hire_1,
                                                a.hardest_to_hire_2,
                                                a.hardest_to_hire_3,
                                            ),
                                        ],
                                        [
                                            'Hardest to retain',
                                            joined(
                                                a.hardest_to_retain_1,
                                                a.hardest_to_retain_2,
                                                a.hardest_to_retain_3,
                                            ),
                                        ],
                                        ['Headcount plan', a.headcount_plan],
                                        [
                                            'Pay a reason for leaving',
                                            a.pay_reason_for_leaving,
                                        ],
                                        [
                                            'Retrenched',
                                            a.retrenched === null ||
                                            a.retrenched === undefined
                                                ? null
                                                : t(
                                                      a.retrenched
                                                          ? 'Yes'
                                                          : 'No',
                                                  ),
                                        ],
                                        [
                                            'Retrenchment driver',
                                            a.retrenchment_driver,
                                        ],
                                        [
                                            'Above statutory benefits',
                                            a.retrenchment_above_statutory,
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Retention initiatives"
                                    value={a.retention_initiatives as string}
                                />
                                <TextBlock
                                    label="Other reasons for leaving"
                                    value={a.other_leaving_reasons as string}
                                />
                                <TextBlock
                                    label="Comments"
                                    value={a.comments as string}
                                />
                            </div>
                        ),
                    },
                    {
                        label: 'Company Profile',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        ['Industry', p.industry],
                                        ['State (HQ)', p.state],
                                        [
                                            'Number of employees',
                                            p.employee_band,
                                        ],
                                        ['Annual revenue', p.revenue_band],
                                        ['Ownership', p.ownership_type],
                                        ['Operating locations', p.locations],
                                        ['Listed status', p.listed_status],
                                        ['Unionised workforce', p.unionised],
                                        ['Uploaded file', p.file_name],
                                        ['Uploaded at', date(p.created_at)],
                                    ]}
                                />
                                {!!p.warnings?.length && (
                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                        <div className="mb-1 font-semibold">
                                            {t('Warnings')}
                                        </div>
                                        <ul className="list-disc ps-5">
                                            {p.warnings.map((warning) => (
                                                <li key={warning}>{warning}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        ),
                    },
                ]}
            />
        </>
    );
}

SurveyParticipantShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Benchmark Survey', href: participantRoutes.index() },
        { title: 'Participants', href: participantRoutes.index() },
    ],
};
