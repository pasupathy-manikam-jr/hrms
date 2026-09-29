import { Head, Link } from '@inertiajs/react';
import { Banknote, Clock, GraduationCap, Tag, Users } from 'lucide-react';
import {
    DetailPage,
    Fields,
    ProgressBar,
    RecordList,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { PersonCell, UserAvatar } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import employeeTrainingRoutes from '@/routes/hr/employee-trainings';
import trainingAssessmentRoutes from '@/routes/hr/training-assessments';
import trainingProgramRoutes from '@/routes/hr/training-programs';
import trainingSessionRoutes from '@/routes/hr/training-sessions';

type Employee = {
    id: number;
    employee_id: string;
    gender: 'male' | 'female' | 'other' | null;
    user: { id: number; name: string; email: string; avatar: string | null };
};

type TrainingProgram = {
    id: number;
    name: string;
    description: string | null;
    duration: number | null;
    cost: string;
    capacity: number | null;
    status: string;
    prerequisites: string | null;
    is_mandatory: boolean;
    is_self_enrollment: boolean;
    created_at: string;
    training_type: { id: number; name: string } | null;
};

type Session = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    location: string | null;
    location_type: string;
    status: string;
    trainers: Employee[];
};

type Enrollment = {
    id: number;
    status: string;
    assigned_date: string;
    completion_date: string | null;
    score: string | null;
    employee: Employee;
};

type Assessment = {
    id: number;
    name: string;
    type: string;
    passing_score: string;
};

export default function TrainingProgramShow({
    trainingProgram: p,
    sessions,
    enrollments,
    assessments,
}: {
    trainingProgram: TrainingProgram;
    sessions: Session[];
    enrollments: Enrollment[];
    assessments: Assessment[];
}) {
    const { t } = useTranslation();
    const { dateTime, money } = useFormat();
    const can = useCan();
    const completed = enrollments.filter((e) => e.status === 'completed');
    const completion = enrollments.length
        ? Math.round((completed.length / enrollments.length) * 100)
        : 0;

    return (
        <>
            <Head title={p.name} />
            <DetailPage
                title={p.name}
                description="View the training program, its sessions and enrolled employees."
                back={trainingProgramRoutes.index()}
                summary={
                    <>
                        <Summary
                            media={<SummaryIcon icon={GraduationCap} />}
                            title={p.name}
                            subtitle={p.training_type?.name}
                            status={p.status}
                            facts={[
                                [Tag, p.training_type?.name],
                                [
                                    Clock,
                                    p.duration &&
                                        t(':hours hours', {
                                            hours: p.duration,
                                        }),
                                ],
                                [Banknote, money(Number(p.cost))],
                                [
                                    Users,
                                    t(':count enrolled', {
                                        count: enrollments.length,
                                    }),
                                ],
                            ]}
                        />
                        <div className="mt-5 w-full text-start">
                            <div className="mb-2 text-sm text-muted-foreground">
                                {t('Completion')}
                            </div>
                            <ProgressBar value={completion} />
                        </div>
                    </>
                }
                tabs={[
                    {
                        label: 'Overview',
                        heading: 'Program Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        [
                                            'Training Type',
                                            p.training_type?.name,
                                        ],
                                        [
                                            'Duration',
                                            p.duration &&
                                                t(':hours hours', {
                                                    hours: p.duration,
                                                }),
                                        ],
                                        ['Cost', money(Number(p.cost))],
                                        ['Capacity', p.capacity],
                                        [
                                            'Mandatory',
                                            t(p.is_mandatory ? 'Yes' : 'No'),
                                        ],
                                        [
                                            'Self Enrollment',
                                            t(
                                                p.is_self_enrollment
                                                    ? 'Yes'
                                                    : 'No',
                                            ),
                                        ],
                                        ['Prerequisites', p.prerequisites],
                                        [
                                            'Created At',
                                            <DateCell
                                                key="c"
                                                value={p.created_at}
                                            />,
                                        ],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="s"
                                                status={p.status}
                                            />,
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Description"
                                    value={p.description}
                                />
                            </div>
                        ),
                    },
                    {
                        label: 'Sessions',
                        heading: `${t('Sessions')} (${sessions.length})`,
                        content: (
                            <RecordList
                                items={sessions}
                                empty="No sessions yet"
                                render={(s) => (
                                    <>
                                        <div>
                                            <Link
                                                href={trainingSessionRoutes.show(
                                                    s.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {s.name}
                                            </Link>
                                            <div className="text-sm text-muted-foreground">
                                                {dateTime(s.start_date)} –{' '}
                                                {dateTime(s.end_date)}
                                                {s.location &&
                                                    ` · ${s.location}`}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <div className="flex -space-x-2">
                                                {s.trainers.map((trainer) => (
                                                    <span
                                                        key={trainer.id}
                                                        title={
                                                            trainer.user.name
                                                        }
                                                    >
                                                        <UserAvatar
                                                            name={
                                                                trainer.user
                                                                    .name
                                                            }
                                                            src={
                                                                trainer.user
                                                                    .avatar
                                                            }
                                                            gender={
                                                                trainer.gender
                                                            }
                                                            className="size-8 ring-2 ring-card"
                                                        />
                                                    </span>
                                                ))}
                                            </div>
                                            <StatusBadge status={s.status} />
                                        </div>
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Enrolled Employees',
                        heading: `${t('Enrolled Employees')} (${enrollments.length})`,
                        content: (
                            <RecordList
                                items={enrollments}
                                empty="No employees enrolled yet"
                                render={(e) => (
                                    <>
                                        <Link
                                            href={employeeTrainingRoutes.show(
                                                e.id,
                                            )}
                                            className="min-w-0 hover:underline"
                                        >
                                            <PersonCell
                                                name={e.employee.user.name}
                                                detail={e.employee.user.email}
                                                src={e.employee.user.avatar}
                                                gender={e.employee.gender}
                                            />
                                        </Link>
                                        <div className="flex items-center gap-4 text-sm">
                                            <IdBadge>
                                                {e.employee.employee_id}
                                            </IdBadge>
                                            <DateCell value={e.assigned_date} />
                                            {e.score !== null && (
                                                <span className="font-medium">
                                                    {Number(e.score)}%
                                                </span>
                                            )}
                                            <StatusBadge status={e.status} />
                                        </div>
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Assessments',
                        content: (
                            <RecordList
                                items={assessments}
                                empty="No assessments yet"
                                render={(a) => (
                                    <>
                                        {can('manage-training-assessments') ? (
                                            <Link
                                                href={trainingAssessmentRoutes.show(
                                                    a.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {a.name}
                                            </Link>
                                        ) : (
                                            <span className="font-medium">
                                                {a.name}
                                            </span>
                                        )}
                                        <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                            <StatusBadge status={a.type} />
                                            {t('Passing Score')}:{' '}
                                            {Number(a.passing_score)}%
                                        </div>
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

TrainingProgramShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Training & Development',
            href: trainingProgramRoutes.index(),
        },
        { title: 'Training Programs', href: trainingProgramRoutes.index() },
        {
            title: 'Training Program Details',
            href: trainingProgramRoutes.index(),
        },
    ],
};
