import { Head, router } from '@inertiajs/react';
import { CalendarDays, ListChecks, Mail } from 'lucide-react';
import {
    DetailPage,
    Fields,
    ProgressBar,
    Summary,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { PersonCell, UserAvatar } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import onboardingRoutes from '@/routes/hr/recruitment/candidate-onboarding';

type Gender = 'male' | 'female' | 'other' | null;

type Task = {
    id: number;
    task_name: string;
    description: string | null;
    category: string;
    assigned_to_role: string | null;
    due_date: string;
    is_required: boolean;
    status: 'pending' | 'completed';
    completed_at: string | null;
};

type Onboarding = {
    id: number;
    start_date: string;
    status: string;
    progress: number;
    candidate: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        phone: string | null;
        gender: Gender;
        job: { id: number; title: string } | null;
    };
    checklist: { id: number; name: string } | null;
    buddy: {
        id: number;
        employee_id: string;
        gender: Gender;
        user: {
            id: number;
            name: string;
            email: string;
            avatar: string | null;
        };
    } | null;
    tasks: Task[];
};

export default function CandidateOnboardingShow({
    onboarding: o,
}: {
    onboarding: Onboarding;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const canUpdateTasks = can('manage-candidate-onboarding-status');
    const name = `${o.candidate.first_name} ${o.candidate.last_name}`;
    const done = o.tasks.filter((task) => task.status === 'completed').length;

    const toggleTask = (task: Task, completed: boolean) =>
        router.put(
            onboardingRoutes.tasks.update({
                candidateOnboarding: o.id,
                task: task.id,
            }),
            { completed },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title={name} />
            <DetailPage
                title={name}
                description="Track the onboarding checklist and its progress."
                back={onboardingRoutes.index()}
                summary={
                    <>
                        <Summary
                            media={
                                <UserAvatar
                                    name={name}
                                    gender={o.candidate.gender}
                                    className="size-32"
                                />
                            }
                            title={name}
                            subtitle={o.candidate.job?.title}
                            status={o.status}
                            facts={[
                                [Mail, o.candidate.email],
                                [ListChecks, o.checklist?.name],
                                [
                                    CalendarDays,
                                    `${t('Start Date')}: ${date(o.start_date)}`,
                                ],
                            ]}
                        />
                        <div className="mt-5 w-full text-start">
                            <div className="mb-2 text-sm text-muted-foreground">
                                {t(':done of :total tasks done', {
                                    done,
                                    total: o.tasks.length,
                                })}
                            </div>
                            <ProgressBar value={o.progress} />
                        </div>
                    </>
                }
                tabs={[
                    {
                        label: 'Tasks',
                        heading: 'Onboarding Checklist',
                        content:
                            o.tasks.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('This onboarding has no tasks.')}
                                </p>
                            ) : (
                                <ul className="divide-y rounded-lg border text-sm">
                                    {o.tasks.map((task) => (
                                        <li
                                            key={task.id}
                                            className="flex items-start gap-3 p-3"
                                        >
                                            <Checkbox
                                                id={`task-${task.id}`}
                                                className="mt-0.5"
                                                checked={
                                                    task.status === 'completed'
                                                }
                                                disabled={!canUpdateTasks}
                                                onCheckedChange={(checked) =>
                                                    toggleTask(
                                                        task,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <div className="grid flex-1 gap-1">
                                                <label
                                                    htmlFor={`task-${task.id}`}
                                                    className="flex flex-wrap items-center gap-2 font-medium"
                                                >
                                                    <span
                                                        className={
                                                            task.status ===
                                                            'completed'
                                                                ? 'text-muted-foreground line-through'
                                                                : undefined
                                                        }
                                                    >
                                                        {task.task_name}
                                                    </span>
                                                    <Badge variant="outline">
                                                        {t(task.category)}
                                                    </Badge>
                                                    {!task.is_required && (
                                                        <StatusBadge status="optional" />
                                                    )}
                                                </label>
                                                {task.description && (
                                                    <p className="text-muted-foreground">
                                                        {task.description}
                                                    </p>
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    {[
                                                        t('Due :date', {
                                                            date: date(
                                                                task.due_date,
                                                            ),
                                                        }),
                                                        task.assigned_to_role,
                                                        task.completed_at &&
                                                            t('Done :date', {
                                                                date: date(
                                                                    task.completed_at,
                                                                ),
                                                            }),
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            ),
                    },
                    {
                        label: 'Details',
                        heading: 'Onboarding Details',
                        content: (
                            <Fields
                                items={[
                                    ['Employee', name],
                                    ['Email', o.candidate.email],
                                    ['Phone', o.candidate.phone],
                                    ['Job Posting', o.candidate.job?.title],
                                    ['Checklist', o.checklist?.name],
                                    [
                                        'Start Date',
                                        <DateCell
                                            key="sd"
                                            value={o.start_date}
                                        />,
                                    ],
                                    [
                                        'Buddy',
                                        o.buddy && (
                                            <PersonCell
                                                name={o.buddy.user.name}
                                                detail={o.buddy.user.email}
                                                src={o.buddy.user.avatar}
                                                gender={o.buddy.gender}
                                            />
                                        ),
                                    ],
                                    [
                                        'Status',
                                        <StatusBadge
                                            key="s"
                                            status={o.status}
                                        />,
                                    ],
                                ]}
                            />
                        ),
                    },
                ]}
            />
        </>
    );
}

CandidateOnboardingShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: onboardingRoutes.index() },
        { title: 'Candidate Onboarding', href: onboardingRoutes.index() },
        { title: 'Onboarding Details', href: onboardingRoutes.index() },
    ],
};
