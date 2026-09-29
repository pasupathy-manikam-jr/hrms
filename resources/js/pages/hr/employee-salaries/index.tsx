import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChartColumn,
    Eye,
    Lock,
    LockOpen,
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
import { ViewDialog } from '@/components/view-dialog';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { DateCell } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import salaryRoutes from '@/routes/hr/employee-salaries';
import type { Paginated, TableFilters } from '@/types';

type Component = {
    id: number;
    name: string;
    type: 'earning' | 'deduction';
    calculation_type: 'fixed' | 'percentage';
    default_amount: string;
    percentage_of_basic: string | null;
};

type EmployeeSalary = {
    id: number;
    employee_id: number;
    basic_salary: string;
    is_active: boolean;
    notes: string | null;
    components: Component[];
    gross_pay: string;
    total_deductions: string;
    net_pay: string;
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
};

const blank = {
    employee_id: '' as number | '',
    basic_salary: '',
    component_ids: [] as number[],
    is_active: true,
    notes: '',
};

export default function EmployeeSalaries({
    employeeSalaries,
    employees,
    salaryComponents,
    statusCounts,
    filters,
}: {
    employeeSalaries: Paginated<EmployeeSalary>;
    employees: { id: number; name: string; employee_id: string }[];
    salaryComponents: Component[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const can = useCan();
    const url = salaryRoutes.index();
    const [editing, setEditing] = useState<EmployeeSalary | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<EmployeeSalary | null>(null);
    const [viewing, setViewing] = useState<EmployeeSalary | null>(null);
    const form = useForm(blank);

    const openForm = (salary: EmployeeSalary | null) => {
        setEditing(salary);
        form.clearErrors();
        form.setData(
            salary
                ? {
                      employee_id: salary.employee_id,
                      basic_salary: salary.basic_salary,
                      component_ids: salary.components.map((c) => c.id),
                      is_active: salary.is_active,
                      notes: salary.notes ?? '',
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? salaryRoutes.update(editing.id) : salaryRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const toggleComponent = (id: number, checked: boolean) =>
        form.setData(
            'component_ids',
            checked
                ? [...form.data.component_ids, id]
                : form.data.component_ids.filter((c) => c !== id),
        );

    const columns: Column<EmployeeSalary>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (s) => (
                <PersonCell
                    name={s.employee.user.name}
                    detail={s.employee.employee_id}
                    src={s.employee.user.avatar}
                    gender={s.employee.gender}
                />
            ),
        },
        {
            key: 'basic_salary',
            label: 'Basic Salary',
            sortable: true,
            render: (s) => (
                <span className="font-medium text-emerald-600">
                    {money(Number(s.basic_salary))}
                </span>
            ),
        },
        {
            key: 'is_active',
            label: 'Status',
            render: (s) => (
                <StatusBadge status={s.is_active ? 'active' : 'inactive'} />
            ),
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (s) => <DateCell value={s.created_at} />,
        },
    ];

    const canEdit = can('edit-employee-salaries');
    const canDelete = can('delete-employee-salaries');

    return (
        <>
            <Head title={t('Employee Salaries')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employee Salaries"
                    description="Manage basic salaries and the salary components assigned to each employee."
                    action={
                        can('create-employee-salaries') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Employee Salary')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={employeeSalaries}
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
                        employees.length > 0 && (
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="employee_id"
                                label="All Employees"
                                options={employees}
                            />
                        )
                    }
                    actions={(salary) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(salary)}
                            >
                                <Eye />
                            </Button>
                            {canEdit && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(salary)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            salary.is_active
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            salary.is_active
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                salaryRoutes.toggleStatus(
                                                    salary.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {salary.is_active ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Payroll Calculation')}
                                title={t('Payroll Calculation')}
                                asChild
                            >
                                <Link href={salaryRoutes.payroll(salary.id)}>
                                    <ChartColumn />
                                </Link>
                            </Button>
                            {canDelete && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(salary)}
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
                title={editing ? 'Edit Employee Salary' : 'Add Employee Salary'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="salary-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="salary-employee"
                            required
                            value={form.data.employee_id}
                            onChange={(e) =>
                                form.setData(
                                    'employee_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">{t('Select Employee')}</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name} ({employee.employee_id})
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.employee_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="salary-basic">
                            {t('Basic Salary')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="salary-basic"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            value={form.data.basic_salary}
                            onChange={(e) =>
                                form.setData('basic_salary', e.target.value)
                            }
                        />
                        <InputError message={form.errors.basic_salary} />
                    </div>
                    <fieldset className="grid gap-2 sm:col-span-2">
                        <legend className="mb-2 text-sm font-medium">
                            {t('Salary Components')}
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {salaryComponents.map((component) => (
                                <label
                                    key={component.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.component_ids.includes(
                                            component.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleComponent(
                                                component.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <span>
                                        {component.name}
                                        <span className="ms-1 text-muted-foreground">
                                            (
                                            {component.calculation_type ===
                                            'percentage'
                                                ? `${Number(component.percentage_of_basic)}%`
                                                : money(
                                                      Number(
                                                          component.default_amount,
                                                      ),
                                                  )}
                                            ,{' '}
                                            {t(
                                                component.type === 'earning'
                                                    ? 'Earning'
                                                    : 'Deduction',
                                            )}
                                            )
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.component_ids} />
                    </fieldset>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="salary-notes">{t('Notes')}</Label>
                        <Input
                            id="salary-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="salary-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                        <Label htmlFor="salary-active">{t('Active')}</Label>
                    </div>
                </div>
            </FormDialog>

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Employee Salary Details"
                fields={
                    viewing
                        ? [
                              ['Employee', viewing.employee.user.name],
                              [
                                  'Basic Salary',
                                  money(Number(viewing.basic_salary)),
                              ],
                              [
                                  'Status',
                                  <StatusBadge
                                      key="status"
                                      status={
                                          viewing.is_active
                                              ? 'active'
                                              : 'inactive'
                                      }
                                  />,
                              ],
                              ['Created At', date(viewing.created_at)],
                              [
                                  'Salary Components',
                                  viewing.components.length
                                      ? viewing.components
                                            .map((c) => c.name)
                                            .join(', ')
                                      : t('Basic only'),
                                  true,
                              ],
                          ]
                        : []
                }
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This employee salary will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(salaryRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

EmployeeSalaries.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payroll Management', href: salaryRoutes.index() },
        { title: 'Employee Salaries', href: salaryRoutes.index() },
    ],
};
