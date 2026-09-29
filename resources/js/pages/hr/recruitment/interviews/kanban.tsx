import { Head, router } from '@inertiajs/react';
import { CalendarDays, List, MapPin, SquareKanban, Users } from 'lucide-react';
import { toast } from 'sonner';
import { KanbanBoard, KanbanSearch } from '@/components/kanban-board';
import { PageHeader } from '@/components/page-header';
import { PersonCell, UserAvatar } from '@/components/user-avatar';
import { FilterSelect } from '@/components/table-filters';
import { ViewToggle } from '@/components/view-toggle';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import interviewRoutes from '@/routes/hr/recruitment/interviews';
import type { TableFilters } from '@/types';

const STATUSES = ['Scheduled', 'Completed', 'Cancelled', 'No-show'] as const;

type Option = { id: number; name: string };
type Person = { id: number; first_name: string; last_name: string };

type Interview = {
    id: number;
    scheduled_date: string;
    scheduled_time: string;
    duration: number;
    location: string | null;
    meeting_link: string | null;
    status: string;
    candidate: (Person & { email: string }) | null;
    job: { id: number; title: string } | null;
    round: Option | null;
    interview_type: Option | null;
    interviewers: (Option & { avatar?: string | null })[];
};

export default function InterviewKanban({
    interviews,
    candidates,
    filters,
}: {
    interviews: Interview[];
    candidates: Person[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const can = useCan();
    const url = interviewRoutes.kanban();

    const move = (interview: Interview, status: string) =>
        router.put(
            interviewRoutes.updateStatus(interview.id),
            { status },
            {
                preserveScroll: true,
                onError: (errors) => toast.error(Object.values(errors)[0]),
            },
        );

    return (
        <>
            <Head title={t('Interviews')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Interviews"
                    description="Schedule and track candidate interviews."
                    action={
                        <ViewToggle
                            current="Kanban"
                            views={[
                                {
                                    label: 'List',
                                    href: interviewRoutes.index(),
                                    icon: List,
                                },
                                {
                                    label: 'Kanban',
                                    href: url,
                                    icon: SquareKanban,
                                },
                            ]}
                        />
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <KanbanSearch url={url} filters={filters} />
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="candidate_id"
                        label="All Candidates"
                        options={candidates.map((c) => ({
                            id: c.id,
                            name: `${c.first_name} ${c.last_name}`,
                        }))}
                    />
                </div>

                <KanbanBoard
                    statuses={STATUSES}
                    items={interviews}
                    canMove={() => can('edit-interviews')}
                    onMove={move}
                    renderCard={(i) => (
                        <>
                            <PersonCell
                                name={
                                    i.candidate
                                        ? `${i.candidate.first_name} ${i.candidate.last_name}`
                                        : '—'
                                }
                                detail={i.job?.title}
                            />
                            <div className="text-xs">
                                {i.round?.name}
                                {i.interview_type &&
                                    ` · ${i.interview_type.name}`}
                            </div>
                            <div className="grid gap-1 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <CalendarDays className="size-3" />
                                    {date(i.scheduled_date)}{' '}
                                    {time(i.scheduled_time)} ({i.duration}{' '}
                                    {t('min')})
                                </span>
                                {(i.location || i.meeting_link) && (
                                    <span className="flex items-center gap-1 truncate">
                                        <MapPin className="size-3 shrink-0" />
                                        {i.location ?? t('Online')}
                                    </span>
                                )}
                                {i.interviewers.length > 0 && (
                                    <span className="flex items-center gap-1">
                                        <Users className="size-3 shrink-0" />
                                        <span className="flex -space-x-1.5">
                                            {i.interviewers.map((u) => (
                                                <span key={u.id} title={u.name}>
                                                    <UserAvatar
                                                        name={u.name}
                                                        src={u.avatar}
                                                        className="size-6 ring-2 ring-card"
                                                    />
                                                </span>
                                            ))}
                                        </span>
                                    </span>
                                )}
                            </div>
                        </>
                    )}
                />
            </div>
        </>
    );
}

InterviewKanban.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: interviewRoutes.index() },
        { title: 'Interviews', href: interviewRoutes.index() },
        { title: 'Kanban', href: interviewRoutes.kanban() },
    ],
};
