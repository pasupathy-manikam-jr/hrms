import { Head, usePage } from '@inertiajs/react';
import { AlertTriangle, MessageSquareWarning, Trophy } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AttendanceClockCard } from '@/components/attendance-clock-card';
import type {
    ClockShift,
    TodayAttendance,
} from '@/components/attendance-clock-card';
import {
    AnnouncementsPanel,
    DashboardHeader,
    greeting,
    MeetingsPanel,
} from '@/components/dashboard-widgets';
import type { Announcement, Meeting } from '@/components/dashboard-widgets';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type EmployeeDashboardData = {
    stats: {
        totalAwards: number;
        totalWarnings: number;
        totalComplaints: number;
    };
    recentActivities: {
        announcements: Announcement[];
        meetings: Meeting[];
    };
};

function StatCard({
    label,
    value,
    footer,
    icon: Icon,
    tone,
}: {
    label: string;
    value: number;
    footer: string;
    icon: LucideIcon;
    tone: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-4 rounded-xl border p-5',
                tone,
            )}
        >
            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-current/10">
                <Icon className="size-5" />
            </div>
            <div>
                <div className="text-sm font-medium">{label}</div>
                <div className="text-2xl font-bold">{value}</div>
                <div className="text-xs">{footer}</div>
            </div>
        </div>
    );
}

export default function EmployeeDashboard({
    dashboardData: { stats, recentActivities },
    todayAttendance,
    shift,
}: {
    dashboardData: EmployeeDashboardData;
    todayAttendance: TodayAttendance;
    shift: ClockShift;
}) {
    const { auth } = usePage().props;
    const { time } = useFormat();
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <DashboardHeader
                    description={t(
                        'Your personal overview — attendance, announcements and meetings.',
                    )}
                />

                <section className="flex flex-col gap-4 rounded-2xl bg-slate-800 p-6 text-white md:flex-row md:items-center md:justify-between">
                    <div>
                        <p className="text-slate-300">{t(greeting())},</p>
                        <p className="text-3xl font-bold">
                            {auth.user?.name} 👋
                        </p>
                        <p className="mt-1 text-sm text-slate-300">
                            {t("Here's your personal overview for today.")}
                        </p>
                    </div>
                    {shift && (
                        <div className="rounded-xl bg-white/10 px-4 py-2 text-center">
                            <div className="font-semibold">{shift.name}</div>
                            <div className="text-xs text-slate-300">
                                {time(shift.start_time)} -{' '}
                                {time(shift.end_time)}
                            </div>
                        </div>
                    )}
                </section>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        label={t('Total Awards')}
                        value={stats.totalAwards}
                        footer={t('recognitions received')}
                        icon={Trophy}
                        tone="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                    />
                    <StatCard
                        label={t('Total Warnings')}
                        value={stats.totalWarnings}
                        footer={t('issued to you')}
                        icon={AlertTriangle}
                        tone="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                    />
                    <StatCard
                        label={t('Total Complaints')}
                        value={stats.totalComplaints}
                        footer={t('filed against you')}
                        icon={MessageSquareWarning}
                        tone="border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                    />
                </div>

                <AttendanceClockCard
                    todayAttendance={todayAttendance}
                    shift={shift}
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <AnnouncementsPanel
                        items={recentActivities.announcements}
                    />
                    <MeetingsPanel items={recentActivities.meetings} />
                </div>
            </div>
        </>
    );
}

EmployeeDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
