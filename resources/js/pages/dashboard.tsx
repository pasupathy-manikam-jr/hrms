import { Head, Link, router, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import {
    ArrowUpRight,
    Briefcase,
    Check,
    Copy,
    ExternalLink,
    Building2,
    CalendarDays,
    Clock,
    Banknote,
    Gift,
    Package,
    Settings,
    TrendingUp,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    AnnouncementsPanel,
    DashboardHeader,
    greeting,
    MeetingsPanel,
    Panel,
    ViewAll,
} from '@/components/dashboard-widgets';
import { StatusBadge } from '@/components/status-badge';
import type { Announcement, Meeting } from '@/components/dashboard-widgets';
import { Badge } from '@/components/ui/badge';
import { SelectField } from '@/components/select-field';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { dashboard, settings } from '@/routes';
import career from '@/routes/career';
import hr from '@/routes/hr';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type Slice = { name: string; value: number; color: string };

type DashboardData = {
    stats: {
        totalEmployees: number;
        totalBranches: number;
        totalDepartments: number;
        newEmployeesThisMonth: number;
        jobPostsThisMonth: number;
        attendanceRate: number;
        presentToday: number;
        pendingLeaves: number;
        onLeaveToday: number;
        activeJobPostings: number;
        totalPayrollThisMonth: number;
        payrollRunsThisMonth: number;
    };
    charts: {
        attendanceWeekly: {
            day: string;
            present: number;
            absent: number;
            leave: number;
        }[];
        leaveOverview: Slice[];
        hiringTrend: { short: string; hires: number }[];
        hiringYear: number;
        payrollTrend: { month: string; netPay: number }[];
        payrollYear: number;
        availableYears: number[];
        assetStatusStats: Slice[];
        candidateStatusStats: Slice[];
    };
    recentActivities: {
        leaves: {
            id: number;
            employee: string;
            leave_type: string;
            start_date: string;
            status: string;
        }[];
        candidates: {
            id: number;
            name: string;
            job: string | null;
            application_date: string;
            status: string;
        }[];
        announcements: Announcement[];
        meetings: Meeting[];
    };
    todayBirthdays: { id: number; name: string; designation: string }[];
    todayOnLeave: {
        id: number;
        name: string;
        designation: string;
        leaveType: string;
    }[];
};

function Person({
    name,
    detail,
    trailing,
}: {
    name: string;
    detail: string;
    trailing?: ReactNode;
}) {
    const getInitials = useInitials();

    return (
        <li className="flex items-center gap-3 py-2.5">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                {getInitials(name)}
            </div>
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{name}</div>
                <div className="truncate text-sm text-muted-foreground">
                    {detail}
                </div>
            </div>
            {trailing}
        </li>
    );
}

function StatCard({
    label,
    value,
    footer,
    icon: Icon,
    tone,
    href,
    children,
}: {
    label: string;
    value: string | number;
    footer?: ReactNode;
    icon: LucideIcon;
    tone: string;
    href: NonNullable<InertiaLinkProps['href']>;
    children?: ReactNode;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'group relative rounded-xl border p-4 transition-shadow hover:shadow-md',
                tone,
            )}
        >
            <ArrowUpRight className="absolute end-4 top-4 size-4 opacity-40 group-hover:opacity-100" />
            <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-current/10">
                <Icon className="size-5" />
            </div>
            <div className="text-sm font-medium">{label}</div>
            <div className="text-xl font-bold break-all xl:text-2xl">
                {value}
            </div>
            {children}
            {footer && <div className="mt-1 text-xs">{footer}</div>}
        </Link>
    );
}

function DonutLegend({ data }: { data: Slice[] }) {
    const { t } = useTranslation();
    const total = data.reduce((sum, slice) => sum + slice.value, 0);

    return (
        <ul className="space-y-2 text-sm">
            {data.map((slice) => (
                <li key={slice.name} className="flex items-center gap-2">
                    <span
                        className="size-2.5 rounded-full"
                        style={{ backgroundColor: slice.color }}
                    />
                    <span className="flex-1 text-muted-foreground">
                        {t(slice.name)}
                    </span>
                    <span className="font-semibold">{slice.value}</span>
                    <span className="w-9 text-right text-xs text-muted-foreground">
                        {total ? Math.round((slice.value / total) * 100) : 0}%
                    </span>
                </li>
            ))}
        </ul>
    );
}

function Donut({ data, hole = true }: { data: Slice[]; hole?: boolean }) {
    return (
        <div className="grid items-center gap-6 sm:grid-cols-2">
            <div className="h-52">
                <ResponsiveContainer>
                    <PieChart>
                        <Pie
                            data={data}
                            dataKey="value"
                            nameKey="name"
                            innerRadius={hole ? '55%' : 0}
                            outerRadius="90%"
                            paddingAngle={2}
                            stroke="none"
                        >
                            {data.map((slice) => (
                                <Cell key={slice.name} fill={slice.color} />
                            ))}
                        </Pie>
                        <Tooltip />
                    </PieChart>
                </ResponsiveContainer>
            </div>
            <DonutLegend data={data} />
        </div>
    );
}

// Reloads only the dashboard data for another year; the other chart keeps its year via the URL.
function YearSelect({
    label,
    value,
    years,
    param,
}: {
    label: string;
    value: number;
    years: number[];
    param: 'hiring_year' | 'payroll_year';
}) {
    return (
        <SelectField
            aria-label={label}
            value={value}
            className="h-8 w-24"
            onChange={(e) =>
                router.reload({
                    data: { [param]: e.target.value },
                    only: ['dashboardData'],
                })
            }
        >
            {years.map((year) => (
                <option key={year} value={year}>
                    {year}
                </option>
            ))}
        </SelectField>
    );
}

const axis = { fontSize: 12, stroke: 'var(--muted-foreground)' };

/** The demo's "Career Page" chip: the public careers site with its open jobs, copy link and open buttons. */
function CareerPageChip({ openJobs }: { openJobs: number }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const url = new URL(career.index.url(), window.location.origin).toString();

    return (
        <div className="flex items-center gap-6 border-slate-600 md:border-e md:pe-6">
            <div className="flex items-center gap-3 rounded-xl bg-slate-700/70 px-3 py-2">
                <span className="relative flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <Briefcase className="size-4" />
                    <span className="absolute -end-0.5 -top-0.5 size-2 rounded-full bg-emerald-400 ring-2 ring-slate-700" />
                </span>
                <div className="leading-tight">
                    <div className="text-xs font-semibold">
                        {t('Career Page')}
                    </div>
                    <div className="text-xs text-primary">
                        {t(':count open', { count: openJobs })}
                    </div>
                </div>
                <button
                    type="button"
                    aria-label={t('Copy link')}
                    title={t(copied ? 'Copied!' : 'Copy link')}
                    onClick={() =>
                        navigator.clipboard.writeText(url).then(() => {
                            setCopied(true);
                            setTimeout(() => setCopied(false), 2000);
                        })
                    }
                    className="rounded-md bg-slate-600 p-1.5 text-slate-200 hover:bg-slate-500"
                >
                    {copied ? (
                        <Check className="size-3.5" />
                    ) : (
                        <Copy className="size-3.5" />
                    )}
                </button>
                <a
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={t('Open career page')}
                    title={t('Open career page')}
                    className="rounded-md bg-primary p-1.5 text-primary-foreground hover:bg-primary/90"
                >
                    <ExternalLink className="size-3.5" />
                </a>
            </div>
        </div>
    );
}

export default function Dashboard({
    dashboardData: { stats, charts, recentActivities, ...today },
}: {
    dashboardData: DashboardData;
}) {
    const { auth } = usePage().props;
    const { money, date } = useFormat();
    const { t } = useTranslation();
    const leaveTotal = charts.leaveOverview.reduce((s, x) => s + x.value, 0);
    const hiringTotal = charts.hiringTrend.reduce((s, x) => s + x.hires, 0);
    const payrollTotal = charts.payrollTrend.reduce((s, x) => s + x.netPay, 0);

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <DashboardHeader
                    description={t(
                        'Overview of your company stats, attendance, and recent activity.',
                    )}
                />

                <section className="relative overflow-hidden rounded-2xl bg-slate-800 p-6 text-white md:flex md:items-center md:justify-between">
                    <div>
                        <p className="text-slate-300">{t(greeting())},</p>
                        <p className="text-3xl font-bold">
                            {auth.user?.name} 👋
                        </p>
                        <p className="mt-1 text-sm text-slate-300">
                            {t(
                                "Here's what's happening across your company today.",
                            )}
                        </p>
                        <p className="mt-4 flex items-center gap-2 font-semibold text-primary">
                            <span className="flex gap-1">
                                <span className="size-2 animate-pulse rounded-full bg-primary" />
                                <span className="size-2 rounded-full bg-primary/70" />
                                <span className="size-2 rounded-full bg-primary/40" />
                            </span>
                            {stats.presentToday} {t('present today')}
                        </p>
                    </div>
                    <nav className="mt-6 flex flex-wrap items-center gap-6 md:mt-0">
                        {auth.permissions.includes('manage-career-page') && (
                            <CareerPageChip
                                openJobs={stats.activeJobPostings}
                            />
                        )}
                        {[
                            {
                                label: 'Jobs',
                                icon: Briefcase,
                                href: hr.recruitment.jobPostings.index(),
                                permission: 'manage-job-postings',
                            },
                            {
                                label: 'Candidates',
                                icon: UserPlus,
                                href: hr.recruitment.candidates.index(),
                                permission: 'manage-candidates',
                            },
                            {
                                label: 'Settings',
                                icon: Settings,
                                href: settings(),
                                permission: 'manage-settings',
                            },
                        ]
                            .filter((link) =>
                                auth.permissions.includes(link.permission),
                            )
                            .map(({ label, icon: Icon, href }) => (
                                <Link
                                    key={label}
                                    href={href}
                                    className="flex flex-col items-center gap-1 text-xs text-slate-300 hover:text-white"
                                >
                                    <Icon className="size-5 text-amber-300" />
                                    {t(label)}
                                </Link>
                            ))}
                    </nav>
                </section>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <StatCard
                        label={t('Payroll This Month')}
                        value={money(stats.totalPayrollThisMonth)}
                        footer={`${stats.payrollRunsThisMonth} ${t('runs completed')}`}
                        icon={Banknote}
                        tone="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                        href={hr.payrollRuns.index()}
                    />
                    <StatCard
                        label={t('Total Employees')}
                        value={stats.totalEmployees}
                        footer={
                            <span className="flex items-center gap-1">
                                <TrendingUp className="size-3" />
                                {stats.newEmployeesThisMonth} {t('this month')}
                            </span>
                        }
                        icon={Users}
                        tone="border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300"
                        href={hr.employees.index()}
                    />
                    <StatCard
                        label={t('Branches')}
                        value={stats.totalBranches}
                        footer={`${stats.totalDepartments} ${t('departments')}`}
                        icon={Building2}
                        tone="border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-300"
                        href={hr.branches.index()}
                    />
                    <StatCard
                        label={t('Attendance Rate')}
                        value={`${stats.attendanceRate}%`}
                        icon={Clock}
                        tone="border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300"
                        href={hr.attendanceRecords.index()}
                    >
                        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-violet-100 dark:bg-violet-900">
                            <div
                                className="h-full rounded-full bg-violet-600"
                                style={{ width: `${stats.attendanceRate}%` }}
                            />
                        </div>
                    </StatCard>
                    <StatCard
                        label={t('Pending Leaves')}
                        value={stats.pendingLeaves}
                        footer={`${stats.onLeaveToday} ${t('on leave today')}`}
                        icon={CalendarDays}
                        tone="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                        href={hr.leaveApplications.index()}
                    />
                    <StatCard
                        label={t('Active Jobs')}
                        value={stats.activeJobPostings}
                        footer={
                            <span className="flex items-center gap-1">
                                <TrendingUp className="size-3" />
                                {stats.jobPostsThisMonth} {t('this month')}
                            </span>
                        }
                        icon={Briefcase}
                        tone="border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300"
                        href={hr.recruitment.jobPostings.index()}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title={t("Today's Birthdays")}
                        description={t('Celebrate with your team')}
                        action={<Gift className="size-5 text-pink-500" />}
                    >
                        <ul className="divide-y dark:divide-gray-800">
                            {today.todayBirthdays.length === 0 && (
                                <li className="py-8 text-center text-sm text-muted-foreground">
                                    {t('No birthdays today')}
                                </li>
                            )}
                            {today.todayBirthdays.map((person) => (
                                <Person
                                    key={person.id}
                                    name={person.name}
                                    detail={person.designation}
                                />
                            ))}
                        </ul>
                    </Panel>
                    <Panel
                        title={t("Today's Leave")}
                        description={t('Employees on leave today')}
                        action={
                            <CalendarDays className="size-5 text-amber-500" />
                        }
                    >
                        <ul className="divide-y dark:divide-gray-800">
                            {today.todayOnLeave.length === 0 && (
                                <li className="py-8 text-center text-sm text-muted-foreground">
                                    {t('No one is on leave today')}
                                </li>
                            )}
                            {today.todayOnLeave.map((person) => (
                                <Person
                                    key={person.id}
                                    name={person.name}
                                    detail={person.designation}
                                    trailing={
                                        <Badge
                                            variant="outline"
                                            className="border-amber-200 bg-amber-50 text-amber-700"
                                        >
                                            {person.leaveType}
                                        </Badge>
                                    }
                                />
                            ))}
                        </ul>
                    </Panel>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title={t('Attendance - Last 7 Days')}
                        description={t('Daily present / absent / on leave')}
                    >
                        <div className="h-72">
                            <ResponsiveContainer>
                                <BarChart data={charts.attendanceWeekly}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        vertical={false}
                                    />
                                    <XAxis dataKey="day" {...axis} />
                                    <YAxis {...axis} />
                                    <Tooltip />
                                    <Legend
                                        iconType="circle"
                                        itemSorter={null}
                                        formatter={(value) => (
                                            <span className="text-sm text-muted-foreground">
                                                {value}
                                            </span>
                                        )}
                                    />
                                    <Bar
                                        dataKey="present"
                                        name={t('Present')}
                                        stackId="a"
                                        fill="#10B981"
                                    />
                                    <Bar
                                        dataKey="leave"
                                        name={t('On Leave')}
                                        stackId="a"
                                        fill="#F59E0B"
                                    />
                                    <Bar
                                        dataKey="absent"
                                        name={t('Absent')}
                                        stackId="a"
                                        fill="#EF4444"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </Panel>
                    <Panel
                        title={t('Leave Overview')}
                        description={t('Applications this year by status')}
                        action={
                            <Badge
                                variant="outline"
                                className="border-amber-200 bg-amber-50 text-amber-700"
                            >
                                {leaveTotal} {t('total')}
                            </Badge>
                        }
                    >
                        <Donut data={charts.leaveOverview} />
                    </Panel>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title={t('Recent Leave Applications')}
                        description={t('Latest leave requests from employees')}
                        action={<ViewAll href={hr.leaveApplications.index()} />}
                    >
                        <ul className="divide-y dark:divide-gray-800">
                            {recentActivities.leaves.map((leave) => (
                                <Person
                                    key={leave.id}
                                    name={leave.employee}
                                    detail={`${leave.leave_type} • ${date(leave.start_date)}`}
                                    trailing={
                                        <StatusBadge status={leave.status} />
                                    }
                                />
                            ))}
                        </ul>
                    </Panel>
                    <Panel
                        title={t('Recent Candidates')}
                        description={t('Latest applicants in the pipeline')}
                        action={
                            <ViewAll href={hr.recruitment.candidates.index()} />
                        }
                    >
                        <ul className="divide-y dark:divide-gray-800">
                            {recentActivities.candidates.map((candidate) => (
                                <Person
                                    key={candidate.id}
                                    name={candidate.name}
                                    detail={`${candidate.job ?? '—'} • ${date(candidate.application_date)}`}
                                    trailing={
                                        <StatusBadge
                                            status={candidate.status}
                                        />
                                    }
                                />
                            ))}
                        </ul>
                    </Panel>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <AnnouncementsPanel
                        items={recentActivities.announcements}
                    />
                    <MeetingsPanel items={recentActivities.meetings} />
                </div>

                <Panel
                    title={t('Hiring Trend')}
                    description={`${t('New hires per month')} - ${charts.hiringYear}`}
                    action={
                        <div className="flex items-center gap-2">
                            <Badge
                                variant="outline"
                                className="border-orange-200 bg-orange-50 text-orange-700"
                            >
                                {hiringTotal} {t('total')}
                            </Badge>
                            <YearSelect
                                label={t('Hiring Trend')}
                                value={charts.hiringYear}
                                years={charts.availableYears}
                                param="hiring_year"
                            />
                        </div>
                    }
                >
                    <div className="h-72">
                        <ResponsiveContainer>
                            <BarChart
                                data={charts.hiringTrend}
                                margin={{ top: 20 }}
                            >
                                <CartesianGrid
                                    strokeDasharray="3 3"
                                    vertical={false}
                                />
                                <XAxis dataKey="short" {...axis} />
                                <YAxis {...axis} />
                                <Tooltip />
                                <Bar
                                    dataKey="hires"
                                    name={t('Hires')}
                                    fill="#10B981"
                                    fillOpacity={0.7}
                                    radius={[4, 4, 0, 0]}
                                >
                                    <LabelList
                                        dataKey="hires"
                                        position="top"
                                        className="fill-primary text-xs font-semibold"
                                    />
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </Panel>

                <Panel
                    title={t('Payroll Trend')}
                    description={`${t('Monthly net pay')} - ${charts.payrollYear}`}
                    action={
                        <div className="flex items-center gap-2">
                            <Badge
                                variant="outline"
                                className="border-emerald-200 bg-emerald-50 text-emerald-700"
                            >
                                {money(payrollTotal)}
                            </Badge>
                            <YearSelect
                                label={t('Payroll Trend')}
                                value={charts.payrollYear}
                                years={charts.availableYears}
                                param="payroll_year"
                            />
                        </div>
                    }
                >
                    <div className="h-72">
                        <ResponsiveContainer>
                            <AreaChart data={charts.payrollTrend}>
                                <defs>
                                    <linearGradient
                                        id="payroll"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stopColor="#10B981"
                                            stopOpacity={0.25}
                                        />
                                        <stop
                                            offset="100%"
                                            stopColor="#10B981"
                                            stopOpacity={0}
                                        />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid
                                    strokeDasharray="3 3"
                                    vertical={false}
                                />
                                <XAxis dataKey="month" {...axis} />
                                <YAxis
                                    {...axis}
                                    width={90}
                                    tickFormatter={(v: number) => money(v)}
                                />
                                <Tooltip formatter={(v) => money(Number(v))} />
                                <Area
                                    type="monotone"
                                    dataKey="netPay"
                                    name={t('Net Pay')}
                                    stroke="#10B981"
                                    strokeWidth={2}
                                    fill="url(#payroll)"
                                    dot={{ r: 3, fill: '#10B981' }}
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </Panel>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title={t('Asset Status')}
                        description={t('Distribution by current status')}
                        action={<Package className="size-5 text-cyan-600" />}
                    >
                        <Donut data={charts.assetStatusStats} />
                    </Panel>
                    <Panel
                        title={t('Candidate Pipeline')}
                        description={t('Candidates by status')}
                        action={<UserPlus className="size-5 text-blue-600" />}
                    >
                        <Donut
                            data={charts.candidateStatusStats}
                            hole={false}
                        />
                    </Panel>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
