import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Lock, LockOpen, Plus, SquarePen, Trash2 } from 'lucide-react';
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
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import checklistItemRoutes from '@/routes/hr/recruitment/checklist-items';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type ChecklistItem = {
    id: number;
    checklist_id: number;
    task_name: string;
    description: string | null;
    category: string;
    assigned_to_role: string | null;
    due_day: number;
    is_required: boolean;
    sort_order: number;
    status: 'active' | 'inactive';
    checklist: Option;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function ChecklistItems({
    checklistItems,
    checklists,
    categories,
    filters,
}: {
    checklistItems: Paginated<ChecklistItem>;
    checklists: Option[];
    categories: string[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const blank = {
        checklist_id: (filters.checklist_id ?? '') as number | string,
        task_name: '',
        description: '',
        category: categories[0],
        assigned_to_role: '',
        due_day: 1 as number | string,
        is_required: true,
        sort_order: 0 as number | string,
    };
    const [editing, setEditing] = useState<ChecklistItem | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<ChecklistItem | null>(null);
    const [viewing, setViewing] = useState<ChecklistItem | null>(null);
    const form = useForm(blank);
    const url = checklistItemRoutes.index();

    const openForm = (item: ChecklistItem | null) => {
        setEditing(item);
        form.clearErrors();
        form.setData(
            item
                ? {
                      checklist_id: item.checklist_id,
                      task_name: item.task_name,
                      description: item.description ?? '',
                      category: item.category,
                      assigned_to_role: item.assigned_to_role ?? '',
                      due_day: item.due_day,
                      is_required: item.is_required,
                      sort_order: item.sort_order,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    // The demo shows "Required" in red here (Leave Policies uses the amber one).
    const requiredBadge = (item: ChecklistItem) =>
        item.is_required ? (
            <StatusBadge status="mandatory" label="Required" />
        ) : (
            <StatusBadge status="optional" />
        );

    const columns: Column<ChecklistItem>[] = [
        {
            key: 'checklist',
            label: 'Checklist',
            render: (row) => row.checklist.name,
        },
        {
            key: 'task_name',
            label: 'Task',
            sortable: true,
            render: (row) => (
                <div className="grid justify-items-start gap-1">
                    <span className="font-medium">{row.task_name}</span>
                    {requiredBadge(row)}
                </div>
            ),
        },
        {
            key: 'category',
            label: 'Category',
            render: (row) => <StatusBadge status={row.category} />,
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Checklist Items')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Checklist Items"
                    description="Manage individual items within onboarding checklists."
                    action={
                        can('create-checklist-items') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Checklist Item')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={checklistItems}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="category"
                                label="All Categories"
                                options={categories.map((category) => ({
                                    id: category,
                                    name: t(category),
                                }))}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="checklist_id"
                                label="All Checklists"
                                options={checklists}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="is_required"
                                label="All Types"
                                options={[
                                    { id: '1', name: t('Required') },
                                    { id: '0', name: t('Optional') },
                                ]}
                            />
                        </>
                    }
                    actions={(item) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(item)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-checklist-items') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(item)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            item.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            item.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                checklistItemRoutes.toggleStatus(
                                                    item.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {item.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-checklist-items') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(item)}
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
                title={editing ? 'Edit Checklist Item' : 'Add Checklist Item'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? checklistItemRoutes.update(editing.id)
                            : checklistItemRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="item-checklist">
                            {t('Checklist')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="item-checklist"
                            required
                            value={form.data.checklist_id}
                            onChange={(e) =>
                                form.setData('checklist_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Checklist')}</option>
                            {checklists.map((checklist) => (
                                <option key={checklist.id} value={checklist.id}>
                                    {checklist.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.checklist_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="item-task">
                            {t('Task Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="item-task"
                            required
                            value={form.data.task_name}
                            onChange={(e) =>
                                form.setData('task_name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.task_name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="item-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="item-description"
                            rows={2}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-category">{t('Category')}</Label>
                        <SelectField
                            id="item-category"
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                        >
                            {categories.map((category) => (
                                <option key={category} value={category}>
                                    {t(category)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.category} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-role">
                            {t('Assigned To Role')}
                        </Label>
                        <Input
                            id="item-role"
                            placeholder={t('e.g. HR, IT, Manager')}
                            value={form.data.assigned_to_role}
                            onChange={(e) =>
                                form.setData('assigned_to_role', e.target.value)
                            }
                        />
                        <InputError message={form.errors.assigned_to_role} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-due">
                            {t('Due (days after start)')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="item-due"
                            type="number"
                            min={0}
                            max={365}
                            required
                            value={form.data.due_day}
                            onChange={(e) =>
                                form.setData('due_day', e.target.value)
                            }
                        />
                        <InputError message={form.errors.due_day} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-sort">{t('Sort Order')}</Label>
                        <Input
                            id="item-sort"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(e) =>
                                form.setData('sort_order', e.target.value)
                            }
                        />
                        <InputError message={form.errors.sort_order} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="item-required"
                            checked={form.data.is_required}
                            onCheckedChange={(checked) =>
                                form.setData('is_required', checked)
                            }
                        />
                        <Label htmlFor="item-required">{t('Required')}</Label>
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{viewing?.task_name}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            {(
                                [
                                    ['Checklist', viewing.checklist.name],
                                    [
                                        'Assigned To',
                                        viewing.assigned_to_role ?? '—',
                                    ],
                                    [
                                        'Due',
                                        t('Day :day', { day: viewing.due_day }),
                                    ],
                                    ['Sort Order', viewing.sort_order],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium">{value}</dd>
                                </div>
                            ))}
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Category')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.category} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Required')}
                                </dt>
                                <dd>{requiredBadge(viewing)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.status} />
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Description')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.description || '—'}
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This checklist item will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(checklistItemRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

ChecklistItems.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: checklistItemRoutes.index() },
        { title: 'Checklist Items', href: checklistItemRoutes.index() },
    ],
};
