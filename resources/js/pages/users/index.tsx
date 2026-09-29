import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Eye,
    History,
    KeyRound,
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
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { PersonCell } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import loginHistoryRoutes from '@/routes/login-history';
import userRoutes from '@/routes/users';
import type { Paginated, TableFilters } from '@/types';

type Role = { id: number; name: string; label: string | null };

type User = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    status: 'active' | 'inactive';
    created_at: string;
    roles: Role[];
};

const FIELDS = [
    ['name', 'Name', 'text'],
    ['email', 'Email', 'email'],
    ['password', 'Password', 'password'],
    ['password_confirmation', 'Confirm Password', 'password'],
] as const;

const blank = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
};

export default function Users({
    users,
    roles,
    filters,
}: {
    users: Paginated<User>;
    roles: Role[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<User | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [resetting, setResetting] = useState<User | null>(null);
    const [deleting, setDeleting] = useState<User | null>(null);
    const form = useForm(blank);
    const passwordForm = useForm({ password: '', password_confirmation: '' });

    const openForm = (user: User | null) => {
        setEditing(user);
        form.clearErrors();
        form.setData(
            user
                ? {
                      ...blank,
                      name: user.name,
                      email: user.email,
                      roles: user.roles.map((r) => r.name),
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const roleBadges = (u: User) => (
        <div className="flex flex-wrap gap-1">
            {u.roles.map((r) => (
                <IdBadge key={r.id}>{r.label ?? r.name}</IdBadge>
            ))}
        </div>
    );

    const columns: Column<User>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (u) => (
                <PersonCell name={u.name} detail={u.email} src={u.avatar} />
            ),
        },
        {
            key: 'roles',
            label: 'Roles',
            render: roleBadges,
        },
        {
            key: 'status',
            label: 'Status',
            render: (u) => <StatusBadge status={u.status} />,
        },
        {
            key: 'created_at',
            label: 'Joined',
            sortable: true,
            render: (u) => <DateCell value={u.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Users')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Users"
                    description="Manage company users and their roles. Employees are managed in the Employees module."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label={t('Login History')}
                                title={t('Login History')}
                                asChild
                            >
                                <Link href={loginHistoryRoutes.index()}>
                                    <History />
                                </Link>
                            </Button>
                            {can('create-users') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Add User')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={users}
                    columns={columns}
                    filters={filters}
                    url={userRoutes.index()}
                    renderCard={(u, actions) => (
                        <div className="flex h-full flex-col rounded-xl border bg-card shadow-sm">
                            <div className="flex items-start justify-between gap-2 border-b p-4">
                                <PersonCell
                                    name={u.name}
                                    detail={u.email}
                                    src={u.avatar}
                                />
                                <StatusBadge status={u.status} />
                            </div>
                            <dl className="grid flex-1 gap-2 p-4 text-sm">
                                <div className="flex items-center gap-2">
                                    <dt className="font-medium">
                                        {t('Roles')}:
                                    </dt>
                                    <dd>{roleBadges(u)}</dd>
                                </div>
                                <div className="flex items-center gap-2">
                                    <dt className="font-medium">
                                        {t('Joined')}:
                                    </dt>
                                    <dd className="text-muted-foreground">
                                        <DateCell value={u.created_at} />
                                    </dd>
                                </div>
                            </dl>
                            <div className="border-t px-4 py-2">{actions}</div>
                        </div>
                    )}
                    toolbar={
                        <FilterSelect
                            url={userRoutes.index()}
                            filters={filters}
                            name="role"
                            label="All Roles"
                            options={roles.map((r) => ({
                                id: r.name,
                                name: r.label ?? r.name,
                            }))}
                        />
                    }
                    moreFilters={
                        <>
                            <FilterSelect
                                url={userRoutes.index()}
                                filters={filters}
                                name="status"
                                label="All Statuses"
                                options={[
                                    { id: 'active', name: t('Active') },
                                    { id: 'inactive', name: t('Inactive') },
                                ]}
                            />
                        </>
                    }
                    actions={(user) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={userRoutes.show(user.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-users') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(user)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('reset-password-users') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Reset Password')}
                                    onClick={() => {
                                        passwordForm.reset();
                                        passwordForm.clearErrors();
                                        setResetting(user);
                                    }}
                                >
                                    <KeyRound />
                                </Button>
                            )}
                            {can('toggle-status-users') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        user.status === 'active'
                                            ? 'Deactivate'
                                            : 'Activate',
                                    )}
                                    onClick={() =>
                                        router.put(
                                            userRoutes.toggleStatus(user.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {user.status === 'active' ? (
                                        <Lock />
                                    ) : (
                                        <Unlock />
                                    )}
                                </Button>
                            )}
                            {can('delete-users') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(user)}
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
                title={editing ? 'Edit User' : 'Add User'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? userRoutes.update(editing.id)
                            : userRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {FIELDS.filter(
                        ([key]) => !editing || !key.startsWith('password'),
                    ).map(([key, label, type]) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={`user-${key}`}>
                                {t(label)}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id={`user-${key}`}
                                type={type}
                                required
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <fieldset className="grid gap-2 sm:col-span-2">
                        <legend className="mb-2 text-sm font-medium">
                            {t('Roles')}
                            <span className="text-destructive">*</span>
                        </legend>
                        <div className="flex flex-wrap gap-4">
                            {roles.map((r) => (
                                <label
                                    key={r.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.roles.includes(
                                            r.name,
                                        )}
                                        onCheckedChange={(on) =>
                                            form.setData(
                                                'roles',
                                                on === true
                                                    ? [
                                                          ...form.data.roles,
                                                          r.name,
                                                      ]
                                                    : form.data.roles.filter(
                                                          (n) => n !== r.name,
                                                      ),
                                            )
                                        }
                                    />
                                    {r.label ?? r.name}
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.roles} />
                    </fieldset>
                </div>
            </FormDialog>

            <FormDialog
                open={resetting !== null}
                onOpenChange={(open) => !open && setResetting(null)}
                title="Reset Password"
                description={resetting?.email}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (resetting) {
                        passwordForm.submit(
                            userRoutes.resetPassword(resetting.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setResetting(null),
                            },
                        );
                    }
                }}
                processing={passwordForm.processing}
            >
                {(
                    [
                        ['password', 'New Password'],
                        ['password_confirmation', 'Confirm Password'],
                    ] as const
                ).map(([key, label]) => (
                    <div key={key} className="grid gap-2">
                        <Label htmlFor={`reset-${key}`}>{t(label)}</Label>
                        <Input
                            id={`reset-${key}`}
                            type="password"
                            required
                            value={passwordForm.data[key]}
                            onChange={(e) =>
                                passwordForm.setData(key, e.target.value)
                            }
                        />
                        <InputError message={passwordForm.errors[key]} />
                    </div>
                ))}
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This user will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(userRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Users.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: userRoutes.index() },
    ],
};
