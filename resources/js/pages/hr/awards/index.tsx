import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Gift, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { DateCell, IdBadge } from '@/components/table-cells';
import { DateRangeFilter, FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import awardRoutes from '@/routes/hr/awards';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = Option & { employee_id: string };

type Award = {
    id: number;
    employee_id: number;
    award_type_id: number;
    award_date: string;
    gift: string | null;
    monetary_value: string | null;
    description: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: Person;
    };
    award_type: Option;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    employee_id: '' as number | string,
    award_type_id: '' as number | string,
    award_date: '',
    gift: '',
    monetary_value: '',
    description: '',
};

export default function Awards({
    awards,
    awardTypes,
    employees,
    filters,
}: {
    awards: Paginated<Award>;
    awardTypes: Option[];
    employees: EmployeeOption[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = awardRoutes.index();
    const [editing, setEditing] = useState<Award | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Award | null>(null);
    const form = useForm(blank);

    const openForm = (award: Award | null) => {
        setEditing(award);
        form.clearErrors();
        form.setData(
            award
                ? {
                      employee_id: award.employee_id,
                      award_type_id: award.award_type_id,
                      award_date: award.award_date,
                      gift: award.gift ?? '',
                      monetary_value: award.monetary_value ?? '',
                      description: award.description ?? '',
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Award>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (a) => (
                <PersonCell
                    name={a.employee.user.name}
                    detail={a.employee.user.email}
                    src={a.employee.user.avatar}
                    gender={a.employee.gender}
                />
            ),
        },
        {
            key: 'award_type',
            label: 'Award Type',
            render: (a) => <IdBadge>{a.award_type.name}</IdBadge>,
        },
        {
            key: 'award_date',
            label: 'Award Date',
            sortable: true,
            render: (a) => <DateCell value={a.award_date} />,
        },
        {
            key: 'gift',
            label: 'Gift',
            render: (a) =>
                a.gift ? (
                    <span className="inline-flex items-center gap-1 rounded-md border border-pink-200 bg-pink-50 px-2 py-0.5 text-xs font-medium whitespace-nowrap text-pink-700 dark:border-pink-900 dark:bg-pink-950 dark:text-pink-300">
                        <Gift className="size-3" />
                        {a.gift}
                    </span>
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <>
            <Head title={t('Awards')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Awards"
                    description="Recognise employees with awards and gifts."
                    action={
                        can('create-awards') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Award')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={awards}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="award_type_id"
                                label="All Award Types"
                                options={awardTypes}
                            />
                            {employees.length > 0 && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="employee_id"
                                    label="All Employees"
                                    options={employees}
                                />
                            )}
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(award) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={awardRoutes.show(award.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-awards') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(award)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-awards') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(award)}
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
                title={editing ? 'Edit Award' : 'Add Award'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? awardRoutes.update(editing.id)
                            : awardRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
                submitLabel={editing ? 'Save' : 'Create'}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="award-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="award-employee"
                            required
                            value={form.data.employee_id}
                            onChange={(e) =>
                                form.setData('employee_id', e.target.value)
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
                        <Label htmlFor="award-type">
                            {t('Award Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="award-type"
                            required
                            value={form.data.award_type_id}
                            onChange={(e) =>
                                form.setData('award_type_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Award Type')}</option>
                            {awardTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.award_type_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="award-date">
                            {t('Award Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="award-date"
                            type="date"
                            required
                            value={form.data.award_date}
                            onChange={(e) =>
                                form.setData('award_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.award_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="award-gift">{t('Gift')}</Label>
                        <Input
                            id="award-gift"
                            value={form.data.gift}
                            onChange={(e) =>
                                form.setData('gift', e.target.value)
                            }
                        />
                        <InputError message={form.errors.gift} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="award-value">
                            {t('Monetary Value')}
                        </Label>
                        <Input
                            id="award-value"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.monetary_value}
                            onChange={(e) =>
                                form.setData('monetary_value', e.target.value)
                            }
                        />
                        <InputError message={form.errors.monetary_value} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="award-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="award-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This award will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(awardRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Awards.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Lifecycle', href: awardRoutes.index() },
        { title: 'Awards', href: awardRoutes.index() },
    ],
};
