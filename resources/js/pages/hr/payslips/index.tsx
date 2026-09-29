import { Head, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import {
    DateRangeFilter,
    FilterSelect,
    StatusTabs,
} from '@/components/table-filters';
import { DateCell } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { SelectField } from '@/components/select-field';
import { dashboard } from '@/routes';
import payslipRoutes from '@/routes/hr/payslips';
import type { Paginated, TableFilters } from '@/types';

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

type Line = { name: string; amount: string };

type Payslip = {
    id: number;
    payslip_number: string;
    basic_salary: string;
    total_earnings: string;
    gross_pay: string;
    total_deductions: string;
    net_pay: string;
    earnings: Line[];
    deductions: Line[];
    status: 'generated' | 'sent' | 'downloaded';
    created_at: string;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | null;
        user: {
            id: number;
            name: string;
            email: string;
            avatar: string | null;
        };
    };
    payroll_run: {
        id: number;
        title: string;
        pay_period_start: string;
        pay_period_end: string;
        pay_date: string;
        status: string;
    };
};

type Option = { id: number; name: string };

export default function Payslips({
    payslips,
    employees,
    payrollRuns,
    years,
    selectedMonth,
    statusCounts,
    filters,
}: {
    payslips: Paginated<Payslip>;
    employees: Option[];
    payrollRuns: Option[];
    years: number[];
    selectedMonth: string;
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const url = payslipRoutes.index();
    const [viewing, setViewing] = useState<Payslip | null>(null);
    const year = selectedMonth.slice(0, 4);

    // Choosing a month leaves a payroll run's own list and starts from page one.
    const pickMonth = (month: string) =>
        router.get(
            url,
            Object.fromEntries(
                Object.entries({
                    ...filters,
                    selected_month: month,
                    payroll_run_id: undefined,
                    page: undefined,
                }).filter(([, v]) => v !== undefined && v !== null && v !== ''),
            ),
            { preserveState: true, preserveScroll: true },
        );

    const columns: Column<Payslip>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (p) => (
                <PersonCell
                    name={p.employee.user.name}
                    detail={p.employee.employee_id}
                    src={p.employee.user.avatar}
                    gender={p.employee.gender}
                />
            ),
        },
        {
            key: 'pay_date',
            label: 'Pay Date',
            sortable: true,
            render: (p) => <DateCell value={p.payroll_run.pay_date} />,
        },
        {
            key: 'net_pay',
            label: 'Net Pay',
            render: (p) => (
                <span className="font-medium whitespace-nowrap tabular-nums">
                    {money(Number(p.net_pay))}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (p) => <StatusBadge status={p.status} />,
        },
        {
            key: 'created_at',
            label: 'Generated On',
            sortable: true,
            render: (p) => <DateCell value={p.created_at} />,
        },
    ];

    const Lines = ({
        title,
        lines,
        total,
        totalLabel,
    }: {
        title: string;
        lines: Line[];
        total: string;
        totalLabel: string;
    }) => (
        <div className="rounded-lg border">
            <div className="border-b bg-muted/60 px-4 py-2 font-medium">
                {t(title)}
            </div>
            <dl className="divide-y text-sm">
                {lines.map((line) => (
                    <div
                        key={line.name}
                        className="flex justify-between gap-4 px-4 py-2"
                    >
                        <dt>{line.name}</dt>
                        <dd>{money(Number(line.amount))}</dd>
                    </div>
                ))}
                <div className="flex justify-between gap-4 px-4 py-2 font-semibold">
                    <dt>{t(totalLabel)}</dt>
                    <dd>{money(Number(total))}</dd>
                </div>
            </dl>
        </div>
    );

    return (
        <>
            <Head title={t('Payslips')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Payslips"
                    description="Browse and download employee payslips."
                />

                <div className="grid grid-cols-4 overflow-hidden rounded-xl border bg-card shadow-sm sm:grid-cols-6 lg:grid-cols-12">
                    {Array.from({ length: 12 }, (_, i) => {
                        const value = `${year}-${String(i + 1).padStart(2, '0')}`;
                        const active = value === selectedMonth;

                        return (
                            <button
                                key={value}
                                type="button"
                                onClick={() => pickMonth(value)}
                                aria-pressed={active}
                                className={cn(
                                    'flex h-14 flex-col items-center justify-center border-e text-sm font-medium last:border-e-0 hover:bg-muted/60',
                                    active &&
                                        'bg-primary text-primary-foreground hover:bg-primary',
                                )}
                            >
                                {t(MONTHS[i])}
                                {active && (
                                    <span className="text-xs font-normal">
                                        {year}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>

                <DataTable
                    data={payslips}
                    columns={columns}
                    filters={filters}
                    url={url}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    toolbar={
                        <>
                            <SelectField
                                aria-label={t('Year')}
                                className="w-auto min-w-32"
                                value={year}
                                onChange={(e) =>
                                    pickMonth(
                                        `${e.target.value}-${selectedMonth.slice(5)}`,
                                    )
                                }
                            >
                                {years.map((y) => (
                                    <option key={y} value={y}>
                                        {y}
                                    </option>
                                ))}
                            </SelectField>
                            {employees.length > 0 && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="employee_id"
                                    label="All Employees"
                                    options={employees}
                                />
                            )}
                        </>
                    }
                    moreFilters={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="payroll_run_id"
                                label="All Payroll Runs"
                                options={payrollRuns}
                            />
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(payslip) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            onClick={() => setViewing(payslip)}
                        >
                            <Eye />
                        </Button>
                    )}
                />
            </div>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {t('Payslip')} {viewing?.payslip_number}
                        </DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <div className="grid gap-4">
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Employee')}
                                    </dt>
                                    <dd className="font-medium">
                                        {viewing.employee.user.name} (
                                        {viewing.employee.employee_id})
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Payroll Run')}
                                    </dt>
                                    <dd className="font-medium">
                                        {viewing.payroll_run.title}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Pay Period')}
                                    </dt>
                                    <dd className="font-medium">
                                        {date(
                                            viewing.payroll_run
                                                .pay_period_start,
                                        )}{' '}
                                        -{' '}
                                        {date(
                                            viewing.payroll_run.pay_period_end,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Pay Date')}
                                    </dt>
                                    <dd className="font-medium">
                                        {date(viewing.payroll_run.pay_date)}
                                    </dd>
                                </div>
                            </dl>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Lines
                                    title="Earnings"
                                    lines={[
                                        {
                                            name: t('Basic Salary'),
                                            amount: viewing.basic_salary,
                                        },
                                        ...viewing.earnings,
                                    ]}
                                    total={viewing.gross_pay}
                                    totalLabel="Gross Pay"
                                />
                                <Lines
                                    title="Deductions"
                                    lines={viewing.deductions}
                                    total={viewing.total_deductions}
                                    totalLabel="Total Deductions"
                                />
                            </div>
                            <div className="flex items-center justify-between rounded-lg bg-primary/10 px-4 py-3 font-semibold">
                                <span>{t('Net Pay')}</span>
                                <span>{money(Number(viewing.net_pay))}</span>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Payslips.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payroll Management', href: payslipRoutes.index() },
        { title: 'Payslips', href: payslipRoutes.index() },
    ],
};
