import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { FilterSelect } from '@/components/table-filters';
import { ViewDialog } from '@/components/view-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import trainingTypeRoutes from '@/routes/hr/training-types';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type DepartmentOption = Option & { branch_id: number };

type TrainingType = {
    id: number;
    name: string;
    description: string | null;
    branch_id: number | null;
    branch: Option | null;
    departments: Option[];
    training_programs_count: number;
    created_at: string;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    name: '',
    description: '',
    branch_id: '' as number | string,
    department_ids: [] as number[],
};

export default function TrainingTypes({
    trainingTypes,
    branches,
    departments,
    filters,
}: {
    trainingTypes: Paginated<TrainingType>;
    branches: Option[];
    departments: DepartmentOption[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = trainingTypeRoutes.index();
    const [editing, setEditing] = useState<TrainingType | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<TrainingType | null>(null);
    const [viewing, setViewing] = useState<TrainingType | null>(null);
    const [assigning, setAssigning] = useState<TrainingType | null>(null);
    const form = useForm(blank);
    const assignForm = useForm({ department_ids: [] as number[] });

    const openAssign = (type: TrainingType) => {
        assignForm.clearErrors();
        assignForm.setData(
            'department_ids',
            type.departments.map((d) => d.id),
        );
        setAssigning(type);
    };

    // A type tied to a branch can only use that branch's departments.
    const assignable = assigning
        ? departments.filter(
              (d) =>
                  !assigning.branch_id || d.branch_id === assigning.branch_id,
          )
        : [];

    const departmentTags = (type: TrainingType) => (
        <div className="flex flex-wrap gap-1">
            {type.departments.map((d) => (
                <span
                    key={d.id}
                    className="rounded-md border px-2 py-0.5 text-xs leading-tight"
                >
                    <span className="block font-medium">{d.name}</span>
                    {type.branch && (
                        <span className="block text-muted-foreground">
                            {type.branch.name}
                        </span>
                    )}
                </span>
            ))}
        </div>
    );

    const openForm = (type: TrainingType | null) => {
        setEditing(type);
        form.clearErrors();
        form.setData(
            type
                ? {
                      name: type.name,
                      description: type.description ?? '',
                      branch_id: type.branch_id ?? '',
                      department_ids: type.departments.map((d) => d.id),
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const branchDepartments = departments.filter(
        (d) => d.branch_id === Number(form.data.branch_id),
    );

    const toggleDepartment = (id: number, checked: boolean) =>
        form.setData(
            'department_ids',
            checked
                ? [...form.data.department_ids, id]
                : form.data.department_ids.filter((d) => d !== id),
        );

    const columns: Column<TrainingType>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (type) => (
                <div>
                    <div className="font-medium">{type.name}</div>
                    <div className="line-clamp-1 max-w-md text-xs text-muted-foreground">
                        {type.description}
                    </div>
                </div>
            ),
        },
        {
            key: 'departments',
            label: 'Departments',
            render: departmentTags,
        },
        {
            key: 'training_programs_count',
            label: 'Programs',
            render: (type) => type.training_programs_count,
        },
    ];

    return (
        <>
            <Head title={t('Training Types')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Training Types"
                    description="Manage training types used to categorize training programs."
                    action={
                        can('create-training-types') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Training Type')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={trainingTypes}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="branch_id"
                                label="All Branches"
                                options={branches}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="department_id"
                                label="All Departments"
                                options={departments
                                    .filter(
                                        (d) =>
                                            !filters.branch_id ||
                                            d.branch_id ===
                                                Number(filters.branch_id),
                                    )
                                    .map((d) => ({
                                        id: d.id,
                                        name: `${d.name} · ${branches.find((b) => b.id === d.branch_id)?.name ?? ''}`,
                                    }))}
                            />
                        </>
                    }
                    actions={(type) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(type)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-training-types') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(type)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Assign Departments')}
                                        title={t('Assign Departments')}
                                        onClick={() => openAssign(type)}
                                    >
                                        <Users />
                                    </Button>
                                </>
                            )}
                            {can('delete-training-types') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(type)}
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
                title={editing ? 'Edit Training Type' : 'Add Training Type'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? trainingTypeRoutes.update(editing.id)
                            : trainingTypeRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="training-type-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="training-type-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="training-type-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="training-type-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="training-type-branch">
                            {t('Branch')}
                        </Label>
                        <SelectField
                            id="training-type-branch"
                            value={form.data.branch_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    branch_id: e.target.value,
                                    department_ids: [],
                                })
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
                    {branchDepartments.length > 0 && (
                        <div className="grid gap-2">
                            <Label>{t('Departments')}</Label>
                            <div className="grid max-h-40 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                                {branchDepartments.map((department) => (
                                    <label
                                        key={department.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.department_ids.includes(
                                                department.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleDepartment(
                                                    department.id,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {department.name}
                                    </label>
                                ))}
                            </div>
                            <InputError message={form.errors.department_ids} />
                        </div>
                    )}
                </div>
            </FormDialog>

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Training Type Details"
                fields={
                    viewing
                        ? [
                              ['Name', viewing.name],
                              ['Programs', viewing.training_programs_count],
                              ['Branch', viewing.branch?.name, true],
                              ['Departments', departmentTags(viewing), true],
                              ['Description', viewing.description, true],
                          ]
                        : []
                }
            />

            <FormDialog
                open={assigning !== null}
                onOpenChange={(open) => !open && setAssigning(null)}
                title="Assign Departments"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (assigning) {
                        assignForm.submit(
                            trainingTypeRoutes.assignDepartments(assigning.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setAssigning(null),
                            },
                        );
                    }
                }}
                processing={assignForm.processing}
            >
                <div className="grid gap-2">
                    <Label>
                        {t('Departments')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <div className="grid max-h-60 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                        {assignable.map((department) => (
                            <label
                                key={department.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={assignForm.data.department_ids.includes(
                                        department.id,
                                    )}
                                    onCheckedChange={(checked) =>
                                        assignForm.setData(
                                            'department_ids',
                                            checked === true
                                                ? [
                                                      ...assignForm.data
                                                          .department_ids,
                                                      department.id,
                                                  ]
                                                : assignForm.data.department_ids.filter(
                                                      (id) =>
                                                          id !== department.id,
                                                  ),
                                        )
                                    }
                                />
                                {department.name}
                            </label>
                        ))}
                    </div>
                    <InputError
                        message={
                            assignForm.errors.department_ids ??
                            Object.entries(assignForm.errors).find(([key]) =>
                                key.startsWith('department_ids.'),
                            )?.[1]
                        }
                    />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This training type and its programs will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(trainingTypeRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

TrainingTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Training & Development', href: trainingTypeRoutes.index() },
        { title: 'Training Types', href: trainingTypeRoutes.index() },
    ],
};
