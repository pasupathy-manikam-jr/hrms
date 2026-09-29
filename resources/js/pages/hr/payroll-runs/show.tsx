import { Head, Link, router } from '@inertiajs/react';
import {
    Banknote,
    CalendarDays,
    CheckCircle2,
    FileText,
    Play,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    DetailPage,
    Fields,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { PersonCell } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import payrollRoutes from '@/routes/hr/payroll-runs';
import payslipRoutes from '@/routes/hr/payslips';

type PayrollRun = {
    id: number;
    title: string;
    payroll_frequency: string;
    pay_period_start: string;
    pay_period_end: string;
    pay_date: string;
    total_gross_pay: string;
    total_deductions: string;
    total_net_pay: string;
    employee_count: number;
    status: string;
    notes: string | null;
};

type Payslip = {
    id: number;
    payslip_number: string | null;
    basic_salary: string;
    total_earnings: string;
    gross_pay: string;
    total_deductions: string;
    net_pay: string;
    status: string;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: { name: string; email: string; avatar: string | null };
        designation: { id: number; name: string } | null;
    };
};

const FREQUENCIES: Record<string, string> = {
    weekly: 'Weekly',
    biweekly: 'Bi-weekly',
    monthly: 'Monthly',
};

const CONFIRM_TEXT = {
    process:
        'Payslips will be (re)generated for every active employee with a salary.',
    complete:
        'Completing this payroll run locks it: it can no longer be processed, edited or deleted.',
};

export default function PayrollRunShow({
    payrollRun: run,
    payslips,
}: {
    payrollRun: PayrollRun;
    payslips: Payslip[];
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const can = useCan();
    const [confirming, setConfirming] = useState<'process' | 'complete' | null>(
        null,
    );
    const locked = run.status === 'completed' || run.status === 'cancelled';
    const period = `${date(run.pay_period_start)} - ${date(run.pay_period_end)}`;

    const totals: [string, string, string][] = [
        ['Gross Pay', run.total_gross_pay, ''],
        ['Deductions', run.total_deductions, 'text-destructive'],
        ['Net Pay', run.total_net_pay, 'text-emerald-600'],
    ];

    return (
        <>
            <Head title={run.title} />
            <DetailPage
                title={run.title}
                description="View the payroll run's totals and payslips."
                back={payrollRoutes.index()}
                summary={
                    <>
                        <Summary
                            media={<SummaryIcon icon={Wallet} />}
                            title={run.title}
                            subtitle={t(
                                FREQUENCIES[run.payroll_frequency] ??
                                    run.payroll_frequency,
                            )}
                            status={run.status}
                            facts={[
                                [CalendarDays, period],
                                [
                                    Banknote,
                                    `${t('Pay Date')}: ${date(run.pay_date)}`,
                                ],
                                [
                                    Users,
                                    t(':count employees', {
                                        count: run.employee_count,
                                    }),
                                ],
                            ]}
                        />
                        <dl className="mt-5 grid w-full gap-2 border-t pt-5 text-start text-sm">
                            {totals.map(([label, value, tone]) => (
                                <div
                                    key={label}
                                    className="flex justify-between gap-4"
                                >
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className={`font-semibold ${tone}`}>
                                        {money(Number(value))}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <div className="mt-5 grid w-full gap-2">
                            {!locked && can('process-payroll-runs') && (
                                <>
                                    <Button
                                        onClick={() => setConfirming('process')}
                                    >
                                        <Play /> {t('Process')}
                                    </Button>
                                    {run.employee_count > 0 && (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setConfirming('complete')
                                            }
                                        >
                                            <CheckCircle2 className="text-emerald-600" />{' '}
                                            {t('Complete')}
                                        </Button>
                                    )}
                                </>
                            )}
                            {payslips.length > 0 && can('manage-payslips') && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={payslipRoutes.index({
                                            query: { payroll_run_id: run.id },
                                        })}
                                    >
                                        <FileText /> {t('View Payslips')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </>
                }
                tabs={[
                    {
                        label: 'Overview',
                        heading: 'Payroll Run Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        ['Title', run.title],
                                        [
                                            'Payroll Frequency',
                                            t(
                                                FREQUENCIES[
                                                    run.payroll_frequency
                                                ] ?? run.payroll_frequency,
                                            ),
                                        ],
                                        [
                                            'Pay Period Start',
                                            <DateCell
                                                key="s"
                                                value={run.pay_period_start}
                                            />,
                                        ],
                                        [
                                            'Pay Period End',
                                            <DateCell
                                                key="e"
                                                value={run.pay_period_end}
                                            />,
                                        ],
                                        [
                                            'Pay Date',
                                            <DateCell
                                                key="p"
                                                value={run.pay_date}
                                            />,
                                        ],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="st"
                                                status={run.status}
                                            />,
                                        ],
                                        ['Employees', run.employee_count],
                                        [
                                            'Total Gross Pay',
                                            money(Number(run.total_gross_pay)),
                                        ],
                                        [
                                            'Total Deductions',
                                            money(Number(run.total_deductions)),
                                        ],
                                        [
                                            'Total Net Pay',
                                            money(Number(run.total_net_pay)),
                                        ],
                                    ]}
                                />
                                <TextBlock label="Notes" value={run.notes} />
                            </div>
                        ),
                    },
                    {
                        label: 'Payslips',
                        content:
                            payslips.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'No payslips yet. Process the payroll run to generate them.',
                                    )}
                                </p>
                            ) : (
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="p-3 text-start font-medium">
                                                    {t('Employee')}
                                                </th>
                                                <th className="p-3 text-start font-medium">
                                                    {t('Payslip')}
                                                </th>
                                                <th className="p-3 text-end font-medium">
                                                    {t('Gross Pay')}
                                                </th>
                                                <th className="p-3 text-end font-medium">
                                                    {t('Deductions')}
                                                </th>
                                                <th className="p-3 text-end font-medium">
                                                    {t('Net Pay')}
                                                </th>
                                                <th className="p-3 text-start font-medium">
                                                    {t('Status')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {payslips.map((p) => (
                                                <tr key={p.id}>
                                                    <td className="p-3">
                                                        <PersonCell
                                                            name={
                                                                p.employee.user
                                                                    .name
                                                            }
                                                            detail={
                                                                p.employee
                                                                    .designation
                                                                    ?.name ??
                                                                p.employee.user
                                                                    .email
                                                            }
                                                            src={
                                                                p.employee.user
                                                                    .avatar
                                                            }
                                                            gender={
                                                                p.employee
                                                                    .gender
                                                            }
                                                        />
                                                    </td>
                                                    <td className="p-3">
                                                        {p.payslip_number && (
                                                            <IdBadge>
                                                                {
                                                                    p.payslip_number
                                                                }
                                                            </IdBadge>
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-end whitespace-nowrap">
                                                        {money(
                                                            Number(p.gross_pay),
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-end whitespace-nowrap text-destructive">
                                                        {money(
                                                            Number(
                                                                p.total_deductions,
                                                            ),
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-end font-semibold whitespace-nowrap">
                                                        {money(
                                                            Number(p.net_pay),
                                                        )}
                                                    </td>
                                                    <td className="p-3">
                                                        <StatusBadge
                                                            status={p.status}
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ),
                    },
                ]}
            />

            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(open) => !open && setConfirming(null)}
                description={confirming ? CONFIRM_TEXT[confirming] : ''}
                confirmLabel={confirming === 'process' ? 'Process' : 'Complete'}
                onConfirm={() =>
                    confirming &&
                    router.post(
                        payrollRoutes[confirming](run.id),
                        {},
                        {
                            preserveScroll: true,
                            onSuccess: () => setConfirming(null),
                        },
                    )
                }
            />
        </>
    );
}

PayrollRunShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payroll Management', href: payrollRoutes.index() },
        { title: 'Payroll Runs', href: payrollRoutes.index() },
        { title: 'Payroll Run Details', href: payrollRoutes.index() },
    ],
};
