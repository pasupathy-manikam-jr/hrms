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
import branchRoutes from '@/routes/hr/branches';
import type { Paginated, TableFilters } from '@/types';

type Branch = {
    id: number;
    name: string;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    zip_code: string | null;
    phone: string | null;
    email: string | null;
    status: 'active' | 'inactive';
    created_at: string;
};

const FIELDS = [
    ['name', 'Name'],
    ['email', 'Email'],
    ['phone', 'Phone'],
    ['address', 'Address'],
    ['city', 'City'],
    ['state', 'State'],
    ['country', 'Country'],
    ['zip_code', 'Postcode'],
] as const;

const blank = {
    name: '',
    address: '',
    city: '',
    state: '',
    country: '',
    zip_code: '',
    phone: '',
    email: '',
    status: 'active' as Branch['status'],
};

export default function Branches({
    branches,
    filters,
}: {
    branches: Paginated<Branch>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Branch | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [viewing, setViewing] = useState<Branch | null>(null);
    const [deleting, setDeleting] = useState<Branch | null>(null);
    const form = useForm(blank);

    const openForm = (branch: Branch | null) => {
        setEditing(branch);
        form.clearErrors();
        form.setData(
            branch
                ? (Object.fromEntries(
                      Object.keys(blank).map((key) => [
                          key,
                          branch[key as keyof typeof blank] ?? '',
                      ]),
                  ) as typeof blank)
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? branchRoutes.update(editing.id) : branchRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<Branch>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (b) => (
                <div>
                    <div className="font-medium">{b.name}</div>
                    <div className="text-muted-foreground">{b.email}</div>
                </div>
            ),
        },
        { key: 'phone', label: 'Contact', render: (b) => b.phone },
        {
            key: 'status',
            label: 'Status',
            render: (b) => <StatusBadge status={b.status} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (b) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(b.created_at)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Branches')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Branches"
                    description="Manage your company branches and locations."
                    action={
                        can('create-branches') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Branch')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={branches}
                    columns={columns}
                    filters={filters}
                    url={branchRoutes.index()}
                    actions={(branch) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(branch)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-branches') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(branch)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('toggle-status-branches') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        branch.status === 'active'
                                            ? 'Deactivate'
                                            : 'Activate',
                                    )}
                                    onClick={() =>
                                        router.put(
                                            branchRoutes.toggleStatus(
                                                branch.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {branch.status === 'active' ? (
                                        <Lock />
                                    ) : (
                                        <Unlock />
                                    )}
                                </Button>
                            )}
                            {can('delete-branches') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(branch)}
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
                title={editing ? 'Edit Branch' : 'Add Branch'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {FIELDS.map(([key, label]) => (
                        <div
                            key={key}
                            className={
                                key === 'address'
                                    ? 'grid gap-2 sm:col-span-2'
                                    : 'grid gap-2'
                            }
                        >
                            <Label htmlFor={`branch-${key}`}>
                                {t(label)}
                                {key === 'name' && (
                                    <span className="text-destructive">*</span>
                                )}
                            </Label>
                            <Input
                                id={`branch-${key}`}
                                type={key === 'email' ? 'email' : 'text'}
                                required={key === 'name'}
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="branch-status">{t('Status')}</Label>
                        <SelectField
                            id="branch-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Branch['status'],
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
                            {FIELDS.slice(1).map(([key, label]) => (
                                <div key={key}>
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium">
                                        {viewing[key] || '—'}
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
                description="This branch will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(branchRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Branches.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: branchRoutes.index() },
        { title: 'Branches', href: branchRoutes.index() },
    ],
};
