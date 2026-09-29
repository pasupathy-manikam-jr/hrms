import { Head, Link } from '@inertiajs/react';
import { Award, CalendarDays, GraduationCap, Mail } from 'lucide-react';
import {
    DetailPage,
    Fields,
    RecordList,
    Summary,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { UserAvatar } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import employeeTrainingRoutes from '@/routes/hr/employee-trainings';
import trainingProgramRoutes from '@/routes/hr/training-programs';

type Result = {
    id: number;
    score: string;
    is_passed: boolean;
    feedback: string | null;
    assessment_date: string;
    assessment: {
        id: number;
        name: string;
        type: string;
        passing_score: string;
    } | null;
    assessor: { id: number; name: string } | null;
};

type EmployeeTraining = {
    id: number;
    status: string;
    assigned_date: string;
    completion_date: string | null;
    score: string | null;
    certification: boolean;
    feedback: string | null;
    notes: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: {
            id: number;
            name: string;
            email: string;
            avatar: string | null;
        };
    };
    program: {
        id: number;
        name: string;
        training_type: { id: number; name: string } | null;
    };
    session: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
    } | null;
    results: Result[];
};

export default function EmployeeTrainingShow({
    employeeTraining: e,
}: {
    employeeTraining: EmployeeTraining;
}) {
    const { t } = useTranslation();
    const { date, dateTime } = useFormat();

    return (
        <>
            <Head title={e.program.name} />
            <DetailPage
                title={e.program.name}
                description="View the training assignment, its result and assessments."
                back={employeeTrainingRoutes.index()}
                summary={
                    <Summary
                        media={
                            <UserAvatar
                                name={e.employee.user.name}
                                src={e.employee.user.avatar}
                                gender={e.employee.gender}
                                className="size-32"
                            />
                        }
                        title={e.employee.user.name}
                        subtitle={<IdBadge>{e.employee.employee_id}</IdBadge>}
                        status={e.status}
                        facts={[
                            [Mail, e.employee.user.email],
                            [GraduationCap, e.program.name],
                            [
                                CalendarDays,
                                `${t('Assigned')}: ${date(e.assigned_date)}`,
                            ],
                            [
                                Award,
                                e.score !== null &&
                                    `${t('Score')}: ${Number(e.score)}%`,
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Assignment',
                        heading: 'Assignment Details',
                        content: (
                            <Fields
                                items={[
                                    [
                                        'Training Program',
                                        <Link
                                            key="p"
                                            href={trainingProgramRoutes.show(
                                                e.program.id,
                                            )}
                                            className="text-primary hover:underline"
                                        >
                                            {e.program.name}
                                        </Link>,
                                    ],
                                    [
                                        'Training Type',
                                        e.program.training_type?.name,
                                    ],
                                    [
                                        'Training Session',
                                        e.session &&
                                            `${e.session.name} (${dateTime(e.session.start_date)})`,
                                    ],
                                    [
                                        'Assigned Date',
                                        <DateCell
                                            key="a"
                                            value={e.assigned_date}
                                        />,
                                    ],
                                    [
                                        'Status',
                                        <StatusBadge
                                            key="s"
                                            status={e.status}
                                        />,
                                    ],
                                ]}
                            />
                        ),
                    },
                    {
                        label: 'Result',
                        heading: 'Training Result',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        [
                                            'Completion Date',
                                            <DateCell
                                                key="c"
                                                value={e.completion_date}
                                            />,
                                        ],
                                        [
                                            'Score',
                                            e.score !== null &&
                                                `${Number(e.score)}%`,
                                        ],
                                        [
                                            'Certificate Issued',
                                            t(e.certification ? 'Yes' : 'No'),
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Feedback"
                                    value={e.feedback}
                                />
                                <TextBlock label="Notes" value={e.notes} />
                            </div>
                        ),
                    },
                    {
                        label: 'Assessment Results',
                        content: (
                            <RecordList
                                items={e.results}
                                empty="No assessment results yet."
                                render={(result) => (
                                    <>
                                        <div>
                                            <div className="font-medium">
                                                {result.assessment?.name}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {date(result.assessment_date)}
                                                {result.assessor &&
                                                    ` · ${result.assessor.name}`}
                                                {result.feedback &&
                                                    ` · ${result.feedback}`}
                                            </div>
                                        </div>
                                        <span className="flex items-center gap-2 text-sm">
                                            {Number(result.score)}%
                                            {result.assessment &&
                                                ` / ${Number(result.assessment.passing_score)}%`}
                                            <StatusBadge
                                                status={
                                                    result.is_passed
                                                        ? 'passed'
                                                        : 'failed'
                                                }
                                            />
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

EmployeeTrainingShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Training & Development',
            href: employeeTrainingRoutes.index(),
        },
        { title: 'Employee Trainings', href: employeeTrainingRoutes.index() },
        {
            title: 'Employee Training Details',
            href: employeeTrainingRoutes.index(),
        },
    ],
};
