import { Head, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { applyFilters, FilterSelect } from '@/components/table-filters';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { UserAvatar } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import leaveBalanceRoutes from '@/routes/hr/leave-balances';
import type { Paginated, TableFilters } from '@/types';

type LeaveTypeOption = {
    id: number;
    name: string;
    color: string;
    max_days_per_year: number;
};

type Balance = {
    leave_type_id: number;
    allocated: number;
    carried_forward: number;
    manual_adjustment: number;
    adjustment_reason: string | null;
    used: number;
    pending: number;
    remaining: number;
};

type EmployeeBalances = {
    id: number;
    user: { id: number; name: string; avatar: string | null };
    employee_id: string;
    gender: 'male' | 'female' | null;
    balances: Balance[];
};

type Selected = {
    employee: EmployeeBalances;
    type: LeaveTypeOption;
    balance: Balance;
};

export default function LeaveBalances({
    employeeBalances,
    leaveTypes,
    year,
    yearOptions,
    employees,
    filters,
}: {
    employeeBalances: Paginated<EmployeeBalances>;
    leaveTypes: LeaveTypeOption[];
    year: number;
    yearOptions: number[];
    employees: { id: number; name: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = leaveBalanceRoutes.index();
    const [selected, setSelected] = useState<Selected | null>(null);
    const form = useForm({
        employee_id: 0,
        leave_type_id: 0,
        year,
        carried_forward: 0 as number | string,
        manual_adjustment: 0 as number | string,
        adjustment_reason: '',
    });

    const open = (employee: EmployeeBalances, type: LeaveTypeOption) => {
        const balance = employee.balances.find(
            (b) => b.leave_type_id === type.id,
        );

        if (!balance) {
            return;
        }

        form.clearErrors();
        form.setData({
            employee_id: employee.id,
            leave_type_id: type.id,
            year,
            carried_forward: balance.carried_forward,
            manual_adjustment: balance.manual_adjustment,
            adjustment_reason: balance.adjustment_reason ?? '',
        });
        setSelected({ employee, type, balance });
    };

    const card = (e: EmployeeBalances) => (
        <div className="h-full rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex items-center gap-3 border-b pb-3">
                <UserAvatar
                    name={e.user.name}
                    src={e.user.avatar}
                    gender={e.gender}
                    className="size-12"
                />
                <div className="min-w-0">
                    <div className="truncate text-lg font-semibold">
                        {e.user.name}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {e.employee_id}
                    </div>
                </div>
            </div>
            <table className="mt-2 w-full text-sm">
                <thead>
                    <tr className="border-b text-xs">
                        <th className="py-2 text-start font-medium">
                            {t('Leave Type')}
                        </th>
                        <th className="py-2 text-end font-medium">
                            {t('Total')}
                        </th>
                        <th className="py-2 text-end font-medium text-red-600">
                            {t('Used')}
                        </th>
                        <th className="py-2 text-end font-medium text-emerald-600">
                            {t('Available')}
                        </th>
                        <th className="w-8" />
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {leaveTypes.map((type) => {
                        const balance = e.balances.find(
                            (b) => b.leave_type_id === type.id,
                        );

                        return (
                            balance && (
                                <tr key={type.id}>
                                    <td className="py-2">{type.name}</td>
                                    <td className="py-2 text-end tabular-nums">
                                        {balance.allocated}
                                    </td>
                                    <td className="py-2 text-end text-red-600 tabular-nums">
                                        {balance.used}
                                        {balance.pending > 0 && (
                                            <span
                                                className="ms-1 text-xs text-amber-600"
                                                title={t('Pending approval')}
                                            >
                                                +{balance.pending}
                                            </span>
                                        )}
                                    </td>
                                    <td className="py-2 text-end text-emerald-600 tabular-nums">
                                        {balance.remaining}
                                    </td>
                                    <td className="py-1 text-end">
                                        <button
                                            type="button"
                                            aria-label={t('Details')}
                                            onClick={() => open(e, type)}
                                            className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                        >
                                            <Info className="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            )
                        );
                    })}
                </tbody>
            </table>
        </div>
    );

    const details: [string, number | string][] = selected
        ? [
              ['Max Days Per Year', selected.type.max_days_per_year],
              ['Carried Forward', selected.balance.carried_forward],
              ['Manual Adjustment', selected.balance.manual_adjustment],
              ['Total Allocated', selected.balance.allocated],
              ['Used', selected.balance.used],
              ['Pending', selected.balance.pending],
              ['Remaining', selected.balance.remaining],
          ]
        : [];

    const detailList = (
        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
            {details.map(([label, value]) => (
                <div key={label}>
                    <dt className="text-muted-foreground">{t(label)}</dt>
                    <dd className="font-medium">{value}</dd>
                </div>
            ))}
        </dl>
    );

    return (
        <>
            <Head title={t('Leave Balances')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Leave Balances"
                    description="View and manage leave balances."
                />

                <DataTable
                    cardsOnly
                    data={employeeBalances}
                    columns={[]}
                    renderCard={card}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            {employees.length > 1 && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="employee_id"
                                    label="All Employees"
                                    options={employees}
                                />
                            )}
                            <SelectField
                                aria-label={t('Year')}
                                className="w-auto"
                                value={year}
                                onChange={(e) =>
                                    applyFilters(url, filters, {
                                        year: e.target.value,
                                    })
                                }
                            >
                                {yearOptions.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </SelectField>
                        </>
                    }
                />
            </div>

            {can('adjust-leave-balances') ? (
                <FormDialog
                    open={selected !== null}
                    onOpenChange={(isOpen) => !isOpen && setSelected(null)}
                    title="Leave Balance"
                    description={
                        selected
                            ? `${selected.employee.user.name} · ${selected.type.name} · ${year}`
                            : undefined
                    }
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(leaveBalanceRoutes.adjust(), {
                            preserveScroll: true,
                            onSuccess: () => setSelected(null),
                        });
                    }}
                    processing={form.processing}
                    submitLabel="Save Adjustment"
                >
                    {detailList}
                    {
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="balance-carried">
                                    {t('Carried Forward')}
                                </Label>
                                <Input
                                    id="balance-carried"
                                    type="number"
                                    min={0}
                                    value={form.data.carried_forward}
                                    onChange={(e) =>
                                        form.setData(
                                            'carried_forward',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.carried_forward}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="balance-manual">
                                    {t('Manual Adjustment')}
                                </Label>
                                <Input
                                    id="balance-manual"
                                    type="number"
                                    value={form.data.manual_adjustment}
                                    onChange={(e) =>
                                        form.setData(
                                            'manual_adjustment',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.manual_adjustment}
                                />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="balance-reason">
                                    {t('Adjustment Reason')}
                                </Label>
                                <Input
                                    id="balance-reason"
                                    value={form.data.adjustment_reason}
                                    onChange={(e) =>
                                        form.setData(
                                            'adjustment_reason',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.adjustment_reason}
                                />
                            </div>
                        </div>
                    }
                </FormDialog>
            ) : (
                <Dialog
                    open={selected !== null}
                    onOpenChange={(isOpen) => !isOpen && setSelected(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Leave Balance')}</DialogTitle>
                            <DialogDescription>
                                {selected &&
                                    `${selected.employee.user.name} · ${selected.type.name} · ${year}`}
                            </DialogDescription>
                        </DialogHeader>
                        {detailList}
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

LeaveBalances.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Management', href: leaveBalanceRoutes.index() },
        { title: 'Leave Balances', href: leaveBalanceRoutes.index() },
    ],
};
