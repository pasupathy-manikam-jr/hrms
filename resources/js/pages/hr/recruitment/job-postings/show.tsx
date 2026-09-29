import { Head, Link } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    CalendarDays,
    MapPin,
    Users,
} from 'lucide-react';
import {
    DetailPage,
    Fields,
    RecordList,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
import { PersonCell } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import candidateRoutes from '@/routes/hr/recruitment/candidates';
import jobPostingRoutes from '@/routes/hr/recruitment/job-postings';

type Named = { id: number; name: string } | null;

type JobPosting = {
    id: number;
    job_code: string | null;
    title: string;
    positions: number;
    min_experience: string;
    max_experience: string | null;
    min_salary: string | null;
    max_salary: string | null;
    description: string | null;
    requirements: string | null;
    benefits: string | null;
    skills: string[] | null;
    start_date: string | null;
    application_deadline: string | null;
    priority: string;
    is_featured: boolean;
    publish_date: string | null;
    status: string;
    category: Named;
    job_type: Named;
    location: Named;
    branch: Named;
    department: Named;
};

type Candidate = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    gender: 'male' | 'female' | 'other' | null;
    experience_years: number;
    status: string;
    application_date: string | null;
    source: Named;
};

export default function JobPostingShow({
    jobPosting: p,
    candidates,
}: {
    jobPosting: JobPosting;
    /** Null when the user may not see applicants. */
    candidates: Candidate[] | null;
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const can = useCan();
    const salary = [p.min_salary, p.max_salary]
        .filter((v): v is string => v !== null)
        .map((v) => money(Number(v)))
        .join(' – ');

    return (
        <>
            <Head title={p.title} />
            <DetailPage
                title={p.title}
                description="View job posting details and its candidates."
                back={jobPostingRoutes.index()}
                summary={
                    <Summary
                        media={<SummaryIcon icon={Briefcase} />}
                        title={p.title}
                        subtitle={p.job_code && <IdBadge>{p.job_code}</IdBadge>}
                        status={p.status}
                        facts={[
                            [Building2, p.department?.name],
                            [MapPin, p.location?.name],
                            [
                                Users,
                                t(':count positions', { count: p.positions }),
                            ],
                            [
                                CalendarDays,
                                p.application_deadline &&
                                    `${t('Deadline')}: ${date(p.application_deadline)}`,
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Overview',
                        heading: 'Job Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        ['Job Category', p.category?.name],
                                        ['Job Type', p.job_type?.name],
                                        ['Location', p.location?.name],
                                        ['Branch', p.branch?.name],
                                        ['Department', p.department?.name],
                                        ['Positions', p.positions],
                                        [
                                            'Experience',
                                            `${p.min_experience}${p.max_experience ? ` – ${p.max_experience}` : '+'} ${t('Years')}`,
                                        ],
                                        ['Salary Range', salary],
                                        ['Priority', t(p.priority)],
                                        [
                                            'Featured Job',
                                            t(p.is_featured ? 'Yes' : 'No'),
                                        ],
                                        [
                                            'Start Date',
                                            <DateCell
                                                key="s"
                                                value={p.start_date}
                                            />,
                                        ],
                                        [
                                            'Application Deadline',
                                            <DateCell
                                                key="d"
                                                value={p.application_deadline}
                                            />,
                                        ],
                                        [
                                            'Publish Date',
                                            <DateCell
                                                key="p"
                                                value={p.publish_date}
                                            />,
                                        ],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="st"
                                                status={p.status}
                                            />,
                                        ],
                                    ]}
                                />
                                {!!p.skills?.length && (
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            {t('Skills')}
                                        </div>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {p.skills.map((skill) => (
                                                <Badge
                                                    key={skill}
                                                    variant="secondary"
                                                >
                                                    {skill}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ),
                    },
                    {
                        label: 'Description',
                        content: (
                            <div className="grid gap-6">
                                <TextBlock
                                    label="Description"
                                    value={p.description}
                                />
                                <TextBlock
                                    label="Requirements"
                                    value={p.requirements}
                                />
                                <TextBlock
                                    label="Benefits"
                                    value={p.benefits}
                                />
                            </div>
                        ),
                    },
                    ...(candidates
                        ? [
                              {
                                  label: 'Candidates',
                                  heading: `${t('Candidates')} (${candidates.length})`,
                                  content: (
                                      <RecordList
                                          items={candidates}
                                          empty="No candidates yet"
                                          render={(c) => {
                                              const name = `${c.first_name} ${c.last_name}`;
                                              const person = (
                                                  <PersonCell
                                                      name={name}
                                                      detail={c.email}
                                                      gender={c.gender}
                                                  />
                                              );

                                              return (
                                                  <>
                                                      {can(
                                                          'view-candidates',
                                                      ) ? (
                                                          <Link
                                                              href={candidateRoutes.show(
                                                                  c.id,
                                                              )}
                                                              className="min-w-0 hover:underline"
                                                          >
                                                              {person}
                                                          </Link>
                                                      ) : (
                                                          person
                                                      )}
                                                      <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                          {c.source?.name}
                                                          <DateCell
                                                              value={
                                                                  c.application_date
                                                              }
                                                          />
                                                          <StatusBadge
                                                              status={c.status}
                                                          />
                                                      </div>
                                                  </>
                                              );
                                          }}
                                      />
                                  ),
                              },
                          ]
                        : []),
                ]}
            />
        </>
    );
}

JobPostingShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: jobPostingRoutes.index() },
        { title: 'Job Postings', href: jobPostingRoutes.index() },
        { title: 'Job Posting Details', href: jobPostingRoutes.index() },
    ],
};
