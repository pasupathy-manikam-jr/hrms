import { Head } from '@inertiajs/react';
import { CalendarDays, Mail, ShieldCheck } from 'lucide-react';
import {
    DetailPage,
    Fields,
    RecordList,
    Summary,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { PersonCell, UserAvatar } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import userRoutes from '@/routes/users';

type Role = { id: number; name: string; label: string | null };

type User = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    status: string;
    lang: string | null;
    email_verified_at: string | null;
    two_factor_confirmed_at: string | null;
    created_at: string;
    roles: Role[];
    reports_to: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
    } | null;
    direct_reports_count: number;
};

type Login = {
    id: number;
    ip: string | null;
    browser: string | null;
    os: string | null;
    device: string | null;
    logged_in_at: string;
};

export default function UserShow({
    user,
    logins,
}: {
    user: User;
    logins: Login[];
}) {
    const { t } = useTranslation();
    const { date, dateTime } = useFormat();
    const roleNames = user.roles.map((r) => r.label ?? r.name).join(', ');

    return (
        <>
            <Head title={user.name} />
            <DetailPage
                title={user.name}
                description="View the user's profile, roles and recent logins."
                back={userRoutes.index()}
                summary={
                    <Summary
                        media={
                            <UserAvatar
                                name={user.name}
                                src={user.avatar}
                                className="size-32"
                            />
                        }
                        title={user.name}
                        subtitle={roleNames}
                        status={user.status}
                        facts={[
                            [Mail, user.email],
                            [ShieldCheck, roleNames],
                            [
                                CalendarDays,
                                `${t('Joined')}: ${date(user.created_at)}`,
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Profile',
                        heading: 'User Profile',
                        content: (
                            <Fields
                                items={[
                                    ['Name', user.name],
                                    ['Email', user.email],
                                    [
                                        'Status',
                                        <StatusBadge
                                            key="s"
                                            status={user.status}
                                        />,
                                    ],
                                    [
                                        'Email Verified',
                                        user.email_verified_at
                                            ? date(user.email_verified_at)
                                            : t('No'),
                                    ],
                                    [
                                        'Two-Factor Authentication',
                                        user.two_factor_confirmed_at
                                            ? t('Enabled')
                                            : t('Disabled'),
                                    ],
                                    ['Language', user.lang?.toUpperCase()],
                                    [
                                        'Reports To',
                                        user.reports_to && (
                                            <PersonCell
                                                name={user.reports_to.name}
                                                detail={user.reports_to.email}
                                                src={user.reports_to.avatar}
                                            />
                                        ),
                                    ],
                                    [
                                        'Direct Reports',
                                        user.direct_reports_count,
                                    ],
                                    [
                                        'Created',
                                        <DateCell
                                            key="c"
                                            value={user.created_at}
                                        />,
                                    ],
                                ]}
                            />
                        ),
                    },
                    {
                        label: 'Roles',
                        content: (
                            <RecordList
                                items={user.roles}
                                empty="No roles assigned"
                                render={(role) => (
                                    <>
                                        <span className="flex items-center gap-2 font-medium">
                                            <ShieldCheck className="size-4 text-muted-foreground" />
                                            {role.label ?? role.name}
                                        </span>
                                        <span className="font-mono text-sm text-muted-foreground">
                                            {role.name}
                                        </span>
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Login History',
                        heading: 'Recent Logins',
                        content: (
                            <RecordList
                                items={logins}
                                empty="No logins recorded yet"
                                render={(login) => (
                                    <>
                                        <div>
                                            <div className="font-medium whitespace-nowrap">
                                                {dateTime(login.logged_in_at)}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {[
                                                    login.browser,
                                                    login.os,
                                                    login.device,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '-'}
                                            </div>
                                        </div>
                                        <span className="font-mono text-sm text-muted-foreground">
                                            {login.ip}
                                        </span>
                                    </>
                                )}
                            />
                        ),
                    },
                ]}
            />
        </>
    );
}

UserShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: userRoutes.index() },
        { title: 'User Details', href: userRoutes.index() },
    ],
};
