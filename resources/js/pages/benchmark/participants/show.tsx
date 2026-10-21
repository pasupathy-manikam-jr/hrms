import { Head, router } from '@inertiajs/react';
import {
    Download,
    FileDown,
    Pencil,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    AttritionDialog,
    BenefitsDialog,
    ProfileDialog,
    SalaryRowDialog,
} from '@/components/benchmark-corrections';
import type { Lookups } from '@/components/benchmark-corrections';
import {
    answer,
    IssueActions,
    IssueButton,
    IssuesDialog,
    Typed,
} from '@/components/benchmark-issues';
import type { Issue, TypedAnswers } from '@/components/benchmark-issues';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DetailPage, Fields, TextBlock } from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import participantRoutes from '@/routes/benchmark/participants';
import rowRoutes from '@/routes/benchmark/participants/rows';

type SalaryRow = {
    id: number;
    line: number | null;
    job_family: string;
    job_title: string;
    own_title: string | null;
    job_level: string;
    headcount: number;
    male: number;
    female: number;
    median_salary: string | null;
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
    industry: string | null;
    state: string | null;
    employee_band: string | null;
    revenue_band: string | null;
    ownership_type: string | null;
    locations: number | null;
    listed_status: string | null;
    unionised: string | null;
    authorised_name: string | null;
    authorised_designation: string | null;
    consent_date: string | null;
    consented: boolean | null;
    file_name: string | null;
    edited_at: string | null;
    editor: { id: number; name: string } | null;
    errors: string[] | null;
    warnings: string[] | null;
    raw_answers: TypedAnswers | null;
    created_at: string;
    cycle: { id: number; name: string; min_companies: number };
    benefits: Benefit[];
    attrition: Attrition | null;
};

export default function SurveyParticipantShow({
    participant: p,
    hasFile,
    issues,
    salaryRows,
    benefitLabels,
    lookups,
    jobTitles,
}: {
    participant: Participant;
    hasFile: boolean;
    issues: Issue[];
    salaryRows: SalaryRow[];
    benefitLabels: Record<string, string>;
    lookups: Lookups;
    jobTitles: string[];
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const can = useCan();
    const a = p.attrition ?? {};
    const canEdit = can('upload-benchmark-survey');
    // Which correction dialog is open: a section, a salary row, or a new row.
    const [editing, setEditing] = useState<
        'profile' | 'benefits' | 'attrition' | 'new-row' | SalaryRow | null
    >(null);
    const [deletingRow, setDeletingRow] = useState<SalaryRow | null>(null);
    const close = (open: boolean) => !open && setEditing(null);
    // The findings dialog open for one row or field group ("row:7", "benefit:dental_coverage", …).
    const [showing, setShowing] = useState<{
        title: string;
        targets: (target: string | null) => boolean;
        row?: SalaryRow;
    } | null>(null);
    const typed = p.raw_answers ?? {};
    const issuesFor = (targets: (target: string | null) => boolean) =>
        issues.filter((issue) => targets(issue.target));
    const rowTarget = (row: SalaryRow) => `row:${row.line ?? 0}`;
    const sectionIssues = (targets: (target: string | null) => boolean) => {
        const found = issuesFor(targets);

        return (
            found.length > 0 && (
                <IssueActions
                    issues={found}
                    all={issues}
                    participantId={p.id}
                    canAccept={canEdit}
                />
            )
        );
    };
    const toolbar = (label: string, onClick: () => void, add = false) =>
        canEdit && (
            <div>
                <Button variant="outline" size="sm" onClick={onClick}>
                    {add ? <Plus /> : <Pencil />} {t(label)}
                </Button>
            </div>
        );
    const section = (bar: ReactNode, content: ReactNode) => (
        <div className="grid gap-4">
            {bar}
            {content}
        </div>
    );

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
                            '',
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
                        const median =
                            row.median_salary === null
                                ? null
                                : Number(row.median_salary);
                        const diff =
                            row.market_median !== null && median !== null
                                ? ((median - row.market_median) /
                                      row.market_median) *
                                  100
                                : null;

                        return (
                            <tr key={row.id} className="border-b align-top">
                                <td className="px-2 py-2">
                                    <div className="font-medium">
                                        {row.line !== null && (
                                            <span className="me-1 text-muted-foreground">
                                                #{row.line}
                                            </span>
                                        )}
                                        {row.job_title || (
                                            <span className="text-destructive">
                                                {t('No Standard Job Title')}
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {row.own_title ?? row.job_family}
                                    </div>
                                </td>
                                <td className="px-2 py-2">
                                    {answer(
                                        typed,
                                        `${rowTarget(row)}.job_level`,
                                        row.job_level,
                                    )}
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {typed[`${rowTarget(row)}.headcount`] ? (
                                        <Typed
                                            value={
                                                typed[
                                                    `${rowTarget(row)}.headcount`
                                                ].value
                                            }
                                        />
                                    ) : (
                                        row.headcount
                                    )}
                                    <span className="text-xs text-muted-foreground">
                                        {' '}
                                        ({row.male}M / {row.female}F)
                                    </span>
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {median === null
                                        ? (answer(
                                              typed,
                                              `${rowTarget(row)}.median_salary`,
                                              null,
                                          ) ?? '-')
                                        : money(median)}
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
                                <td className="px-2 py-1 whitespace-nowrap">
                                    <IssueButton
                                        issues={issuesFor(
                                            (target) =>
                                                target === rowTarget(row),
                                        )}
                                        onClick={() =>
                                            setShowing({
                                                title: t('Row :line — :title', {
                                                    line: row.line ?? '',
                                                    title:
                                                        row.job_title ||
                                                        row.own_title ||
                                                        '—',
                                                }),
                                                targets: (target) =>
                                                    target === rowTarget(row),
                                                row,
                                            })
                                        }
                                    />
                                    {canEdit && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Edit')}
                                                onClick={() => setEditing(row)}
                                            >
                                                <SquarePen />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Delete')}
                                                onClick={() =>
                                                    setDeletingRow(row)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </>
                                    )}
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
                            '',
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
                            <td className="px-2 py-1">
                                <IssueButton
                                    issues={issuesFor(
                                        (target) =>
                                            target === `benefit:${b.item}`,
                                    )}
                                    onClick={() =>
                                        setShowing({
                                            title: t(
                                                benefitLabels[b.item] ?? b.item,
                                            ),
                                            targets: (target) =>
                                                target === `benefit:${b.item}`,
                                        })
                                    }
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );

    const joined = (...values: unknown[]) =>
        values.filter(Boolean).join(', ') || null;
    // An Attrition & Hiring answer, or what the company typed when it failed a check.
    const at = (field: string) =>
        answer(typed, `attrition:${field}.${field}`, a[field] as ReactNode);
    const ranked = (group: string) => {
        const answers = [1, 2, 3]
            .map((rank) => at(`${group}_${rank}`))
            .filter((value) => value !== null && value !== undefined);

        return answers.length === 0
            ? null
            : answers.map((value, index) => (
                  <span key={index}>
                      {index > 0 && ', '}
                      {value}
                  </span>
              ));
    };

    return (
        <>
            <Head title={p.company_name} />
            <DetailPage
                title={p.company_name}
                description={t('Submission for the :cycle survey', {
                    cycle: p.cycle.name,
                })}
                back={participantRoutes.index({ query: { cycle: p.cycle.id } })}
                tabs={[
                    ...(issues.length > 0
                        ? [
                              {
                                  label: 'Issues',
                                  heading: p.errors?.length
                                      ? 'Needs fixing before it counts in the analytics'
                                      : 'Warnings',
                                  content: (
                                      <div className="grid gap-4 text-sm">
                                          {can('export-benchmark-survey') && (
                                              <div>
                                                  <Button
                                                      variant="outline"
                                                      asChild
                                                  >
                                                      <a
                                                          href={participantRoutes.issues.url(
                                                              {
                                                                  query: {
                                                                      cycle: p
                                                                          .cycle
                                                                          .id,
                                                                      participant:
                                                                          p.id,
                                                                  },
                                                              },
                                                          )}
                                                          download
                                                      >
                                                          <FileDown />{' '}
                                                          {t(
                                                              'Download Issues (CSV)',
                                                          )}
                                                      </a>
                                                  </Button>
                                              </div>
                                          )}
                                          <IssueActions
                                              issues={issues}
                                              all={issues}
                                              participantId={p.id}
                                              canAccept={canEdit}
                                          />
                                      </div>
                                  ),
                              },
                          ]
                        : []),
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
                                {toolbar(
                                    'Add Row',
                                    () => setEditing('new-row'),
                                    true,
                                )}
                                {sectionIssues(
                                    (target) =>
                                        target === 'salary' ||
                                        (!!target?.startsWith('row:') &&
                                            !salaryRows.some(
                                                (row) =>
                                                    rowTarget(row) === target,
                                            )),
                                )}
                                {salaryTable}
                            </div>
                        ),
                    },
                    {
                        label: 'Benefits',
                        content: section(
                            <>
                                {toolbar('Edit Benefits', () =>
                                    setEditing('benefits'),
                                )}
                                {sectionIssues(
                                    (target) =>
                                        !!target?.startsWith('benefit') &&
                                        !p.benefits.some(
                                            (b) =>
                                                target === `benefit:${b.item}`,
                                        ),
                                )}
                            </>,
                            benefitsTable,
                        ),
                    },
                    {
                        label: 'Attrition & Hiring',
                        content: (
                            <div className="grid gap-6">
                                {toolbar('Edit Attrition & Hiring', () =>
                                    setEditing('attrition'),
                                )}
                                {sectionIssues(
                                    (target) =>
                                        !!target?.startsWith('attrition'),
                                )}
                                <Fields
                                    items={[
                                        [
                                            'Attrition rate %',
                                            at('attrition_rate'),
                                        ],
                                        [
                                            'New-hire attrition rate %',
                                            at('new_hire_attrition_rate'),
                                        ],
                                        ['Retirements', at('retirements')],
                                        [
                                            'Involuntary terminations',
                                            at('involuntary_terminations'),
                                        ],
                                        [
                                            'Contract non-renewals',
                                            at('contract_non_renewals'),
                                        ],
                                        [
                                            'Time to fill (days)',
                                            at('time_to_fill_days'),
                                        ],
                                        [
                                            'Hardest to hire',
                                            ranked('hardest_to_hire'),
                                        ],
                                        [
                                            'Hardest to retain',
                                            ranked('hardest_to_retain'),
                                        ],
                                        [
                                            'Headcount plan',
                                            at('headcount_plan'),
                                        ],
                                        [
                                            'Pay a reason for leaving',
                                            at('pay_reason_for_leaving'),
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
                                            at('retrenchment_driver'),
                                        ],
                                        [
                                            'Above statutory benefits',
                                            at('retrenchment_above_statutory'),
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
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        status={
                                            p.errors?.length
                                                ? 'needs_fixing'
                                                : 'ready'
                                        }
                                    />
                                    <div className="ms-auto flex flex-wrap gap-2">
                                        {hasFile && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={participantRoutes.download.url(
                                                        p.id,
                                                    )}
                                                    download
                                                >
                                                    <Download />{' '}
                                                    {t('Original Workbook')}
                                                </a>
                                            </Button>
                                        )}
                                        {toolbar('Edit Profile & Consent', () =>
                                            setEditing('profile'),
                                        )}
                                    </div>
                                </div>
                                {sectionIssues(
                                    (target) =>
                                        target === 'profile' ||
                                        target === 'consent',
                                )}
                                <Fields
                                    items={[
                                        [
                                            'Industry',
                                            answer(
                                                typed,
                                                'profile.industry',
                                                p.industry,
                                            ),
                                        ],
                                        [
                                            'State (HQ)',
                                            answer(
                                                typed,
                                                'profile.state',
                                                p.state,
                                            ),
                                        ],
                                        [
                                            'Number of employees',
                                            answer(
                                                typed,
                                                'profile.employee_band',
                                                p.employee_band,
                                            ),
                                        ],
                                        [
                                            'Annual revenue',
                                            answer(
                                                typed,
                                                'profile.revenue_band',
                                                p.revenue_band,
                                            ),
                                        ],
                                        [
                                            'Ownership',
                                            answer(
                                                typed,
                                                'profile.ownership_type',
                                                p.ownership_type,
                                            ),
                                        ],
                                        [
                                            'Operating locations',
                                            answer(
                                                typed,
                                                'profile.locations',
                                                p.locations,
                                            ),
                                        ],
                                        [
                                            'Listed status',
                                            answer(
                                                typed,
                                                'profile.listed_status',
                                                p.listed_status,
                                            ),
                                        ],
                                        [
                                            'Unionised workforce',
                                            answer(
                                                typed,
                                                'profile.unionised',
                                                p.unionised,
                                            ),
                                        ],
                                        [
                                            'Authorised person',
                                            joined(
                                                p.authorised_name,
                                                p.authorised_designation,
                                            ),
                                        ],
                                        [
                                            'Consent date',
                                            answer(
                                                typed,
                                                'consent.consent_date',
                                                p.consent_date &&
                                                    date(p.consent_date),
                                            ),
                                        ],
                                        [
                                            'Consent given',
                                            p.consented === null
                                                ? null
                                                : t(p.consented ? 'Yes' : 'No'),
                                        ],
                                        ['Uploaded file', p.file_name],
                                        ['Uploaded at', date(p.created_at)],
                                        [
                                            'Last corrected online',
                                            p.edited_at &&
                                                `${date(p.edited_at)}${p.editor ? ` · ${p.editor.name}` : ''}`,
                                        ],
                                    ]}
                                />
                            </div>
                        ),
                    },
                ]}
            />

            {showing && (
                <IssuesDialog
                    title={showing.title}
                    issues={issuesFor(showing.targets)}
                    all={issues}
                    participantId={p.id}
                    canAccept={canEdit}
                    onClose={() => setShowing(null)}
                    onEdit={
                        canEdit
                            ? () => {
                                  setEditing(showing.row ?? 'benefits');
                                  setShowing(null);
                              }
                            : undefined
                    }
                />
            )}
            {editing === 'profile' && (
                <ProfileDialog
                    open
                    onOpenChange={close}
                    participantId={p.id}
                    answers={p}
                    typed={typed}
                    lookups={lookups}
                />
            )}
            {(editing === 'new-row' || typeof editing === 'object') &&
                editing !== null && (
                    <SalaryRowDialog
                        open
                        onOpenChange={close}
                        participantId={p.id}
                        row={editing === 'new-row' ? null : editing}
                        typed={typed}
                        lookups={lookups}
                        jobTitles={jobTitles}
                    />
                )}
            {editing === 'benefits' && (
                <BenefitsDialog
                    open
                    onOpenChange={close}
                    participantId={p.id}
                    benefits={p.benefits}
                    typed={typed}
                    labels={benefitLabels}
                />
            )}
            {editing === 'attrition' && (
                <AttritionDialog
                    open
                    onOpenChange={close}
                    participantId={p.id}
                    answers={a}
                    typed={typed}
                    lookups={lookups}
                />
            )}
            <ConfirmDialog
                open={deletingRow !== null}
                onOpenChange={(open) => !open && setDeletingRow(null)}
                description="This salary row will be removed from the submission."
                onConfirm={() =>
                    deletingRow &&
                    router.delete(
                        rowRoutes.destroy.url({
                            participant: p.id,
                            salaryRow: deletingRow.id,
                        }),
                        {
                            preserveScroll: true,
                            onSuccess: () => setDeletingRow(null),
                        },
                    )
                }
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
