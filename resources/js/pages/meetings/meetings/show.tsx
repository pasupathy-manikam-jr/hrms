import { Head } from '@inertiajs/react';
import { CalendarDays, Clock, MapPin, Repeat, Users } from 'lucide-react';
import {
    DetailPage,
    Fields,
    RecordList,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import meetingRoutes from '@/routes/meetings/meetings';

type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};

type Meeting = {
    id: number;
    title: string;
    description: string | null;
    meeting_date: string;
    start_time: string;
    end_time: string;
    duration: number;
    agenda: string | null;
    status: string;
    recurrence: string;
    recurrence_end_date: string | null;
    type: { id: number; name: string; color: string } | null;
    room: {
        id: number;
        name: string;
        type: string;
        location: string | null;
        capacity: number;
    } | null;
    organizer: Person | null;
    attendees: (Person & {
        pivot: {
            type: string;
            rsvp_status: string;
            attendance_status: string;
            rsvp_date: string | null;
            decline_reason: string | null;
        };
    })[];
};

type Minute = {
    id: number;
    topic: string;
    content: string;
    type: string;
    recorded_at: string | null;
    recorder: Person | null;
};

type ActionItem = {
    id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    priority: string;
    status: string;
    progress_percentage: number;
    assignee: Person | null;
};

export default function MeetingShow({
    meeting: m,
    minutes,
    actionItems,
}: {
    meeting: Meeting;
    minutes: Minute[];
    actionItems: ActionItem[];
}) {
    const { t } = useTranslation();
    const { date, time, dateTime } = useFormat();
    const hours = `${time(m.start_time)} - ${time(m.end_time)}`;

    return (
        <>
            <Head title={m.title} />
            <DetailPage
                title={m.title}
                description="View the meeting, its attendees, minutes and action items."
                back={meetingRoutes.index()}
                summary={
                    <Summary
                        media={<SummaryIcon icon={Users} />}
                        title={m.title}
                        subtitle={m.type?.name}
                        status={m.status}
                        facts={[
                            [CalendarDays, date(m.meeting_date)],
                            [Clock, hours],
                            [MapPin, m.room?.name],
                            [
                                Repeat,
                                m.recurrence !== 'None'
                                    ? t(m.recurrence)
                                    : null,
                            ],
                            [
                                Users,
                                t(':count attendees', {
                                    count: m.attendees.length,
                                }),
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Meeting Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        ['Meeting Type', m.type?.name],
                                        [
                                            'Meeting Room',
                                            m.room &&
                                                [m.room.name, m.room.location]
                                                    .filter(Boolean)
                                                    .join(' · '),
                                        ],
                                        [
                                            'Date',
                                            <DateCell
                                                key="d"
                                                value={m.meeting_date}
                                            />,
                                        ],
                                        ['Time', hours],
                                        [
                                            'Duration',
                                            t(':minutes min', {
                                                minutes: m.duration,
                                            }),
                                        ],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="s"
                                                status={m.status}
                                            />,
                                        ],
                                        ['Recurrence', t(m.recurrence)],
                                        [
                                            'Recurrence End Date',
                                            m.recurrence_end_date && (
                                                <DateCell
                                                    value={
                                                        m.recurrence_end_date
                                                    }
                                                />
                                            ),
                                        ],
                                        [
                                            'Organizer',
                                            m.organizer && (
                                                <PersonCell
                                                    name={m.organizer.name}
                                                    detail={m.organizer.email}
                                                    src={m.organizer.avatar}
                                                />
                                            ),
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Description"
                                    value={m.description}
                                />
                                <TextBlock label="Agenda" value={m.agenda} />
                            </div>
                        ),
                    },
                    {
                        label: 'Attendees',
                        content: (
                            <RecordList
                                items={m.attendees}
                                empty="No attendees yet"
                                render={(a) => (
                                    <>
                                        <PersonCell
                                            name={a.name}
                                            detail={a.email}
                                            src={a.avatar}
                                        />
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            <StatusBadge
                                                status={a.pivot.type}
                                            />
                                            <span className="text-muted-foreground">
                                                {t('RSVP')}:
                                            </span>
                                            <StatusBadge
                                                status={a.pivot.rsvp_status}
                                            />
                                            <span className="text-muted-foreground">
                                                {t('Attendance')}:
                                            </span>
                                            <StatusBadge
                                                status={
                                                    a.pivot.attendance_status
                                                }
                                            />
                                        </div>
                                        {a.pivot.decline_reason && (
                                            <p className="w-full text-sm text-muted-foreground">
                                                {a.pivot.decline_reason}
                                            </p>
                                        )}
                                    </>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Minutes',
                        heading: 'Meeting Minutes',
                        content: (
                            <RecordList
                                items={minutes}
                                empty="No minutes recorded yet"
                                render={(minute) => (
                                    <div className="grid w-full gap-2">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex items-center gap-2 font-medium">
                                                {minute.topic}
                                                <StatusBadge
                                                    status={minute.type}
                                                />
                                            </div>
                                            <span className="text-sm whitespace-nowrap text-muted-foreground">
                                                {minute.recorder?.name}
                                                {minute.recorded_at &&
                                                    ` · ${dateTime(minute.recorded_at)}`}
                                            </span>
                                        </div>
                                        <p className="text-sm whitespace-pre-line">
                                            {minute.content}
                                        </p>
                                    </div>
                                )}
                            />
                        ),
                    },
                    {
                        label: 'Action Items',
                        content: (
                            <RecordList
                                items={actionItems}
                                empty="No action items yet"
                                render={(item) => (
                                    <>
                                        <div className="grid min-w-0 gap-1">
                                            <div className="font-medium">
                                                {item.title}
                                            </div>
                                            {item.assignee && (
                                                <PersonCell
                                                    name={item.assignee.name}
                                                    detail={item.assignee.email}
                                                    src={item.assignee.avatar}
                                                />
                                            )}
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            <DateCell value={item.due_date} />
                                            <StatusBadge
                                                status={item.priority}
                                            />
                                            <StatusBadge status={item.status} />
                                            <span className="text-muted-foreground">
                                                {item.progress_percentage}%
                                            </span>
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

MeetingShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Meetings', href: meetingRoutes.index() },
        { title: 'Meeting Details', href: meetingRoutes.index() },
    ],
};
