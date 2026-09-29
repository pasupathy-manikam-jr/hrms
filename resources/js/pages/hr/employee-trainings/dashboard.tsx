import { Head } from '@inertiajs/react';
import {
    BookOpen,
    CheckCircle2,
    Clock,
    LayoutDashboard,
    List,
    Percent,
    UserPlus,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Panel } from '@/components/dashboard-widgets';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { PersonCell } from '@/components/user-avatar';
import { ViewToggle } from '@/components/view-toggle';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import employeeTrainingRoutes from '@/routes/hr/employee-trainings';

type Training = {
    id: number;
    status: string;
    assigned_date: string;
    completion_date: string | null;
    score: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: { id: number; name: string; avatar: string | null } | null;
    } | null;
    program: { id: number; name: string } | null;
};

type Statistics = {
    totalTrainings: number;
    completedTrainings: number;
    inProgressTrainings: number;
    assignedTrainings: number;
    failedTrainings: number;
    completionRate: number;
};

export default function EmployeeTrainingDashboard({
    statistics,
    programStats,
    recentCompletions,
    upcomingTrainings,
}: {
    statistics: Statistics;
    programStats: {
        name: string;
        total: number;
        completed: number;
        completion_rate: number;
    }[];
    recentCompletions: Training[];
    upcomingTrainings: Training[];
}) {
    const { t } = useTranslation();
    const { date } = useFormat();

    const stats: [string, string | number, LucideIcon][] = [
        ['Total Trainings', statistics.totalTrainings, BookOpen],
        ['Completed', statistics.completedTrainings, CheckCircle2],
        ['In Progress', statistics.inProgressTrainings, Clock],
        ['Assigned', statistics.assignedTrainings, UserPlus],
        ['Failed', statistics.failedTrainings, XCircle],
        ['Completion Rate', `${statistics.completionRate}%`, Percent],
    ];

    const Rows = ({
        items,
        empty,
        dateOf,
    }: {
        items: Training[];
        empty: string;
        dateOf: (item: Training) => string | null;
    }) =>
        items.length === 0 ? (
            <p className="py-10 text-center text-sm text-muted-foreground">
                {t(empty)}
            </p>
        ) : (
            <ul className="divide-y">
                {items.map((item) => {
                    const when = dateOf(item);

                    return (
                        <li
                            key={item.id}
                            className="flex items-center justify-between gap-3 py-2.5"
                        >
                            <PersonCell
                                name={item.employee?.user?.name ?? '—'}
                                src={item.employee?.user?.avatar}
                                gender={item.employee?.gender}
                                detail={item.program?.name}
                            />
                            <div className="grid shrink-0 justify-items-end gap-1 text-xs text-muted-foreground">
                                <StatusBadge status={item.status} />
                                {when && date(when)}
                                {item.score !== null && (
                                    <span>
                                        {t('Score')}: {item.score}
                                    </span>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        );

    return (
        <>
            <Head title={t('Training Dashboard')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Training Dashboard"
                    description="Track training progress and completion across programs."
                    action={
                        <ViewToggle
                            current="Dashboard"
                            views={[
                                {
                                    label: 'List',
                                    href: employeeTrainingRoutes.index(),
                                    icon: List,
                                },
                                {
                                    label: 'Dashboard',
                                    href: employeeTrainingRoutes.dashboard(),
                                    icon: LayoutDashboard,
                                },
                            ]}
                        />
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                    {stats.map(([label, value, Icon]) => (
                        <div
                            key={label}
                            className="flex items-center gap-4 rounded-xl border bg-card p-5 shadow-sm"
                        >
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Icon className="size-5" />
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {t(label)}
                                </div>
                                <div className="text-xl font-bold">{value}</div>
                            </div>
                        </div>
                    ))}
                </div>

                <Panel
                    title={t('Program Statistics')}
                    description={t('Completion rate of the busiest programs')}
                >
                    {programStats.length === 0 && (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            {t('No trainings assigned yet')}
                        </p>
                    )}
                    <ul className="grid gap-4 py-2">
                        {programStats.map((program) => (
                            <li key={program.name} className="grid gap-1.5">
                                <div className="flex justify-between gap-3 text-sm">
                                    <span className="truncate font-medium">
                                        {program.name}
                                    </span>
                                    <span className="shrink-0 text-muted-foreground">
                                        {program.completed}/{program.total} ·{' '}
                                        {program.completion_rate}%
                                    </span>
                                </div>
                                <div
                                    role="progressbar"
                                    aria-label={program.name}
                                    aria-valuenow={program.completion_rate}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    className="h-2 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        className="h-full rounded-full bg-primary"
                                        style={{
                                            width: `${program.completion_rate}%`,
                                        }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </Panel>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title={t('Recent Completions')}
                        description={t('Latest completed trainings')}
                    >
                        <Rows
                            items={recentCompletions}
                            empty="No completed trainings yet"
                            dateOf={(item) => item.completion_date}
                        />
                    </Panel>
                    <Panel
                        title={t('Upcoming Trainings')}
                        description={t('Assigned trainings not started yet')}
                    >
                        <Rows
                            items={upcomingTrainings}
                            empty="No upcoming trainings"
                            dateOf={(item) => item.assigned_date}
                        />
                    </Panel>
                </div>
            </div>
        </>
    );
}

EmployeeTrainingDashboard.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Trainings', href: employeeTrainingRoutes.index() },
        { title: 'Dashboard', href: employeeTrainingRoutes.dashboard() },
    ],
};
