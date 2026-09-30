import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    Briefcase,
    FileDown,
    FileText,
    ShieldCheck,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatCards } from '@/components/stat-cards';
import { applyFilters, FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import analyticsRoutes from '@/routes/benchmark/analytics';
import cycleRoutes from '@/routes/benchmark/cycles';
import type { TableFilters } from '@/types';

type Suppressible = { suppressed: boolean; companies: number };

type SalaryStat = Suppressible & {
    job_family: string;
    job_title: string;
    job_level: string;
    incumbents: number;
    p25?: number | null;
    median?: number | null;
    p75?: number | null;
    average?: number | null;
    min?: number;
    max?: number;
    bonus_months?: number | null;
    allowances?: number | null;
    total_cash?: number | null;
};

type GenderStat = Suppressible & {
    name: string;
    male: number;
    female: number;
    avg_male?: number | null;
    avg_female?: number | null;
    gap_percent?: number | null;
    female_share?: number | null;
};

type BenefitStat = Suppressible & {
    item: string;
    label: string;
    kind: 'yes_no' | 'number' | 'text';
    yes_percent?: number | null;
    median?: number | null;
    exec?: number | null;
    manager?: number | null;
    tiered_percent?: number;
    notes?: string[];
};

/** The second cut ("Compare with"): its size and each role's median. */
type Comparison = {
    overview: Report['overview'];
    salaries: Record<
        string,
        { median: number | null; companies: number; suppressed: boolean }
    >;
};

const COMPARE_FILTERS = [
    ['industry', 'Industry'],
    ['state', 'State'],
    ['employee_band', 'Company Size'],
    ['ownership_type', 'Ownership'],
] as const;

type Ranked = { name: string; score: number; mentions: number };

type Report = {
    overview: {
        companies: number;
        roles: number;
        incumbents: number;
        suppressed: boolean;
    };
    salaries: SalaryStat[];
    gender: { levels: GenderStat[]; families: GenderStat[] };
    allowances: {
        types: {
            type: string;
            label: string;
            companies: number;
            prevalence: number | null;
            average: number | null;
            median: number | null;
        }[];
        notes: string[];
    };
    benefits: BenefitStat[];
    attrition: { suppressed: boolean; companies: number } & Partial<{
        attrition_rate: number | null;
        new_hire_attrition_rate: number | null;
        retirements_per_100: number | null;
        terminations_per_100: number | null;
        non_renewals_per_100: number | null;
        time_to_fill_days: number | null;
        hardest_to_hire: Ranked[];
        hardest_to_retain: Ranked[];
        headcount_plan: Record<string, number>;
        pay_reason_for_leaving: Record<string, number>;
        retrenched_percent: number;
        retrenchment_drivers: Record<string, number>;
        retrenchment_above_statutory: Record<string, number>;
    }>;
    workforce: { suppressed: boolean } & Partial<{
        tenure: { label: string; headcount: number; percent: number }[];
        experience: { label: string; roles: number; percent: number }[];
        shift_based_percent: number | null;
        levels: { label: string; headcount: number }[];
    }>;
    mix: Record<string, Record<string, number>>;
};

const VIEWS = [
    ['salaries', 'Salary Benchmarks'],
    ['gender', 'Gender Pay'],
    ['allowances', 'Allowances'],
    ['benefits', 'Benefits'],
    ['attrition', 'Attrition & Hiring'],
    ['workforce', 'Workforce'],
    ['participants', 'Participants'],
] as const;

const PROFILE_FILTERS = [
    ['industry', 'All Industries'],
    ['state', 'All States'],
    ['employee_band', 'All Company Sizes'],
    ['revenue_band', 'All Revenue Bands'],
    ['ownership_type', 'All Ownership Types'],
    ['listed_status', 'Listed & Private'],
    ['unionised', 'Unionised or Not'],
    ['job_family', 'All Job Families'],
    ['job_title', 'All Job Titles'],
    ['job_level', 'All Job Levels'],
] as const;

function Table({
    headings,
    children,
}: {
    headings: string[];
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-muted-foreground">
                    <tr className="border-b">
                        {headings.map((heading) => (
                            <th
                                key={heading}
                                className="px-2 py-2 text-start font-medium whitespace-nowrap"
                            >
                                {t(heading)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

function Cell({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <td className={cn('border-b px-2 py-2 align-top', className)}>
            {children ?? '-'}
        </td>
    );
}

/** A percentage with a bar behind it. */
function Bar({ percent }: { percent: number | null | undefined }) {
    if (percent === null || percent === undefined) {
        return <span className="text-muted-foreground">-</span>;
    }

    return (
        <div className="flex min-w-32 items-center gap-2">
            <div className="h-2 flex-1 rounded-full bg-muted">
                <div
                    className="h-2 rounded-full bg-primary"
                    style={{ width: `${Math.min(100, percent)}%` }}
                />
            </div>
            <span className="w-12 text-end tabular-nums">{percent}%</span>
        </div>
    );
}

function Hidden() {
    const { t } = useTranslation();

    return (
        <span className="text-xs whitespace-nowrap text-muted-foreground italic">
            {t('Insufficient data')}
        </span>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    const { t } = useTranslation();

    return (
        <section className="grid gap-3">
            <h3 className="font-semibold">{t(title)}</h3>
            {children}
        </section>
    );
}

export default function BenchmarkAnalytics({
    cycles,
    cycle,
    filters,
    options,
    report,
    comparison,
}: {
    cycles: { id: number; name: string }[];
    cycle: { id: number; name: string; min_companies: number } | null;
    filters: TableFilters;
    options: Record<string, string[]>;
    report: Report | null;
    comparison: Comparison | null;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const url = analyticsRoutes.index();
    const view = (filters.view as string) || 'salaries';
    const query = Object.fromEntries(
        Object.entries(filters).filter(
            ([key, value]) => key !== 'view' && value !== undefined,
        ),
    ) as Record<string, string>;
    const rm = (value: number | null | undefined) =>
        value === null || value === undefined ? '-' : money(value);

    if (!cycle || !report) {
        return (
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Head title={t('Benchmark Analytics')} />
                <PageHeader
                    title="Benchmark Analytics"
                    description="Pooled salary and benefits statistics from every uploaded company."
                />
                <div className="rounded-xl border bg-card p-10 text-center text-muted-foreground">
                    {t('Create a survey cycle and upload workbooks first.')}{' '}
                    <Link
                        href={cycleRoutes.index()}
                        className="text-primary hover:underline"
                    >
                        {t('Survey Cycles')}
                    </Link>
                </div>
            </div>
        );
    }

    const views: Record<string, ReactNode> = {
        salaries: (
            <Table
                headings={[
                    'Job',
                    'Level',
                    'Companies',
                    'Incumbents',
                    'P25',
                    'Median',
                    'P75',
                    'Average',
                    'Allowances',
                    'Bonus (months)',
                    'Total Monthly Cash',
                    ...(comparison ? ['Compared Median', 'Difference'] : []),
                ]}
            >
                {report.salaries.map((row) => (
                    <tr key={`${row.job_title}|${row.job_level}`}>
                        <Cell>
                            <div className="font-medium">{row.job_title}</div>
                            <div className="text-xs text-muted-foreground">
                                {row.job_family}
                            </div>
                        </Cell>
                        <Cell>{row.job_level}</Cell>
                        <Cell>{row.companies}</Cell>
                        <Cell>{row.incumbents}</Cell>
                        {row.suppressed ? (
                            <td
                                colSpan={7}
                                className="border-b px-2 py-2 align-top"
                            >
                                <Hidden />
                            </td>
                        ) : (
                            <>
                                <Cell className="whitespace-nowrap">
                                    {rm(row.p25)}
                                </Cell>
                                <Cell className="font-semibold whitespace-nowrap">
                                    {rm(row.median)}
                                </Cell>
                                <Cell className="whitespace-nowrap">
                                    {rm(row.p75)}
                                </Cell>
                                <Cell className="whitespace-nowrap">
                                    {rm(row.average)}
                                </Cell>
                                <Cell className="whitespace-nowrap">
                                    {rm(row.allowances)}
                                </Cell>
                                <Cell>{row.bonus_months}</Cell>
                                <Cell className="whitespace-nowrap">
                                    {rm(row.total_cash)}
                                </Cell>
                            </>
                        )}
                        {comparison && (
                            <CompareCells
                                median={row.suppressed ? null : row.median}
                                other={
                                    comparison.salaries[
                                        `${row.job_title}|${row.job_level}`
                                    ]
                                }
                                format={rm}
                            />
                        )}
                    </tr>
                ))}
            </Table>
        ),
        gender: (
            <div className="grid gap-8">
                {(
                    [
                        ['levels', 'By Job Level'],
                        ['families', 'By Job Family'],
                    ] as const
                ).map(([key, title]) => (
                    <Section key={key} title={title}>
                        <Table
                            headings={[
                                'Group',
                                'Companies',
                                'Male / Female',
                                'Avg. Male',
                                'Avg. Female',
                                'Pay Gap',
                                'Female Share',
                            ]}
                        >
                            {report.gender[key].map((row) => (
                                <tr key={row.name}>
                                    <Cell className="font-medium">
                                        {row.name}
                                    </Cell>
                                    <Cell>{row.companies}</Cell>
                                    <Cell>
                                        {row.male} / {row.female}
                                    </Cell>
                                    {row.suppressed ? (
                                        <td
                                            colSpan={4}
                                            className="border-b px-2 py-2"
                                        >
                                            <Hidden />
                                        </td>
                                    ) : (
                                        <>
                                            <Cell>{rm(row.avg_male)}</Cell>
                                            <Cell>{rm(row.avg_female)}</Cell>
                                            <Cell
                                                className={cn(
                                                    'font-medium',
                                                    (row.gap_percent ?? 0) > 0
                                                        ? 'text-red-600'
                                                        : 'text-emerald-600',
                                                )}
                                            >
                                                {row.gap_percent === null ||
                                                row.gap_percent === undefined
                                                    ? '-'
                                                    : `${row.gap_percent}%`}
                                            </Cell>
                                            <Cell>
                                                <Bar
                                                    percent={row.female_share}
                                                />
                                            </Cell>
                                        </>
                                    )}
                                </tr>
                            ))}
                        </Table>
                    </Section>
                ))}
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Pay gap = (average male − average female) ÷ average male, headcount-weighted. A positive gap means women earn less.',
                    )}
                </p>
            </div>
        ),
        allowances: (
            <div className="grid gap-8">
                <Table
                    headings={[
                        'Allowance',
                        'Companies Paying',
                        '% of Companies',
                        'Average (where paid)',
                        'Median (where paid)',
                    ]}
                >
                    {report.allowances.types.map((row) => (
                        <tr key={row.type}>
                            <Cell className="font-medium">{t(row.label)}</Cell>
                            <Cell>{row.companies}</Cell>
                            <Cell>
                                {report.overview.suppressed ? (
                                    <Hidden />
                                ) : (
                                    <Bar percent={row.prevalence} />
                                )}
                            </Cell>
                            <Cell>
                                {row.average === null ? (
                                    <Hidden />
                                ) : (
                                    rm(row.average)
                                )}
                            </Cell>
                            <Cell>
                                {row.median === null ? (
                                    <Hidden />
                                ) : (
                                    rm(row.median)
                                )}
                            </Cell>
                        </tr>
                    ))}
                </Table>
                {report.allowances.notes.length > 0 && (
                    <Section title="Other Allowances Described">
                        <ul className="list-disc ps-5 text-sm">
                            {report.allowances.notes.map((note) => (
                                <li key={note}>{note}</li>
                            ))}
                        </ul>
                    </Section>
                )}
            </div>
        ),
        benefits: (
            <Table
                headings={[
                    'Benefit',
                    'Companies',
                    '% Yes / Median',
                    'Non-Exec / Executive',
                    'Managerial & Above',
                ]}
            >
                {report.benefits.map((row) => (
                    <tr key={row.item}>
                        <Cell className="font-medium">{t(row.label)}</Cell>
                        <Cell>{row.companies}</Cell>
                        {row.suppressed ? (
                            <td colSpan={3} className="border-b px-2 py-2">
                                <Hidden />
                            </td>
                        ) : row.kind === 'yes_no' ? (
                            <>
                                <Cell>
                                    <Bar percent={row.yes_percent} />
                                </Cell>
                                <Cell>
                                    <Bar percent={row.exec} />
                                </Cell>
                                <Cell>
                                    <Bar percent={row.manager} />
                                </Cell>
                            </>
                        ) : row.kind === 'number' ? (
                            <>
                                <Cell className="font-semibold">
                                    {row.median}
                                </Cell>
                                <Cell>{row.exec}</Cell>
                                <Cell>{row.manager}</Cell>
                            </>
                        ) : (
                            <td colSpan={3} className="border-b px-2 py-2">
                                <ul className="list-disc ps-5 text-muted-foreground">
                                    {row.notes?.map((note) => (
                                        <li key={note}>{note}</li>
                                    ))}
                                </ul>
                            </td>
                        )}
                    </tr>
                ))}
            </Table>
        ),
        attrition: report.attrition.suppressed ? (
            <Hidden />
        ) : (
            <AttritionView attrition={report.attrition} />
        ),
        workforce: report.workforce.suppressed ? (
            <Hidden />
        ) : (
            <div className="grid gap-8 lg:grid-cols-2">
                <Section title="Tenure">
                    <Table headings={['Tenure', 'Headcount', 'Share']}>
                        {report.workforce.tenure?.map((row) => (
                            <tr key={row.label}>
                                <Cell>{t(row.label)}</Cell>
                                <Cell>{row.headcount}</Cell>
                                <Cell>
                                    <Bar percent={row.percent} />
                                </Cell>
                            </tr>
                        ))}
                    </Table>
                </Section>
                <Section title="Experience Required to Hire">
                    <Table headings={['Experience', 'Roles', 'Share']}>
                        {report.workforce.experience?.map((row) => (
                            <tr key={row.label}>
                                <Cell>{row.label}</Cell>
                                <Cell>{row.roles}</Cell>
                                <Cell>
                                    <Bar percent={row.percent} />
                                </Cell>
                            </tr>
                        ))}
                    </Table>
                </Section>
                <Section title="Headcount by Level">
                    <Table headings={['Level', 'Headcount']}>
                        {report.workforce.levels?.map((row) => (
                            <tr key={row.label}>
                                <Cell>{row.label}</Cell>
                                <Cell>{row.headcount}</Cell>
                            </tr>
                        ))}
                    </Table>
                </Section>
                <Section title="Shift-Based Roles">
                    <Bar percent={report.workforce.shift_based_percent} />
                </Section>
            </div>
        ),
        participants: (
            <div className="grid gap-8 lg:grid-cols-2">
                {(
                    [
                        ['industry', 'By Industry'],
                        ['state', 'By State'],
                        ['employee_band', 'By Company Size'],
                        ['ownership_type', 'By Ownership'],
                    ] as const
                ).map(([key, title]) => (
                    <Section key={key} title={title}>
                        <Table headings={['Category', 'Companies']}>
                            {Object.entries(report.mix[key] ?? {}).map(
                                ([name, count]) => (
                                    <tr key={name}>
                                        <Cell>{name}</Cell>
                                        <Cell>{count}</Cell>
                                    </tr>
                                ),
                            )}
                        </Table>
                    </Section>
                ))}
            </div>
        ),
    };

    return (
        <>
            <Head title={t('Benchmark Analytics')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Benchmark Analytics"
                    description="Pooled salary and benefits statistics. Pick any combination of filters to cut the data."
                    action={
                        can('export-benchmark-survey') && (
                            <>
                                <Button variant="outline" asChild>
                                    <a
                                        href={analyticsRoutes.export.url({
                                            query,
                                        })}
                                        download
                                    >
                                        <FileDown /> {t('Export Excel')}
                                    </a>
                                </Button>
                                <Button asChild>
                                    <a
                                        href={analyticsRoutes.pdf.url({
                                            query,
                                        })}
                                        download
                                    >
                                        <FileText /> {t('PDF Report')}
                                    </a>
                                </Button>
                            </>
                        )
                    }
                />

                <div className="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    {/* No ?cycle= means the newest cycle, so it is the blank choice. */}
                    <FilterSelect
                        url={url}
                        filters={{
                            ...filters,
                            cycle:
                                String(filters.cycle) === String(cycles[0]?.id)
                                    ? ''
                                    : filters.cycle,
                        }}
                        name="cycle"
                        label={cycles[0]?.name ?? cycle.name}
                        options={cycles.slice(1)}
                    />
                    {PROFILE_FILTERS.map(([name, label]) => (
                        <FilterSelect
                            key={name}
                            url={url}
                            filters={filters}
                            name={name}
                            label={label}
                            options={(options[name] ?? []).map((value) => ({
                                id: value,
                                name: value,
                            }))}
                        />
                    ))}
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="gender"
                        label="All Genders"
                        options={[
                            { id: 'male', name: t('Male') },
                            { id: 'female', name: t('Female') },
                        ]}
                    />
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="weighting"
                        label="Company-weighted"
                        options={[
                            {
                                id: 'incumbent',
                                name: t('Incumbent-weighted'),
                            },
                        ]}
                    />
                    <Button
                        variant="ghost"
                        onClick={() =>
                            router.get(
                                url.url,
                                { cycle: filters.cycle, view: filters.view },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {t('Clear Filters')}
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2 rounded-xl border border-dashed bg-card p-4 shadow-sm">
                    <span className="me-2 text-sm font-medium">
                        {t('Compare with')}
                    </span>
                    {COMPARE_FILTERS.map(([name, label]) => (
                        <FilterSelect
                            key={name}
                            url={url}
                            filters={filters}
                            name={`vs_${name}`}
                            label={`${t(label)}: ${t('as above')}`}
                            options={[
                                { id: '*', name: t('Whole market') },
                                ...(options[name] ?? []).map((value) => ({
                                    id: value,
                                    name: value,
                                })),
                            ]}
                        />
                    ))}
                    {comparison && (
                        <span className="text-sm text-muted-foreground">
                            {t(':n companies in the comparison', {
                                n: comparison.overview.companies,
                            })}
                        </span>
                    )}
                </div>

                <StatCards
                    stats={[
                        {
                            label: 'Companies',
                            value: report.overview.companies,
                            note: 'Participants in this cut',
                            icon: Building2,
                            tone: 'bg-blue-100 text-blue-600',
                        },
                        {
                            label: 'Job Roles',
                            value: report.overview.roles,
                            note: 'Title and level combinations',
                            icon: Briefcase,
                            tone: 'bg-violet-100 text-violet-600',
                        },
                        {
                            label: 'Employees',
                            value: report.overview.incumbents.toLocaleString(),
                            note: 'Incumbents covered',
                            icon: Users,
                            tone: 'bg-emerald-100 text-emerald-600',
                        },
                        {
                            label: 'Confidentiality',
                            value: `≥ ${cycle.min_companies}`,
                            note: 'Companies needed per figure',
                            icon: ShieldCheck,
                            tone: 'bg-amber-100 text-amber-600',
                        },
                    ]}
                />

                <div className="grid gap-4 rounded-xl border bg-card p-4 shadow-sm md:p-6">
                    <div
                        role="tablist"
                        className="flex flex-wrap gap-1 rounded-lg bg-muted p-1"
                    >
                        {VIEWS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                role="tab"
                                aria-selected={view === key}
                                onClick={() =>
                                    applyFilters(url, filters, { view: key })
                                }
                                className={cn(
                                    'flex-1 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap',
                                    view === key
                                        ? 'bg-card shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {t(label)}
                            </button>
                        ))}
                    </div>
                    {report.overview.companies === 0 ? (
                        <p className="py-10 text-center text-muted-foreground">
                            {t('No companies match these filters.')}
                        </p>
                    ) : (
                        views[view]
                    )}
                </div>
            </div>
        </>
    );
}

/** The compared cut's median for a role and how far the main cut sits from it. */
function CompareCells({
    median,
    other,
    format,
}: {
    median: number | null | undefined;
    other: Comparison['salaries'][string] | undefined;
    format: (value: number | null | undefined) => string;
}) {
    if (!other || other.suppressed || other.median === null) {
        return (
            <td colSpan={2} className="border-b px-2 py-2 align-top">
                <Hidden />
            </td>
        );
    }

    const diff =
        median !== null && median !== undefined
            ? ((median - other.median) / other.median) * 100
            : null;

    return (
        <>
            <Cell className="whitespace-nowrap">{format(other.median)}</Cell>
            <Cell
                className={cn(
                    'font-medium whitespace-nowrap',
                    diff !== null &&
                        (diff >= 0 ? 'text-emerald-600' : 'text-red-600'),
                )}
            >
                {diff === null
                    ? '-'
                    : `${diff > 0 ? '+' : ''}${diff.toFixed(1)}%`}
            </Cell>
        </>
    );
}

function AttritionView({ attrition: a }: { attrition: Report['attrition'] }) {
    const { t } = useTranslation();
    const shares = (values: Record<string, number> | undefined) =>
        Object.entries(values ?? {}).map(([name, percent]) => (
            <tr key={name}>
                <Cell>{name}</Cell>
                <Cell>
                    <Bar percent={percent} />
                </Cell>
            </tr>
        ));

    return (
        <div className="grid gap-8 lg:grid-cols-2">
            <Section title="Key Measures (median)">
                <Table headings={['Measure', 'Value']}>
                    {(
                        [
                            ['Attrition rate %', a.attrition_rate],
                            [
                                'New-hire attrition rate %',
                                a.new_hire_attrition_rate,
                            ],
                            [
                                'Retirements per 100 staff',
                                a.retirements_per_100,
                            ],
                            [
                                'Involuntary terminations per 100 staff',
                                a.terminations_per_100,
                            ],
                            [
                                'Contract non-renewals per 100 staff',
                                a.non_renewals_per_100,
                            ],
                            ['Time to fill (days)', a.time_to_fill_days],
                            [
                                'Companies that retrenched %',
                                a.retrenched_percent,
                            ],
                        ] as const
                    ).map(([label, value]) => (
                        <tr key={label}>
                            <Cell>{t(label)}</Cell>
                            <Cell className="font-semibold">{value}</Cell>
                        </tr>
                    ))}
                </Table>
            </Section>
            {(
                [
                    ['hardest_to_hire', 'Hardest to Hire'],
                    ['hardest_to_retain', 'Hardest to Retain'],
                ] as const
            ).map(([key, title]) => (
                <Section key={key} title={title}>
                    <Table
                        headings={['Job Family', 'Score (3-2-1)', 'Mentions']}
                    >
                        {(a[key] ?? []).map((row) => (
                            <tr key={row.name}>
                                <Cell>{row.name}</Cell>
                                <Cell className="font-semibold">
                                    {row.score}
                                </Cell>
                                <Cell>{row.mentions}</Cell>
                            </tr>
                        ))}
                    </Table>
                </Section>
            ))}
            <Section title="Headcount Plan">
                <Table headings={['Plan', 'Companies']}>
                    {shares(a.headcount_plan)}
                </Table>
            </Section>
            <Section title="Pay a Reason for Leaving">
                <Table headings={['Answer', 'Companies']}>
                    {shares(a.pay_reason_for_leaving)}
                </Table>
            </Section>
            <Section title="Retrenchment">
                <Table headings={['Driver', 'Companies']}>
                    {Object.entries(a.retrenchment_drivers ?? {}).map(
                        ([name, count]) => (
                            <tr key={name}>
                                <Cell>{name}</Cell>
                                <Cell>{count}</Cell>
                            </tr>
                        ),
                    )}
                </Table>
                <Table headings={['Benefits above statutory', 'Companies']}>
                    {shares(a.retrenchment_above_statutory)}
                </Table>
            </Section>
        </div>
    );
}

BenchmarkAnalytics.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Benchmark Survey', href: analyticsRoutes.index() },
        { title: 'Analytics', href: analyticsRoutes.index() },
    ],
};
