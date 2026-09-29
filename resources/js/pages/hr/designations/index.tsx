import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Eye,
    Lock,
    Plus,
    SquarePen,
    Trash2,
    Unlock,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect } from '@/components/table-filters';
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
import designationRoutes from '@/routes/hr/designations';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Department = Option & { branch: Option | null };

type Designation = {
    id: number;
    name: string;
    department_id: number;
    description: string | null;
    status: 'active' | 'inactive';
    created_at: string;
    department: Department | null;
};

const label = (department: Department) =>
    department.branch
        ? `${department.name} (${department.branch.name})`
        : department.name;

const blank = {
    name: '',
    department_id: '' as number | '',
    description: '',
    status: 'active' as Designation['status'],
};

export default function Designations({
    designations,
    departments,
    filters,
}: {
    designations: Paginated<Designation>;
    departments: Department[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Designation | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Designation | null>(null);
    const [viewing, setViewing] = useState<Designation | null>(null);
    const form = useForm(blank);
    const url = designationRoutes.index();

    const openForm = (designation: Designation | null) => {
        setEditing(designation);
        form.clearErrors();
        form.setData(
            designation
                ? {
                      name: designation.name,
                      department_id: designation.department_id,
                      description: designation.description ?? '',
                      status: designation.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? designationRoutes.update(editing.id)
                : designationRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<Designation>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (d) => <span className="font-medium">{d.name}</span>,
        },
        {
            key: 'department',
            label: 'Department',
            render: (d) =>
                d.department && (
                    <div>
                        <div>{d.department.name}</div>
                        {d.department.branch && (
                            <div className="text-xs text-muted-foreground">
                                {t('Branch')}: {d.department.branch.name}
                            </div>
                        )}
                    </div>
                ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (d) => <StatusBadge status={d.status} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (d) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(d.created_at)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Designations')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Designations"
                    description="Manage job designations within your departments."
                    action={
                        can('create-designations') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Designation')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={designations}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="department"
                            label="All Departments"
                            options={departments.map((d) => ({
                                id: d.id,
                                name: label(d),
                            }))}
                        />
                    }
                    actions={(designation) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(designation)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-designations') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(designation)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('toggle-status-designations') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        designation.status === 'active'
                                            ? 'Deactivate'
                                            : 'Activate',
                                    )}
                                    onClick={() =>
                                        router.put(
                                            designationRoutes.toggleStatus(
                                                designation.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {designation.status === 'active' ? (
                                        <Lock />
                                    ) : (
                                        <Unlock />
                                    )}
                                </Button>
                            )}
                            {can('delete-designations') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(designation)}
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
                title={editing ? 'Edit Designation' : 'Add Designation'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="designation-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="designation-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="designation-department">
                            {t('Department')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="designation-department"
                            required
                            value={form.data.department_id}
                            onChange={(e) =>
                                form.setData(
                                    'department_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">{t('Select Department')}</option>
                            {departments.map((department) => (
                                <option
                                    key={department.id}
                                    value={department.id}
                                >
                                    {label(department)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.department_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="designation-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="designation-description"
                            rows={3}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="designation-status">
                            {t('Status')}
                        </Label>
                        <SelectField
                            id="designation-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Designation['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{viewing?.name}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            {(
                                [
                                    [
                                        'Department',
                                        viewing.department
                                            ? label(viewing.department)
                                            : null,
                                    ],
                                    ['Created At', date(viewing.created_at)],
                                    ['Description', viewing.description],
                                ] as const
                            ).map(([name, value]) => (
                                <div key={name}>
                                    <dt className="text-muted-foreground">
                                        {t(name)}
                                    </dt>
                                    <dd className="font-medium">
                                        {value || '—'}
                                    </dd>
                                </div>
                            ))}
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.status} />
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This designation will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(designationRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Designations.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: designationRoutes.index() },
        { title: 'Designations', href: designationRoutes.index() },
    ],
};
