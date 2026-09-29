import { Head, router } from '@inertiajs/react';
import { Briefcase, CalendarDays, List, SquareKanban } from 'lucide-react';
import { toast } from 'sonner';
import { KanbanBoard, KanbanSearch } from '@/components/kanban-board';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { FilterSelect } from '@/components/table-filters';
import { ViewToggle } from '@/components/view-toggle';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import candidateRoutes from '@/routes/hr/recruitment/candidates';
import type { TableFilters } from '@/types';

const STATUSES = [
    'New',
    'Screening',
    'Interview',
    'Offer',
    'Hired',
    'Rejected',
] as const;

type Candidate = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    experience_years: number;
    expected_salary: string | null;
    application_date: string | null;
    status: string;
    job: { id: number; title: string; job_code: string | null } | null;
    source: { id: number; name: string } | null;
};

export default function CandidateKanban({
    candidates,
    jobPostings,
    sources,
    filters,
}: {
    candidates: Candidate[];
    jobPostings: { id: number; title: string }[];
    sources: { id: number; name: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const can = useCan();
    const url = candidateRoutes.kanban();

    // Candidates have no status-only endpoint: resend the record with the new status through update().
    const move = (candidate: Candidate, status: string) =>
        router.put(
            candidateRoutes.update(candidate.id),
            { ...candidate, job: undefined, source: undefined, status },
            {
                preserveScroll: true,
                onError: (errors) => toast.error(Object.values(errors)[0]),
            },
        );

    return (
        <>
            <Head title={t('Candidates')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Candidates"
                    description="Track applicants through your hiring pipeline."
                    action={
                        <ViewToggle
                            current="Kanban"
                            views={[
                                {
                                    label: 'List',
                                    href: candidateRoutes.index(),
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
                        name="job_id"
                        label="All Jobs"
                        options={jobPostings.map((job) => ({
                            id: job.id,
                            name: job.title,
                        }))}
                    />
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="source_id"
                        label="All Sources"
                        options={sources}
                    />
                </div>

                <KanbanBoard
                    statuses={STATUSES}
                    items={candidates}
                    canMove={() => can('edit-candidates')}
                    onMove={move}
                    renderCard={(c) => (
                        <>
                            <PersonCell
                                name={`${c.first_name} ${c.last_name}`}
                                detail={c.email}
                            />
                            {c.job && (
                                <div className="flex items-center gap-1.5 text-xs">
                                    <Briefcase className="size-3.5 text-muted-foreground" />
                                    {c.job.title}
                                </div>
                            )}
                            <div className="flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <span>
                                    {c.experience_years} {t('Years')}
                                </span>
                                {c.expected_salary !== null && (
                                    <span>
                                        {money(Number(c.expected_salary))}
                                    </span>
                                )}
                                {c.source && <span>{c.source.name}</span>}
                                {c.application_date && (
                                    <span className="flex items-center gap-1">
                                        <CalendarDays className="size-3" />
                                        {date(c.application_date)}
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

CandidateKanban.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: candidateRoutes.index() },
        { title: 'Candidates', href: candidateRoutes.index() },
        { title: 'Kanban', href: candidateRoutes.kanban() },
    ],
};
