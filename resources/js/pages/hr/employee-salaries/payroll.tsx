import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    Calculator,
    Clock,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { SelectField } from '@/components/select-field';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import salaryRoutes from '@/routes/hr/employee-salaries';

type Line = { name: string; amount: string };

type Run = {
    id: number;
    title: string;
    pay_period_start: string;
    pay_period_end: string;
    status: string;
};

type Attendance = {
    working_days: number;
    full_present_days: number;
    half_days: number;
    holiday_days: number;
    paid_leave_days: number;
    absent_days: number;
    overtime_hours: number;
    present_days: number;
};

export default function PayrollCalculation({
    employeeSalary,
    employeeName,
    payrollRuns,
    selectedRunId,
    payslip,
    attendance,
}: {
    employeeSalary: { id: number };
    employeeName: string;
    payrollRuns: Run[];
    selectedRunId: number;
    payslip: {
        basic_salary: string;
        total_earnings: string;
        gross_pay: string;
        total_deductions: string;
        net_pay: string;
        earnings: Line[];
        deductions: Line[];
    };
    attendance: Attendance;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const run = payrollRuns.find((r) => r.id === selectedRunId);
    const period = run
        ? new Date(
              `${run.pay_period_start.slice(0, 10)}T00:00:00`,
          ).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
        : '';

    const totals = [
        {
            label: 'Basic Salary',
            value: payslip.basic_salary,
            icon: Banknote,
            tone: 'text-foreground',
            tile: 'bg-muted text-muted-foreground',
        },
        {
            label: 'Gross Pay',
            value: payslip.gross_pay,
            icon: TrendingUp,
            tone: 'text-emerald-600',
            tile: 'bg-emerald-50 text-emerald-600',
        },
        {
            label: 'Net Salary',
            value: payslip.net_pay,
            icon: Banknote,
            tone: 'text-primary',
            tile: 'bg-primary/10 text-primary',
        },
    ];

    const tiles: [string, string | number, string][] = [
        ['Working Days', attendance.working_days, 'bg-muted text-foreground'],
        [
            'Full Present',
            attendance.full_present_days,
            'bg-emerald-50 text-emerald-700',
        ],
        ['Half Days', attendance.half_days, 'bg-amber-50 text-amber-700'],
        ['Holidays', attendance.holiday_days, 'bg-purple-50 text-purple-700'],
        ['Paid Leave', attendance.paid_leave_days, 'bg-blue-50 text-blue-700'],
        ['Absent', attendance.absent_days, 'bg-red-50 text-red-700'],
        [
            'Overtime',
            `${attendance.overtime_hours}h`,
            'bg-indigo-50 text-indigo-700',
        ],
    ];

    const lines = (
        title: string,
        icon: typeof TrendingUp,
        rows: Line[],
        totalLabel: string,
        total: string,
        tone: string,
    ) => {
        const Icon = icon;

        return (
            <section className="rounded-xl border bg-card p-6 shadow-sm">
                <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                    <Icon className={cn('size-5', tone)} /> {t(title)}
                </h2>
                <dl className="divide-y">
                    {rows.map((line) => (
                        <div
                            key={line.name}
                            className="flex justify-between gap-4 py-2.5"
                        >
                            <dt>{line.name}</dt>
                            <dd className={cn('tabular-nums', tone)}>
                                {money(Number(line.amount))}
                            </dd>
                        </div>
                    ))}
                    <div className="flex justify-between gap-4 pt-3 font-semibold">
                        <dt>{t(totalLabel)}</dt>
                        <dd className={cn('tabular-nums', tone)}>
                            {money(Number(total))}
                        </dd>
                    </div>
                </dl>
            </section>
        );
    };

    return (
        <>
            <Head title={t('Payroll Calculation')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${t('Payroll Calculation')} — ${employeeName}`}
                    description="Salary breakdown with attendance-based earnings and deductions."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={salaryRoutes.index()}>
                                <ArrowLeft /> {t('Back')}
                            </Link>
                        </Button>
                    }
                />

                <section className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card p-5 shadow-sm">
                    <div className="flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Calculator className="size-5" />
                        </span>
                        <div>
                            <div className="text-lg font-semibold">
                                {employeeName}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {period}
                            </div>
                        </div>
                    </div>
                    <SelectField
                        aria-label={t('Payroll Run')}
                        className="w-auto min-w-64"
                        value={selectedRunId}
                        onChange={(e) =>
                            router.get(
                                salaryRoutes.payroll.url(employeeSalary.id),
                                { payroll_run_id: e.target.value },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {payrollRuns.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.title}
                            </option>
                        ))}
                    </SelectField>
                </section>

                <div className="grid gap-4 sm:grid-cols-3">
                    {totals.map(({ label, value, icon: Icon, tone, tile }) => (
                        <div
                            key={label}
                            className="flex items-start justify-between rounded-xl border bg-card p-5 shadow-sm"
                        >
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {t(label)}
                                </div>
                                <div
                                    className={cn(
                                        'mt-1 text-2xl font-bold tabular-nums',
                                        tone,
                                    )}
                                >
                                    {money(Number(value))}
                                </div>
                            </div>
                            <span
                                className={cn(
                                    'flex size-10 items-center justify-center rounded-lg',
                                    tile,
                                )}
                            >
                                <Icon className="size-5" />
                            </span>
                        </div>
                    ))}
                </div>

                <section className="rounded-xl border bg-card p-6 shadow-sm">
                    <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                        <Clock className="size-5 text-muted-foreground" />{' '}
                        {t('Attendance Summary')}
                    </h2>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                        {tiles.map(([label, value, tone]) => (
                            <div
                                key={label}
                                className={cn(
                                    'rounded-lg p-3 text-center',
                                    tone,
                                )}
                            >
                                <div className="text-2xl font-bold tabular-nums">
                                    {value}
                                </div>
                                <div className="text-xs">{t(label)}</div>
                            </div>
                        ))}
                    </div>
                    <p className="mt-4 rounded-lg bg-muted/60 px-4 py-2 text-sm">
                        <span className="font-medium">
                            {t('Present Days')}:
                        </span>{' '}
                        <span className="text-muted-foreground">
                            {t(
                                'Full Present + Holidays + Paid Leave + (Half Days × 0.5)',
                            )}
                        </span>{' '}
                        ={' '}
                        <span className="font-semibold">
                            {attendance.present_days}
                        </span>
                    </p>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    {lines(
                        'Earnings',
                        TrendingUp,
                        [
                            {
                                name: t('Basic Salary'),
                                amount: payslip.basic_salary,
                            },
                            ...payslip.earnings,
                        ],
                        'Total Earnings',
                        payslip.gross_pay,
                        'text-emerald-600',
                    )}
                    {lines(
                        'Component Deductions',
                        TrendingDown,
                        payslip.deductions,
                        'Total Deductions',
                        payslip.total_deductions,
                        'text-red-600',
                    )}
                </div>
            </div>
        </>
    );
}

PayrollCalculation.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payroll Management', href: salaryRoutes.index() },
        { title: 'Employee Salaries', href: salaryRoutes.index() },
        { title: 'Payroll Calculation', href: salaryRoutes.index() },
    ],
};
