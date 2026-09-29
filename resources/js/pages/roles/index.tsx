import { Head, Link, router } from '@inertiajs/react';
import { Eye, Plus, ShieldCheck, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import roleRoutes from '@/routes/roles';
import type { Paginated, TableFilters } from '@/types';

type Role = {
    id: number;
    name: string;
    label: string | null;
    description: string | null;
    permissions_count: number;
    permission_preview: string[];
    users_count: number;
    is_editable: boolean;
    is_deletable: boolean;
    created_at: string;
};

export default function Roles({
    roles,
    filters,
}: {
    roles: Paginated<Role>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [deleting, setDeleting] = useState<Role | null>(null);

    const columns: Column<Role>[] = [
        {
            key: 'label',
            label: 'Role',
            sortable: true,
            render: (r) => (
                <div className="flex items-center gap-2">
                    <ShieldCheck className="size-4 text-muted-foreground" />
                    <div>
                        <div className="font-medium">{r.label ?? r.name}</div>
                        <div className="text-muted-foreground">
                            {r.description}
                        </div>
                    </div>
                    {!r.is_deletable && (
                        <Badge variant="secondary">{t('Built-in')}</Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'permissions',
            label: 'Permissions',
            render: (r) => (
                <div className="flex flex-wrap gap-1">
                    {r.permission_preview.map((label) => (
                        <IdBadge key={label}>{label}</IdBadge>
                    ))}
                    {r.permissions_count > r.permission_preview.length && (
                        <Badge variant="secondary">
                            {t('+:count more', {
                                count:
                                    r.permissions_count -
                                    r.permission_preview.length,
                            })}
                        </Badge>
                    )}
                </div>
            ),
        },
        { key: 'users', label: 'Users', render: (r) => r.users_count },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (r) => <DateCell value={r.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Roles')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Roles"
                    description="Manage roles and the permissions they grant."
                    action={
                        can('create-roles') && (
                            <Button asChild>
                                <Link href={roleRoutes.create()}>
                                    <Plus /> {t('Add Role')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={roles}
                    columns={columns}
                    filters={filters}
                    url={roleRoutes.index()}
                    actions={(role) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={roleRoutes.show(role.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-roles') && role.is_editable && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    asChild
                                >
                                    <Link href={roleRoutes.edit(role.id)}>
                                        <SquarePen />
                                    </Link>
                                </Button>
                            )}
                            {can('delete-roles') && role.is_deletable && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(role)}
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
                description="This role will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(roleRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Roles.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles', href: roleRoutes.index() },
    ],
};
