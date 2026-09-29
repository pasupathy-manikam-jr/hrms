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
import departmentRoutes from '@/routes/hr/departments';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Department = {
    id: number;
    name: string;
    branch_id: number;
    description: string | null;
    status: 'active' | 'inactive';
    created_at: string;
    branch: Option | null;
};

const blank = {
    name: '',
    branch_id: '' as number | '',
    description: '',
    status: 'active' as Department['status'],
};

export default function Departments({
    departments,
    branches,
    statusCounts,
    filters,
}: {
    departments: Paginated<Department>;
    branches: Option[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Department | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Department | null>(null);
    const [viewing, setViewing] = useState<Department | null>(null);
    const form = useForm(blank);
    const url = departmentRoutes.index();

    const openForm = (department: Department | null) => {
        setEditing(department);
        form.clearErrors();
        form.setData(
            department
                ? {
                      name: department.name,
                      branch_id: department.branch_id,
                      description: department.description ?? '',
                      status: department.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? departmentRoutes.update(editing.id)
                : departmentRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<Department>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (d) => <span className="font-medium">{d.name}</span>,
        },
        { key: 'branch', label: 'Branch', render: (d) => d.branch?.name },
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
            <Head title={t('Departments')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Departments"
                    description="Manage departments within your branches."
                    action={
                        can('create-departments') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Department')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={departments}
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
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="branch_id"
                            label="All Branches"
                            options={branches}
                        />
                    }
                    actions={(department) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(department)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-departments') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(department)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('toggle-status-departments') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        department.status === 'active'
                                            ? 'Deactivate'
                                            : 'Activate',
                                    )}
                                    onClick={() =>
                                        router.put(
                                            departmentRoutes.toggleStatus(
                                                department.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {department.status === 'active' ? (
                                        <Lock />
                                    ) : (
                                        <Unlock />
                                    )}
                                </Button>
                            )}
                            {can('delete-departments') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(department)}
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
                title={editing ? 'Edit Department' : 'Add Department'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="department-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="department-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="department-branch">
                            {t('Branch')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="department-branch"
                            required
                            value={form.data.branch_id}
                            onChange={(e) =>
                                form.setData(
                                    'branch_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">{t('Select Branch')}</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.branch_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="department-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="department-description"
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
                        <Label htmlFor="department-status">{t('Status')}</Label>
                        <SelectField
                            id="department-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Department['status'],
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
                                    ['Branch', viewing.branch?.name],
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
                description="This department will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(departmentRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Departments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: departmentRoutes.index() },
        { title: 'Departments', href: departmentRoutes.index() },
    ],
};
