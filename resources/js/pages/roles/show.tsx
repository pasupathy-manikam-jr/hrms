import { Head } from '@inertiajs/react';
import { CalendarDays, KeyRound, ShieldCheck, Users } from 'lucide-react';
import {
    DetailPage,
    Fields,
    RecordList,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import roleRoutes from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    label: string | null;
    description: string | null;
    created_at: string;
    is_editable: boolean;
};

type Member = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    status: string;
};

type Permission = {
    id: number;
    name: string;
    label: string | null;
    description: string | null;
};

export default function RoleShow({
    role,
    users,
    permissions,
}: {
    role: Role;
    users: Member[];
    permissions: Record<string, Permission[]>;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const title = role.label ?? role.name;
    const groups = Object.entries(permissions);
    const permissionCount = groups.reduce(
        (sum, [, items]) => sum + items.length,
        0,
    );

    return (
        <>
            <Head title={title} />
            <DetailPage
                title={title}
                description="View the role, its users and its permissions."
                back={roleRoutes.index()}
                summary={
                    <Summary
                        media={<SummaryIcon icon={ShieldCheck} />}
                        title={title}
                        subtitle={role.name}
                        facts={[
                            [Users, t(':count users', { count: users.length })],
                            [
                                KeyRound,
                                t(':count permissions', {
                                    count: permissionCount,
                                }),
                            ],
                            [CalendarDays, date(role.created_at)],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Role Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        ['Label', role.label],
                                        ['Name', role.name],
                                        ['Users', users.length],
                                        ['Permissions', permissionCount],
                                        [
                                            'Editable',
                                            role.is_editable
                                                ? t('Yes')
                                                : t('No'),
                                        ],
                                        [
                                            'Created',
                                            <DateCell
                                                key="c"
                                                value={role.created_at}
                                            />,
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Description"
                                    value={role.description}
                                />
                            </div>
                        ),
                    },
                    {
                        label: 'Users',
                        content: (
                            <RecordList
                                items={users}
                                empty="No users have this role"
                                render={(user) => (
                                    <>
                                        <PersonCell
                                            name={user.name}
                                            detail={user.email}
                                            src={user.avatar}
                                        />
                                        <StatusBadge status={user.status} />
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Permissions',
                        content:
                            groups.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('This role has no permissions.')}
                                </p>
                            ) : (
                                <div className="grid gap-4">
                                    {groups.map(([module, items]) => (
                                        <div
                                            key={module}
                                            className="rounded-lg border p-4"
                                        >
                                            <h4 className="mb-3 flex items-center justify-between font-medium">
                                                {t(module)}
                                                <span className="text-sm font-normal text-muted-foreground">
                                                    {items.length}
                                                </span>
                                            </h4>
                                            <ul className="flex flex-wrap gap-2">
                                                {items.map((p) => (
                                                    <li
                                                        key={p.id}
                                                        title={
                                                            p.description ??
                                                            p.name
                                                        }
                                                        className="rounded-md border bg-muted/40 px-2 py-0.5 text-sm"
                                                    >
                                                        {t(p.label ?? p.name)}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            ),
                    },
                ]}
            />
        </>
    );
}

RoleShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles', href: roleRoutes.index() },
        { title: 'Role Details', href: roleRoutes.index() },
    ],
};
