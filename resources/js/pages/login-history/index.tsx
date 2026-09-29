import { Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { applyFilters, FilterSelect } from '@/components/table-filters';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import loginHistoryRoutes from '@/routes/login-history';
import type { Paginated, TableFilters } from '@/types';

type Login = {
    id: number;
    user_id: number;
    ip: string | null;
    user_agent: string | null;
    browser: string | null;
    os: string | null;
    device: string | null;
    logged_in_at: string;
    user: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        roles: { id: number; name: string; label: string | null }[];
    } | null;
};

export default function LoginHistory({
    loginHistory,
    filters,
    users,
}: {
    loginHistory: Paginated<Login>;
    filters: TableFilters;
    users: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const seeAll = useCan()('manage-login-history');
    const url = loginHistoryRoutes.index();

    const columns: Column<Login>[] = [
        ...(seeAll
            ? [
                  {
                      key: 'user',
                      label: 'User',
                      render: (l: Login) =>
                          l.user && (
                              <PersonCell
                                  name={l.user.name}
                                  detail={l.user.email}
                                  src={l.user.avatar}
                              />
                          ),
                  },
                  {
                      key: 'user_type',
                      label: 'User Type',
                      render: (l: Login) =>
                          l.user?.roles
                              .map((r) => r.label ?? r.name)
                              .join(', '),
                  },
              ]
            : []),
        {
            key: 'ip',
            label: 'IP Address',
            sortable: true,
            render: (l) => l.ip,
        },
        {
            key: 'logged_in_at',
            label: 'Login Date',
            sortable: true,
            render: (l) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {dateTime(l.logged_in_at)}
                </span>
            ),
        },
        {
            key: 'browser',
            label: 'Details',
            sortable: true,
            render: (l) => (
                <div className="text-xs" title={l.user_agent ?? undefined}>
                    <div>{l.browser}</div>
                    <div className="text-muted-foreground">
                        {[l.os, l.device].filter(Boolean).join(' · ')}
                    </div>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Login History')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Login History"
                    description="Successful sign-ins from the last 90 days."
                />

                <DataTable
                    data={loginHistory}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <Input
                                type="date"
                                aria-label={t('Date')}
                                className="w-auto"
                                value={filters.date ?? ''}
                                onChange={(e) =>
                                    applyFilters(url, filters, {
                                        date: e.target.value,
                                    })
                                }
                            />
                            {seeAll && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="user_id"
                                    label="All Users"
                                    options={users}
                                />
                            )}
                        </>
                    }
                />
            </div>
        </>
    );
}

LoginHistory.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Login History', href: loginHistoryRoutes.index() },
    ],
};
