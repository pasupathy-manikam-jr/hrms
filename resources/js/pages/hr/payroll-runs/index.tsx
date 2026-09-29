import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Eye,
    FileText,
    Play,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ExportButton, ImportButton } from '@/components/import-export';
import { DateCell, IdBadge } from '@/components/table-cells';
import { DateRangeFilter, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import payrollRoutes from '@/routes/hr/payroll-runs';
import payslipRoutes from '@/routes/hr/payslips';
import type { Paginated, TableFilters } from '@/types';

type PayrollRun = {
    id: number;
    title: string;
    payroll_frequency: 'weekly' | 'biweekly' | 'monthly';
    pay_period_start: string;
    pay_period_end: string;
    pay_date: string;
    total_gross_pay: string;
    total_deductions: string;
    total_net_pay: string;
    employee_count: number;
    status: 'draft' | 'processing' | 'completed' | 'cancelled';
    notes: string | null;
};

const FREQUENCIES = [
    ['weekly', 'Weekly'],
    ['biweekly', 'Bi-weekly'],
    ['monthly', 'Monthly'],
] as const;

const blank = {
    title: '',
    payroll_frequency: 'monthly' as PayrollRun['payroll_frequency'],
    pay_period_start: '',
    pay_period_end: '',
    pay_date: '',
    notes: '',
};

const DATE_FIELDS = [
    ['pay_period_start', 'Pay Period Start'],
    ['pay_period_end', 'Pay Period End'],
    ['pay_date', 'Pay Date'],
] as const;

type Confirming = {
    run: PayrollRun;
    action: 'process' | 'complete' | 'delete';
};

const CONFIRM_TEXT = {
    process:
        'Payslips will be (re)generated for every active employee with a salary.',
    complete:
        'Completing this payroll run locks it: it can no longer be processed, edited or deleted.',
    delete: 'This payroll run and its payslips will be permanently deleted.',
};

export default function PayrollRuns({
    payrollRuns,
    statusCounts,
    filters,
}: {
    payrollRuns: Paginated<PayrollRun>;
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const url = payrollRoutes.index();
    const [editing, setEditing] = useState<PayrollRun | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [confirming, setConfirming] = useState<Confirming | null>(null);
    const form = useForm(blank);

    const openForm = (run: PayrollRun | null) => {
        setEditing(run);
        form.clearErrors();
        form.setData(run ? { ...run, notes: run.notes ?? '' } : blank);
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? payrollRoutes.update(editing.id) : payrollRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const confirm = () => {
        if (!confirming) {
            return;
        }

        const { run, action } = confirming;
        const options = {
            preserveScroll: true,
            onSuccess: () => setConfirming(null),
        };

        if (action === 'delete') {
            router.delete(payrollRoutes.destroy(run.id), options);
        } else {
            router.post(payrollRoutes[action](run.id), {}, options);
        }
    };

    const locked = (run: PayrollRun) =>
        run.status === 'completed' || run.status === 'cancelled';

    const columns: Column<PayrollRun>[] = [
        {
            key: 'title',
            label: 'Title',
            sortable: true,
            render: (r) => <div className="font-medium">{r.title}</div>,
        },
        {
            key: 'payroll_frequency',
            label: 'Frequency',
            render: (r) => (
                <IdBadge>
                    {t(
                        FREQUENCIES.find(
                            ([value]) => value === r.payroll_frequency,
                        )?.[1] ?? r.payroll_frequency,
                    )}
                </IdBadge>
            ),
        },
        {
            key: 'pay_period_start',
            label: 'Pay Period',
            sortable: true,
            render: (r) => (
                <div className="grid gap-1">
                    <DateCell value={r.pay_period_start} />
                    <DateCell value={r.pay_period_end} />
                </div>
            ),
        },
        {
            key: 'pay_date',
            label: 'Pay Date',
            sortable: true,
            render: (r) => <DateCell value={r.pay_date} />,
        },
        {
            key: 'employee_count',
            label: 'Employees',
            render: (r) => r.employee_count,
        },
        {
            key: 'total_gross_pay',
            label: 'Gross Pay',
            render: (r) => (
                <span className="whitespace-nowrap text-emerald-600">
                    {money(Number(r.total_gross_pay))}
                </span>
            ),
        },
        {
            key: 'total_net_pay',
            label: 'Net Pay',
            sortable: true,
            render: (r) => (
                <span className="font-medium whitespace-nowrap text-blue-600">
                    {money(Number(r.total_net_pay))}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (r) => <StatusBadge status={r.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Payroll Runs')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Payroll Runs"
                    description="Process payroll for a pay period and generate payslips."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {can('export-payroll-runs') && (
                                <ExportButton
                                    href={payrollRoutes.export({
                                        query: filters,
                                    })}
                                />
                            )}
                            {can('import-payroll-runs') && (
                                <ImportButton
                                    title="Import Payroll Runs from CSV/Excel"
                                    action={payrollRoutes.import()}
                                    templateHref={payrollRoutes.download.template()}
                                    notes={t(
                                        'Payroll Frequency must be weekly, biweekly or monthly. Dates use the YYYY-MM-DD format. Imported runs are created as drafts; process them from the list.',
                                    )}
                                />
                            )}
                            {can('create-payroll-runs') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Add Payroll Run')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={payrollRuns}
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
                    toolbar={<DateRangeFilter url={url} filters={filters} />}
                    actions={(run) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={payrollRoutes.show(run.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {run.employee_count > 0 &&
                                can('manage-payslips') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('View Payslips')}
                                        title={t('View Payslips')}
                                        asChild
                                    >
                                        <Link
                                            href={payslipRoutes.index({
                                                query: {
                                                    payroll_run_id: run.id,
                                                },
                                            })}
                                        >
                                            <FileText />
                                        </Link>
                                    </Button>
                                )}
                            {!locked(run) && can('process-payroll-runs') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Process')}
                                        title={t('Process')}
                                        onClick={() =>
                                            setConfirming({
                                                run,
                                                action: 'process',
                                            })
                                        }
                                    >
                                        <Play />
                                    </Button>
                                    {run.employee_count > 0 && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Complete')}
                                            title={t('Complete')}
                                            onClick={() =>
                                                setConfirming({
                                                    run,
                                                    action: 'complete',
                                                })
                                            }
                                        >
                                            <CheckCircle2 className="text-emerald-600" />
                                        </Button>
                                    )}
                                </>
                            )}
                            {!locked(run) && can('edit-payroll-runs') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(run)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {!locked(run) && can('delete-payroll-runs') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() =>
                                        setConfirming({ run, action: 'delete' })
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Payroll Run' : 'Add Payroll Run'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="run-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="run-title"
                            required
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="run-frequency">
                            {t('Payroll Frequency')}
                        </Label>
                        <SelectField
                            id="run-frequency"
                            value={form.data.payroll_frequency}
                            onChange={(e) =>
                                form.setData(
                                    'payroll_frequency',
                                    e.target
                                        .value as PayrollRun['payroll_frequency'],
                                )
                            }
                        >
                            {FREQUENCIES.map(([value, label]) => (
                                <option key={value} value={value}>
                                    {t(label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.payroll_frequency} />
                    </div>
                    {DATE_FIELDS.map(([key, label]) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={`run-${key}`}>
                                {t(label)}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id={`run-${key}`}
                                type="date"
                                required
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="run-notes">{t('Notes')}</Label>
                        <Input
                            id="run-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(open) => !open && setConfirming(null)}
                description={confirming ? CONFIRM_TEXT[confirming.action] : ''}
                confirmLabel={
                    confirming?.action === 'process'
                        ? 'Process'
                        : confirming?.action === 'complete'
                          ? 'Complete'
                          : 'Delete'
                }
                onConfirm={confirm}
            />
        </>
    );
}

PayrollRuns.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payroll Management', href: payrollRoutes.index() },
        { title: 'Payroll Runs', href: payrollRoutes.index() },
    ],
};
