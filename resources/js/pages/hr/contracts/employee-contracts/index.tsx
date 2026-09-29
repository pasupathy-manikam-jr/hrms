import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Plus, RefreshCw, SquarePen, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ActionMenu } from '@/components/action-menu';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell, IdBadge } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import contractRoutes from '@/routes/hr/contracts/employee-contracts';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Contract = {
    id: number;
    contract_number: string;
    employee_id: number;
    contract_type_id: number | null;
    contract_template_id: number | null;
    start_date: string;
    end_date: string | null;
    basic_salary: string;
    terms_conditions: string | null;
    status: string;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | null;
        user: Option & { email: string; avatar: string | null };
    };
    contract_type: Option | null;
    contract_template: Option | null;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const pretty = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const blank = {
    contract_number: '',
    employee_id: '' as number | string,
    contract_type_id: '' as number | string,
    contract_template_id: '' as number | string,
    start_date: '',
    end_date: '',
    basic_salary: '',
    terms_conditions: '',
    status: 'draft',
};

export default function EmployeeContracts({
    employeeContracts,
    statusCounts,
    stats,
    employees,
    contractTypes,
    contractTemplates,
    statuses,
    filters,
}: {
    employeeContracts: Paginated<Contract>;
    statusCounts: Record<string, number>;
    stats: Record<string, number>;
    employees: (Option & { employee_id: string })[];
    contractTypes: Option[];
    contractTemplates: (Option & { contract_type_id: number | null })[];
    statuses: string[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const can = useCan();
    const url = contractRoutes.index();
    const [editing, setEditing] = useState<Contract | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [viewing, setViewing] = useState<Contract | null>(null);
    const [deleting, setDeleting] = useState<Contract | null>(null);
    const [changing, setChanging] = useState<Contract | null>(null);
    const statusForm = useForm({ status: '' });
    const form = useForm(blank);

    const openForm = (contract: Contract | null) => {
        setEditing(contract);
        form.clearErrors();
        form.setData(
            contract
                ? {
                      contract_number: contract.contract_number,
                      employee_id: contract.employee_id,
                      contract_type_id: contract.contract_type_id ?? '',
                      contract_template_id: contract.contract_template_id ?? '',
                      start_date: contract.start_date,
                      end_date: contract.end_date ?? '',
                      basic_salary: contract.basic_salary,
                      terms_conditions: contract.terms_conditions ?? '',
                      // "expired" is derived from end_date; the stored status is active.
                      status:
                          contract.status === 'expired'
                              ? 'active'
                              : contract.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Contract>[] = [
        {
            key: 'contract_number',
            label: 'Contract Number',
            sortable: true,
            render: (row) => <IdBadge>{row.contract_number}</IdBadge>,
        },
        {
            key: 'contract_type',
            label: 'Contract Name',
            render: (row) => row.contract_type?.name ?? '—',
        },
        {
            key: 'employee',
            label: 'Assigned to',
            render: (row) => (
                <PersonCell
                    name={row.employee.user.name}
                    detail={row.employee.user.email}
                    src={row.employee.user.avatar}
                    gender={row.employee.gender}
                />
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'start_date',
            label: 'Contract Duration',
            sortable: true,
            render: (row) => (
                <div className="grid gap-1">
                    <DateCell value={row.start_date} />
                    <DateCell value={row.end_date} />
                </div>
            ),
        },
        {
            key: 'basic_salary',
            label: 'Contract Amount',
            sortable: true,
            render: (row) => (
                <span className="whitespace-nowrap">
                    {money(Number(row.basic_salary))}
                </span>
            ),
        },
    ];

    const field = (
        name: keyof typeof blank,
        label: string,
        input: ReactNode,
        wide = false,
    ) => (
        <div className={wide ? 'grid gap-2 sm:col-span-2' : 'grid gap-2'}>
            <Label htmlFor={`contract-${name}`}>{t(label)}</Label>
            {input}
            <InputError message={form.errors[name]} />
        </div>
    );

    const select = (
        name: keyof typeof blank,
        placeholder: string,
        options: { id: number | string; name: string }[],
        required = true,
    ) => (
        <SelectField
            id={`contract-${name}`}
            required={required}
            value={form.data[name]}
            onChange={(e) => form.setData(name, e.target.value)}
        >
            <option value="">{t(placeholder)}</option>
            {options.map((option) => (
                <option key={option.id} value={option.id}>
                    {option.name}
                </option>
            ))}
        </SelectField>
    );

    return (
        <>
            <Head title={t('Employee Contracts')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employee Contracts"
                    description="Employment contracts, their terms and validity."
                    action={
                        can('create-employee-contracts') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Contract')}
                            </Button>
                        )
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    {(
                        [
                            ['active', 'Active Contracts'],
                            ['near_expiry', 'Expiring in 30 Days'],
                            ['draft', 'Drafts'],
                        ] as const
                    ).map(([key, label]) => (
                        <div
                            key={key}
                            className="rounded-xl border bg-card p-4 shadow-sm"
                        >
                            <div className="text-sm text-muted-foreground">
                                {t(label)}
                            </div>
                            <div className="text-2xl font-bold">
                                {stats[key]}
                            </div>
                        </div>
                    ))}
                </div>

                <DataTable
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    data={employeeContracts}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            {employees.length > 0 && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="employee_id"
                                    label="All Employees"
                                    options={employees}
                                />
                            )}
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="contract_type_id"
                                label="All Contract Types"
                                options={contractTypes}
                            />
                        </>
                    }
                    actions={(contract) => (
                        <ActionMenu
                            items={[
                                {
                                    label: 'View',
                                    icon: Eye,
                                    onSelect: () => setViewing(contract),
                                },
                                {
                                    label: 'Edit',
                                    icon: SquarePen,
                                    onSelect: () => openForm(contract),
                                    hidden: !can('edit-employee-contracts'),
                                },
                                {
                                    label: 'Update Status',
                                    icon: RefreshCw,
                                    onSelect: () => {
                                        statusForm.clearErrors();
                                        statusForm.setData(
                                            'status',
                                            contract.status,
                                        );
                                        setChanging(contract);
                                    },
                                    hidden: !can('edit-employee-contracts'),
                                },
                                {
                                    label: 'Delete',
                                    icon: Trash2,
                                    destructive: true,
                                    onSelect: () => setDeleting(contract),
                                    hidden: !can('delete-employee-contracts'),
                                },
                            ]}
                        />
                    )}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Contract' : 'Add Contract'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? contractRoutes.update(editing.id)
                            : contractRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {field(
                        'employee_id',
                        'Employee',
                        select(
                            'employee_id',
                            'Select employee',
                            employees.map((e) => ({
                                id: e.id,
                                name: `${e.name} (${e.employee_id})`,
                            })),
                        ),
                    )}
                    {field(
                        'contract_number',
                        'Contract Number',
                        <Input
                            id="contract-contract_number"
                            placeholder={t('Generated automatically')}
                            value={form.data.contract_number}
                            onChange={(e) =>
                                form.setData('contract_number', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'contract_type_id',
                        'Contract Type',
                        select(
                            'contract_type_id',
                            'Select contract type',
                            contractTypes,
                        ),
                    )}
                    {field(
                        'contract_template_id',
                        'Template',
                        select(
                            'contract_template_id',
                            'None',
                            contractTemplates.filter(
                                (template) =>
                                    !form.data.contract_type_id ||
                                    template.contract_type_id === null ||
                                    template.contract_type_id ===
                                        Number(form.data.contract_type_id),
                            ),
                            false,
                        ),
                    )}
                    {field(
                        'start_date',
                        'Start Date',
                        <Input
                            id="contract-start_date"
                            type="date"
                            required
                            value={form.data.start_date}
                            onChange={(e) =>
                                form.setData('start_date', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'end_date',
                        'End Date',
                        <Input
                            id="contract-end_date"
                            type="date"
                            value={form.data.end_date}
                            onChange={(e) =>
                                form.setData('end_date', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'basic_salary',
                        'Salary',
                        <Input
                            id="contract-basic_salary"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            value={form.data.basic_salary}
                            onChange={(e) =>
                                form.setData('basic_salary', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'status',
                        'Status',
                        select(
                            'status',
                            'Select status',
                            statuses.map((status) => ({
                                id: status,
                                name: t(pretty(status)),
                            })),
                        ),
                    )}
                    {field(
                        'terms_conditions',
                        'Terms & Conditions',
                        <textarea
                            id="contract-terms_conditions"
                            rows={4}
                            className={textareaClass}
                            value={form.data.terms_conditions}
                            onChange={(e) =>
                                form.setData('terms_conditions', e.target.value)
                            }
                        />,
                        true,
                    )}
                    <p className="text-xs text-muted-foreground sm:col-span-2">
                        {t(
                            'Active contracts show as Expired automatically once their end date has passed.',
                        )}
                    </p>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{viewing?.contract_number}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid gap-4 text-sm sm:grid-cols-2">
                            {(
                                [
                                    ['Employee', viewing.employee.user.name],
                                    [
                                        'Contract Type',
                                        viewing.contract_type?.name ?? '—',
                                    ],
                                    [
                                        'Template',
                                        viewing.contract_template?.name ?? '—',
                                    ],
                                    [
                                        'Status',
                                        <StatusBadge
                                            key="status"
                                            status={viewing.status}
                                        />,
                                    ],
                                    ['Start Date', date(viewing.start_date)],
                                    [
                                        'End Date',
                                        viewing.end_date
                                            ? date(viewing.end_date)
                                            : '—',
                                    ],
                                    [
                                        'Salary',
                                        money(Number(viewing.basic_salary)),
                                    ],
                                ] as [string, ReactNode][]
                            ).map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium">{value}</dd>
                                </div>
                            ))}
                            <div className="sm:col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Terms & Conditions')}
                                </dt>
                                <dd className="whitespace-pre-wrap">
                                    {viewing.terms_conditions ?? '—'}
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <FormDialog
                open={changing !== null}
                onOpenChange={(open) => !open && setChanging(null)}
                title="Update Contract Status"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (changing) {
                        statusForm.submit(
                            contractRoutes.changeStatus(changing.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setChanging(null),
                            },
                        );
                    }
                }}
                processing={statusForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="contract-status-change">
                        {t('Status')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="contract-status-change"
                        required
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData('status', e.target.value)
                        }
                    >
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {t(pretty(status))}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={statusForm.errors.status} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This contract will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(contractRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

EmployeeContracts.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'Employee Contracts', href: contractRoutes.index() },
    ],
};
