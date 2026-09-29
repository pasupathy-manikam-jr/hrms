import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Lock, LockOpen, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
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
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import checklistItemRoutes from '@/routes/hr/recruitment/checklist-items';
import checklistRoutes from '@/routes/hr/recruitment/onboarding-checklists';
import type { Paginated, TableFilters } from '@/types';

type Checklist = {
    id: number;
    name: string;
    description: string | null;
    is_default: boolean;
    status: 'active' | 'inactive';
    checklist_items_count: number;
    created_at: string;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    name: '',
    description: '',
    is_default: false,
    status: 'active' as Checklist['status'],
};

export default function OnboardingChecklists({
    onboardingChecklists,
    statusCounts,
    filters,
}: {
    onboardingChecklists: Paginated<Checklist>;
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const { date } = useFormat();
    const [editing, setEditing] = useState<Checklist | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Checklist | null>(null);
    const [viewing, setViewing] = useState<Checklist | null>(null);
    const form = useForm(blank);
    const url = checklistRoutes.index();

    const openForm = (checklist: Checklist | null) => {
        setEditing(checklist);
        form.clearErrors();
        form.setData(
            checklist
                ? {
                      name: checklist.name,
                      description: checklist.description ?? '',
                      is_default: checklist.is_default,
                      status: checklist.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Checklist>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => (
                <div className="grid justify-items-start gap-1">
                    <span className="font-medium">{row.name}</span>
                    {row.is_default && (
                        <Badge
                            variant="outline"
                            className="border-blue-200 bg-blue-50 text-blue-700"
                        >
                            {t('Default')}
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'checklist_items_count',
            label: 'Items',
            render: (row) => {
                const count = (
                    <span className="inline-flex size-6 items-center justify-center rounded-full border bg-muted text-xs font-medium">
                        {row.checklist_items_count}
                    </span>
                );

                return can('manage-checklist-items') ? (
                    <Link
                        href={checklistItemRoutes.index({
                            query: { checklist_id: row.id },
                        })}
                        aria-label={t('View checklist items')}
                    >
                        {count}
                    </Link>
                ) : (
                    count
                );
            },
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (row) => <DateCell value={row.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Onboarding Checklists')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Onboarding Checklists"
                    description="Manage onboarding checklists used for new hires."
                    action={
                        can('create-onboarding-checklists') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Checklist')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={onboardingChecklists}
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
                            name="is_default"
                            label="All Types"
                            options={[
                                { id: '1', name: t('Default') },
                                { id: '0', name: t('Not Default') },
                            ]}
                        />
                    }
                    actions={(checklist) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(checklist)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-onboarding-checklists') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(checklist)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            checklist.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            checklist.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                checklistRoutes.toggleStatus(
                                                    checklist.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {checklist.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-onboarding-checklists') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(checklist)}
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
                title={editing ? 'Edit Checklist' : 'Add Checklist'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? checklistRoutes.update(editing.id)
                            : checklistRoutes.store(),
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
                        <Label htmlFor="checklist-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="checklist-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="checklist-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="checklist-description"
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
                        <Label htmlFor="checklist-status">{t('Status')}</Label>
                        <SelectField
                            id="checklist-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Checklist['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="checklist-default"
                            checked={form.data.is_default}
                            onCheckedChange={(checked) =>
                                form.setData('is_default', checked)
                            }
                        />
                        <Label htmlFor="checklist-default">
                            {t('Default checklist')}
                        </Label>
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
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Description')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.description || '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Items')}
                                </dt>
                                <dd className="font-medium">
                                    {can('manage-checklist-items') ? (
                                        <Link
                                            href={checklistItemRoutes.index({
                                                query: {
                                                    checklist_id: viewing.id,
                                                },
                                            })}
                                            className="text-primary hover:underline"
                                        >
                                            {t(':count items', {
                                                count: viewing.checklist_items_count,
                                            })}
                                        </Link>
                                    ) : (
                                        viewing.checklist_items_count
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Default')}
                                </dt>
                                <dd className="font-medium">
                                    {t(viewing.is_default ? 'Yes' : 'No')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.status} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Created At')}
                                </dt>
                                <dd className="font-medium">
                                    {date(viewing.created_at)}
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This checklist and its items will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(checklistRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

OnboardingChecklists.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: checklistRoutes.index() },
        { title: 'Onboarding Checklists', href: checklistRoutes.index() },
    ],
};
