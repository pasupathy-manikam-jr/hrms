import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Clock, GraduationCap, MapPin } from 'lucide-react';
import {
    DetailPage,
    Fields,
    RecordList,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import employeeTrainingRoutes from '@/routes/hr/employee-trainings';
import trainingProgramRoutes from '@/routes/hr/training-programs';
import trainingSessionRoutes from '@/routes/hr/training-sessions';

type Employee = {
    id: number;
    employee_id: string;
    gender: 'male' | 'female' | 'other' | null;
    user: { id: number; name: string; email: string; avatar: string | null };
};

type TrainingSession = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    location_type: string;
    location: string | null;
    meeting_link: string | null;
    status: string;
    notes: string | null;
    program: { id: number; name: string } | null;
    trainers: Employee[];
};

type Participant = {
    id: number;
    status: string;
    score: string | null;
    employee: Employee;
};

const label = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

function EmployeeRow({ employee }: { employee: Employee }) {
    return (
        <PersonCell
            name={employee.user.name}
            detail={employee.user.email}
            src={employee.user.avatar}
            gender={employee.gender}
        />
    );
}

export default function TrainingSessionShow({
    trainingSession: s,
    participants,
}: {
    trainingSession: TrainingSession;
    participants: Participant[];
}) {
    const { t } = useTranslation();
    const { date, dateTime, time } = useFormat();
    const clock = (value: string) => time(value.slice(11, 16));

    return (
        <>
            <Head title={s.name} />
            <DetailPage
                title={s.name}
                description="View the training session, its trainers and participants."
                back={trainingSessionRoutes.index()}
                summary={
                    <Summary
                        media={<SummaryIcon icon={GraduationCap} />}
                        title={s.name}
                        subtitle={s.program?.name}
                        status={s.status}
                        facts={[
                            [CalendarDays, date(s.start_date)],
                            [
                                Clock,
                                `${clock(s.start_date)} – ${clock(s.end_date)}`,
                            ],
                            [MapPin, s.location ?? t(label(s.location_type))],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Session Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        [
                                            'Training Program',
                                            s.program && (
                                                <Link
                                                    href={trainingProgramRoutes.show(
                                                        s.program.id,
                                                    )}
                                                    className="text-primary hover:underline"
                                                >
                                                    {s.program.name}
                                                </Link>
                                            ),
                                        ],
                                        [
                                            'Location Type',
                                            t(label(s.location_type)),
                                        ],
                                        ['Start', dateTime(s.start_date)],
                                        ['End', dateTime(s.end_date)],
                                        ['Location', s.location],
                                        [
                                            'Meeting Link',
                                            s.meeting_link && (
                                                <a
                                                    href={s.meeting_link}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="break-all text-primary hover:underline"
                                                >
                                                    {s.meeting_link}
                                                </a>
                                            ),
                                        ],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="s"
                                                status={s.status}
                                            />,
                                        ],
                                    ]}
                                />
                                <TextBlock label="Notes" value={s.notes} />
                            </div>
                        ),
                    },
                    {
                        label: 'Trainers',
                        content: (
                            <RecordList
                                items={s.trainers}
                                empty="No trainers assigned"
                                render={(trainer) => (
                                    <>
                                        <EmployeeRow employee={trainer} />
                                        <IdBadge>{trainer.employee_id}</IdBadge>
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Participants',
                        heading: `${t('Participants')} (${participants.length})`,
                        content: (
                            <RecordList
                                items={participants}
                                empty="No participants yet"
                                render={(p) => (
                                    <>
                                        <Link
                                            href={employeeTrainingRoutes.show(
                                                p.id,
                                            )}
                                            className="min-w-0 hover:underline"
                                        >
                                            <EmployeeRow
                                                employee={p.employee}
                                            />
                                        </Link>
                                        <div className="flex items-center gap-4 text-sm">
                                            <IdBadge>
                                                {p.employee.employee_id}
                                            </IdBadge>
                                            {p.score !== null && (
                                                <span className="font-medium">
                                                    {Number(p.score)}%
                                                </span>
                                            )}
                                            <StatusBadge status={p.status} />
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

TrainingSessionShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Training & Development',
            href: trainingSessionRoutes.index(),
        },
        { title: 'Training Sessions', href: trainingSessionRoutes.index() },
        {
            title: 'Training Session Details',
            href: trainingSessionRoutes.index(),
        },
    ],
};
