import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, GraduationCap, Percent, Users } from 'lucide-react';
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
import { DateCell } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import trainingAssessmentRoutes from '@/routes/hr/training-assessments';
import trainingProgramRoutes from '@/routes/hr/training-programs';

type TrainingAssessment = {
    id: number;
    name: string;
    description: string | null;
    type: string;
    passing_score: string;
    criteria: string | null;
    created_at: string;
    program: { id: number; name: string } | null;
};

type Result = {
    id: number;
    score: string;
    is_passed: boolean;
    feedback: string | null;
    assessment_date: string;
    assessor: { id: number; name: string } | null;
    employee_training: {
        id: number;
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
    };
};

const label = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function TrainingAssessmentShow({
    trainingAssessment: a,
    results,
    statistics,
}: {
    trainingAssessment: TrainingAssessment;
    results: Result[];
    statistics: { total: number; passed: number; averageScore: number | null };
}) {
    const { t } = useTranslation();
    const passRate = statistics.total
        ? Math.round((statistics.passed / statistics.total) * 100)
        : 0;

    return (
        <>
            <Head title={a.name} />
            <DetailPage
                title={a.name}
                description="View the assessment and each employee's result."
                back={trainingAssessmentRoutes.index()}
                summary={
                    <>
                        <Summary
                            media={<SummaryIcon icon={ClipboardCheck} />}
                            title={a.name}
                            subtitle={t(label(a.type))}
                            facts={[
                                [GraduationCap, a.program?.name],
                                [
                                    Percent,
                                    `${t('Passing Score')}: ${Number(a.passing_score)}%`,
                                ],
                                [
                                    Users,
                                    t(':passed of :total passed', {
                                        passed: statistics.passed,
                                        total: statistics.total,
                                    }),
                                ],
                            ]}
                        />
                        <div className="mt-5 w-full text-start">
                            <div className="mb-2 text-sm text-muted-foreground">
                                {t('Pass Rate')}
                            </div>
                            <ProgressBar value={passRate} />
                        </div>
                    </>
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Assessment Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        [
                                            'Training Program',
                                            a.program && (
                                                <Link
                                                    href={trainingProgramRoutes.show(
                                                        a.program.id,
                                                    )}
                                                    className="text-primary hover:underline"
                                                >
                                                    {a.program.name}
                                                </Link>
                                            ),
                                        ],
                                        ['Type', t(label(a.type))],
                                        [
                                            'Passing Score',
                                            `${Number(a.passing_score)}%`,
                                        ],
                                        ['Results', statistics.total],
                                        [
                                            'Average Score',
                                            statistics.averageScore !== null &&
                                                `${statistics.averageScore}%`,
                                        ],
                                        ['Pass Rate', `${passRate}%`],
                                        [
                                            'Created At',
                                            <DateCell
                                                key="c"
                                                value={a.created_at}
                                            />,
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Description"
                                    value={a.description}
                                />
                                <TextBlock
                                    label="Criteria"
                                    value={a.criteria}
                                />
                            </div>
                        ),
                    },
                    {
                        label: 'Results',
                        heading: `${t('Results')} (${results.length})`,
                        content: (
                            <RecordList
                                items={results}
                                empty="No assessment results yet."
                                render={(r) => {
                                    const employee =
                                        r.employee_training.employee;

                                    return (
                                        <>
                                            <PersonCell
                                                name={employee.user.name}
                                                detail={employee.user.email}
                                                src={employee.user.avatar}
                                                gender={employee.gender}
                                            />
                                            <div className="flex items-center gap-4 text-sm">
                                                <DateCell
                                                    value={r.assessment_date}
                                                />
                                                {r.assessor && (
                                                    <span className="text-muted-foreground">
                                                        {r.assessor.name}
                                                    </span>
                                                )}
                                                <span className="font-medium">
                                                    {Number(r.score)}%
                                                </span>
                                                <StatusBadge
                                                    status={
                                                        r.is_passed
                                                            ? 'passed'
                                                            : 'failed'
                                                    }
                                                />
                                            </div>
                                            {r.feedback && (
                                                <p className="w-full text-sm text-muted-foreground">
                                                    {r.feedback}
                                                </p>
                                            )}
                                        </>
                                    );
                                }}
                            />
                        ),
                    },
                ]}
            />
        </>
    );
}

TrainingAssessmentShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Training & Development',
            href: trainingAssessmentRoutes.index(),
        },
        {
            title: 'Training Assessments',
            href: trainingAssessmentRoutes.index(),
        },
        {
            title: 'Training Assessment Details',
            href: trainingAssessmentRoutes.index(),
        },
    ],
};
