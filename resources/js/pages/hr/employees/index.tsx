import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, Eye, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { ExportButton, ImportButton } from '@/components/import-export';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import employeeRoutes from '@/routes/hr/employees';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

const STATUSES = ['active', 'inactive', 'probation', 'terminated'] as const;

const blank = {
    name: '',
    email: '',
    password: '',
    employee_id: '',
    phone: '',
    date_of_birth: '',
    gender: '',
    branch_id: '' as number | '',
    department_id: '' as number | '',
    designation_id: '' as number | '',
    shift_id: '' as number | '',
    reports_to_id: '' as number | '',
    date_of_joining: '',
    employment_type: 'Full-time',
    employee_status: 'active' as (typeof STATUSES)[number],
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    emergency_contact_name: '',
    emergency_contact_relationship: '',
    emergency_contact_number: '',
    bank_name: '',
    account_holder_name: '',
    account_number: '',
    bank_identifier_code: '',
    bank_branch: '',
    tax_payer_id: '',
};

type Field = keyof typeof blank;

type Employee = Omit<
    Record<Field, string | null>,
    | 'branch_id'
    | 'department_id'
    | 'designation_id'
    | 'shift_id'
    | 'reports_to_id'
    | 'password'
> & {
    id: number;
    branch_id: number | null;
    department_id: number | null;
    designation_id: number | null;
    shift_id: number | null;
    created_at: string;
    user: {
        id: number;
        name: string;
        email: string;
        reports_to_id: number | null;
        avatar: string | null;
    };
    branch: Option | null;
    department: Option | null;
    designation: Option | null;
};

export default function Employees({
    employees,
    branches,
    departments,
    designations,
    statusCounts,
    filters,
}: {
    employees: Paginated<Employee>;
    branches: Option[];
    departments: (Option & { branch_id: number })[];
    designations: (Option & { department_id: number })[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [deleting, setDeleting] = useState<Employee | null>(null);
    const url = employeeRoutes.index();

    const branchName = (id: number) =>
        branches.find((b) => b.id === id)?.name ?? '';
    const departmentBranch = (id: number) =>
        departments.find((d) => d.id === id)?.branch_id;
    const departmentLabel = (id: number) => {
        const department = departments.find((d) => d.id === id);

        return department
            ? filters.branch
                ? department.name
                : `${department.name}, ${branchName(department.branch_id)}`
            : '';
    };

    const columns: Column<Employee>[] = [
        {
            key: 'name',
            label: 'Name',
            render: (e) => (
                <PersonCell
                    name={e.user.name}
                    detail={e.user.email}
                    src={e.user.avatar}
                    gender={e.gender as 'male' | 'female' | null}
                />
            ),
        },
        {
            key: 'employee_id',
            label: 'Employee ID',
            sortable: true,
            render: (e) => (
                <span className="rounded-md border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
                    {e.employee_id}
                </span>
            ),
        },
        {
            key: 'department',
            label: 'Department',
            render: (e) => e.department?.name,
        },
        {
            key: 'designation',
            label: 'Designation',
            render: (e) => e.designation?.name,
        },
        {
            key: 'date_of_joining',
            label: 'Joined',
            sortable: true,
            render: (e) =>
                e.date_of_joining && (
                    <span className="flex items-center gap-2 whitespace-nowrap">
                        <CalendarDays className="size-4 text-muted-foreground" />
                        {date(e.date_of_joining)}
                    </span>
                ),
        },
        {
            key: 'employee_status',
            label: 'Employee Status',
            render: (e) =>
                e.employee_status && <StatusBadge status={e.employee_status} />,
        },
    ];

    return (
        <>
            <Head title={t('Employees')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employees"
                    description="Manage employees and their information."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {can('export-employee') && (
                                <ExportButton
                                    href={employeeRoutes.export({
                                        query: filters,
                                    })}
                                />
                            )}
                            {can('import-employee') && (
                                <ImportButton
                                    title="Import Employees from CSV/Excel"
                                    action={employeeRoutes.import()}
                                    templateHref={employeeRoutes.download.template()}
                                    notes={t(
                                        'Ensure that the values entered for Branch, Department, Designation, Shift, Employment Type and Status match the existing records in your system. Leave Password empty to let the employee set one with “Forgot password”.',
                                    )}
                                />
                            )}
                            {can('create-employees') && (
                                <Button asChild>
                                    <Link href={employeeRoutes.create()}>
                                        <Plus /> {t('Add Employee')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={employees}
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
                    renderCard={(e, actions) => (
                        <div className="flex h-full flex-col rounded-xl border bg-card shadow-sm">
                            <div className="flex items-start justify-between gap-2 border-b p-4">
                                <PersonCell
                                    name={e.user.name}
                                    detail={e.user.email}
                                    src={e.user.avatar}
                                    gender={
                                        e.gender as 'male' | 'female' | null
                                    }
                                />
                                {e.employee_status && (
                                    <StatusBadge status={e.employee_status} />
                                )}
                            </div>
                            <dl className="grid flex-1 gap-1 p-4 text-sm">
                                {(
                                    [
                                        ['Employee Id', e.employee_id],
                                        ['Branch', e.branch?.name],
                                        ['Department', e.department?.name],
                                        ['Designation', e.designation?.name],
                                    ] as const
                                ).map(([label, value]) => (
                                    <div key={label} className="flex gap-1">
                                        <dt className="font-medium">
                                            {t(label)}:
                                        </dt>
                                        <dd className="truncate text-muted-foreground">
                                            {value ?? '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <div className="flex items-center justify-between border-t px-4 py-2 text-sm text-muted-foreground">
                                {e.date_of_joining && date(e.date_of_joining)}
                                {actions}
                            </div>
                        </div>
                    )}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="branch"
                                label="All Branches"
                                options={branches}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="department"
                                label="All Departments"
                                options={departments
                                    .filter(
                                        (d) =>
                                            !filters.branch ||
                                            d.branch_id ===
                                                Number(filters.branch),
                                    )
                                    .map((d) => ({
                                        id: d.id,
                                        // Every branch has its own "Human Resources"; name the branch until one is picked.
                                        name: filters.branch
                                            ? d.name
                                            : `${d.name} (${branchName(d.branch_id)})`,
                                    }))}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="designation"
                                label="All Designations"
                                options={designations
                                    .filter(
                                        (d) =>
                                            (!filters.department ||
                                                d.department_id ===
                                                    Number(
                                                        filters.department,
                                                    )) &&
                                            (!filters.branch ||
                                                departmentBranch(
                                                    d.department_id,
                                                ) === Number(filters.branch)),
                                    )
                                    .map((d) => ({
                                        id: d.id,
                                        name: filters.department
                                            ? d.name
                                            : `${d.name} (${departmentLabel(d.department_id)})`,
                                    }))}
                            />
                        </>
                    }
                    actions={(employee) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={employeeRoutes.show(employee.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-employees') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    asChild
                                >
                                    <Link
                                        href={employeeRoutes.edit(employee.id)}
                                    >
                                        <SquarePen />
                                    </Link>
                                </Button>
                            )}
                            {can('delete-employees') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(employee)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This employee and their login account will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(employeeRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Employees.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: employeeRoutes.index() },
    ],
};
