import { Head, Link } from '@inertiajs/react';
import {
    Banknote,
    Boxes,
    CalendarDays,
    ChartColumn,
    CircleCheck,
    List,
    Package,
    Trash2,
    TrendingDown,
    TrendingUp,
    UserCheck,
    Wrench,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Panel, ViewAll } from '@/components/dashboard-widgets';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { PersonCell } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import assetRoutes from '@/routes/hr/assets';

type Slice = { name: string; value: number; color: string };

type Assignment = {
    id: number;
    assigned_at: string;
    returned_at: string | null;
    asset: { id: number; name: string; asset_code: string | null };
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | null;
        user: { name: string; avatar: string | null };
    };
};

const axis = { fontSize: 12, stroke: 'var(--muted-foreground)' };

type Maintenance = {
    id: number;
    maintenance_type: string;
    start_date: string;
    end_date: string;
    status: string;
    asset: { id: number; name: string };
};

type RecentAsset = {
    id: number;
    name: string;
    asset_code: string | null;
    serial_number: string | null;
    status: string;
    location: string | null;
    purchase_date: string | null;
    purchase_cost: string;
    asset_type: { id: number; name: string } | null;
    current_assignment: {
        employee: {
            gender: 'male' | 'female' | null;
            user: { name: string; email: string; avatar: string | null };
        };
    } | null;
};

/** Status cards, in the order of the status chart (Available, Assigned, Maintenance, Disposed). */
const STATUS_CARDS = [
    {
        label: 'Available',
        icon: CircleCheck,
        tone: 'bg-emerald-50 text-emerald-600',
        note: 'text-emerald-600',
    },
    {
        label: 'Assigned',
        icon: UserCheck,
        tone: 'bg-blue-50 text-blue-600',
        note: 'text-blue-600',
    },
    {
        label: 'Under Maintenance',
        icon: Wrench,
        tone: 'bg-amber-50 text-amber-600',
        note: 'text-amber-600',
    },
    {
        label: 'Disposed',
        icon: Trash2,
        tone: 'bg-red-50 text-red-600',
        note: 'text-red-600',
    },
];

function StatusCard({
    label,
    value,
    note,
    icon: Icon,
    tone,
    noteTone,
}: {
    label: string;
    value: number;
    note: string;
    icon: typeof Boxes;
    tone: string;
    noteTone: string;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex items-start justify-between gap-3 rounded-xl border bg-card p-5 shadow-sm">
            <div>
                <div className="text-sm text-muted-foreground">{t(label)}</div>
                <div className="mt-1 text-2xl font-bold">{value}</div>
                <div className={cn('mt-1 text-xs', noteTone)}>{note}</div>
            </div>
            <span
                className={cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-xl',
                    tone,
                )}
            >
                <Icon className="size-5" />
            </span>
        </div>
    );
}

export default function AssetDashboard({
    statusStats,
    typeStats,
    totals,
    valueTrend,
    recentAssignments,
    maintenance,
    recentAssets,
}: {
    statusStats: Slice[];
    typeStats: { name: string; count: number }[];
    totals: {
        assets: number;
        purchase_value: number;
        current_value: number;
        depreciation: number;
        monthly_depreciation: number;
    };
    valueTrend: { month: string; value: number }[];
    recentAssignments: Assignment[];
    maintenance: Maintenance[];
    recentAssets: RecentAsset[];
}) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const total = statusStats.reduce((sum, s) => sum + s.value, 0);
    const share = (value: number) =>
        `${total ? Math.round((value / total) * 100) : 0}% ${t('of total')}`;

    return (
        <>
            <Head title={t('Asset Dashboard')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Asset Dashboard"
                    description="Overview of company assets, their status and value."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={assetRoutes.index()}>
                                    <List /> {t('Asset List')}
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={assetRoutes.depreciationReport()}>
                                    <ChartColumn /> {t('Depreciation Report')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatusCard
                        label="Total Assets"
                        value={totals.assets}
                        note={t('All registered assets')}
                        icon={Boxes}
                        tone="bg-muted text-muted-foreground"
                        noteTone="text-muted-foreground"
                    />
                    {STATUS_CARDS.map((card, i) => (
                        <StatusCard
                            key={card.label}
                            label={card.label}
                            value={statusStats[i]?.value ?? 0}
                            note={share(statusStats[i]?.value ?? 0)}
                            icon={card.icon}
                            tone={card.tone}
                            noteTone={card.note}
                        />
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title={t('Assets by Status')}
                        description={t('Current status of every asset')}
                    >
                        <div className="grid items-center gap-6 sm:grid-cols-2">
                            <div className="relative h-52">
                                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                    <span className="text-2xl font-bold">
                                        {total}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {t('Total Assets')}
                                    </span>
                                </div>
                                <ResponsiveContainer>
                                    <PieChart>
                                        <Pie
                                            data={statusStats.map((s) => ({
                                                ...s,
                                                name: t(s.name),
                                            }))}
                                            dataKey="value"
                                            nameKey="name"
                                            innerRadius="55%"
                                            outerRadius="90%"
                                            paddingAngle={2}
                                            stroke="none"
                                        >
                                            {statusStats.map((slice) => (
                                                <Cell
                                                    key={slice.name}
                                                    fill={slice.color}
                                                />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                    </PieChart>
                                </ResponsiveContainer>
                            </div>
                            <ul className="space-y-2 text-sm">
                                {statusStats.map((slice) => (
                                    <li
                                        key={slice.name}
                                        className="flex items-center gap-2"
                                    >
                                        <span
                                            className="size-2.5 rounded-full"
                                            style={{
                                                backgroundColor: slice.color,
                                            }}
                                        />
                                        <span className="flex-1 text-muted-foreground">
                                            {t(slice.name)}
                                        </span>
                                        <span className="font-semibold">
                                            {slice.value}
                                        </span>
                                        <span className="w-9 text-end text-xs text-muted-foreground">
                                            {total
                                                ? Math.round(
                                                      (slice.value / total) *
                                                          100,
                                                  )
                                                : 0}
                                            %
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </Panel>

                    <Panel
                        title={t('Assets by Type')}
                        description={t('Number of assets in each type')}
                    >
                        <div className="h-52">
                            <ResponsiveContainer>
                                <BarChart data={typeStats}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        vertical={false}
                                    />
                                    <XAxis
                                        dataKey="name"
                                        {...axis}
                                        interval={0}
                                        tickLine={false}
                                        tickFormatter={(name: string) =>
                                            name.length > 10
                                                ? `${name.slice(0, 9)}…`
                                                : name
                                        }
                                    />
                                    <YAxis
                                        {...axis}
                                        allowDecimals={false}
                                        tickLine={false}
                                        width={30}
                                    />
                                    <Tooltip />
                                    <Bar
                                        dataKey="count"
                                        name={t('Assets')}
                                        fill="#3B82F6"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </Panel>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title={t('Recent Assignments')}
                        description={t(
                            'Latest assets checked out to employees',
                        )}
                        action={<ViewAll href={assetRoutes.index()} />}
                    >
                        {recentAssignments.length === 0 && (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                {t('No assignments yet')}
                            </p>
                        )}
                        <ul className="divide-y dark:divide-gray-800">
                            {recentAssignments.map((a) => (
                                <li
                                    key={a.id}
                                    className="flex items-center gap-3 py-2.5"
                                >
                                    <div className="min-w-0 flex-1">
                                        <PersonCell
                                            name={a.employee.user.name}
                                            detail={a.asset.name}
                                            src={a.employee.user.avatar}
                                            gender={a.employee.gender}
                                        />
                                    </div>
                                    <div className="grid justify-items-end gap-1 text-sm text-muted-foreground">
                                        <StatusBadge
                                            status={
                                                a.returned_at
                                                    ? 'returned'
                                                    : 'assigned'
                                            }
                                        />
                                        <span className="whitespace-nowrap">
                                            {date(a.assigned_at)}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Panel>

                    <Panel
                        title={t('Maintenance Schedule')}
                        description={t(
                            'Upcoming and ongoing asset maintenance',
                        )}
                    >
                        {maintenance.length === 0 && (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                {t('No maintenance scheduled')}
                            </p>
                        )}
                        <ul className="divide-y dark:divide-gray-800">
                            {maintenance.map((m) => (
                                <li
                                    key={m.id}
                                    className="flex items-center gap-3 py-2.5"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                        <Wrench className="size-5" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">
                                            {m.asset.name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {t(m.maintenance_type)}
                                        </div>
                                    </div>
                                    <div className="grid justify-items-end gap-1 text-sm text-muted-foreground">
                                        <StatusBadge status={m.status} />
                                        <span className="whitespace-nowrap">
                                            {date(m.start_date)}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </div>

                <Panel
                    title={t('Asset Value Overview (12 Months)')}
                    description={t(
                        'Total book value of assets at each month end',
                    )}
                >
                    <div className="h-64">
                        <ResponsiveContainer>
                            <AreaChart data={valueTrend}>
                                <CartesianGrid
                                    strokeDasharray="3 3"
                                    vertical={false}
                                />
                                <XAxis
                                    dataKey="month"
                                    {...axis}
                                    tickLine={false}
                                />
                                <YAxis
                                    {...axis}
                                    tickLine={false}
                                    width={70}
                                    tickFormatter={(v: number) =>
                                        `${Math.round(v / 1000)}k`
                                    }
                                />
                                <Tooltip formatter={(v) => money(Number(v))} />
                                <Area
                                    type="monotone"
                                    dataKey="value"
                                    name={t('Book Value')}
                                    stroke="var(--primary)"
                                    fill="var(--primary)"
                                    fillOpacity={0.12}
                                    strokeWidth={2}
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </Panel>

                <Panel
                    title={t('Depreciation Summary')}
                    description={t(
                        'Straight-line depreciation across all assets',
                    )}
                >
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {(
                            [
                                [
                                    'Purchase Value',
                                    totals.purchase_value,
                                    Banknote,
                                    'bg-blue-50 text-blue-700 border-blue-100',
                                ],
                                [
                                    'Accumulated Depr.',
                                    totals.depreciation,
                                    TrendingDown,
                                    'bg-red-50 text-red-700 border-red-100',
                                ],
                                [
                                    'Book Value',
                                    totals.current_value,
                                    Package,
                                    'bg-emerald-50 text-emerald-700 border-emerald-100',
                                ],
                                [
                                    'Monthly Depr.',
                                    totals.monthly_depreciation,
                                    TrendingUp,
                                    'bg-purple-50 text-purple-700 border-purple-100',
                                ],
                            ] as const
                        ).map(([label, value, Icon, tone]) => (
                            <div
                                key={label}
                                className={cn('rounded-xl border p-4', tone)}
                            >
                                <Icon className="size-5" />
                                <div className="mt-3 text-xs font-medium">
                                    {t(label)}
                                </div>
                                <div className="text-xl font-bold tabular-nums">
                                    {money(value)}
                                </div>
                            </div>
                        ))}
                    </div>
                </Panel>

                <Panel
                    title={t('Recent Assets')}
                    description={t('Latest assets added to the register')}
                    action={<ViewAll href={assetRoutes.index()} />}
                >
                    <div className="-mx-6 overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-muted-foreground">
                                <tr>
                                    {[
                                        'Name',
                                        'Asset Code / Serial',
                                        'Status',
                                        'Assigned To',
                                        'Location',
                                        'Purchase Date',
                                        'Value',
                                    ].map((h) => (
                                        <th
                                            key={h}
                                            className="px-4 py-3 text-start font-medium first:ps-6 last:pe-6"
                                        >
                                            {t(h)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentAssets.map((a) => (
                                    <tr key={a.id}>
                                        <td className="px-4 py-3 ps-6">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                    <Package className="size-4" />
                                                </span>
                                                <div>
                                                    <div className="font-medium">
                                                        {a.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {a.asset_type?.name}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div>{a.asset_code ?? '—'}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {a.serial_number}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={a.status} />
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.current_assignment ? (
                                                <PersonCell
                                                    name={
                                                        a.current_assignment
                                                            .employee.user.name
                                                    }
                                                    detail={
                                                        a.current_assignment
                                                            .employee.user.email
                                                    }
                                                    src={
                                                        a.current_assignment
                                                            .employee.user
                                                            .avatar
                                                    }
                                                    gender={
                                                        a.current_assignment
                                                            .employee.gender
                                                    }
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.location ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.purchase_date && (
                                                <span className="flex items-center gap-2 whitespace-nowrap">
                                                    <CalendarDays className="size-4 text-muted-foreground" />
                                                    {date(a.purchase_date)}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 pe-6 font-medium whitespace-nowrap tabular-nums">
                                            {money(Number(a.purchase_cost))}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>
            </div>
        </>
    );
}

AssetDashboard.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Asset Management', href: assetRoutes.dashboard() },
        { title: 'Asset Dashboard', href: assetRoutes.dashboard() },
    ],
};
