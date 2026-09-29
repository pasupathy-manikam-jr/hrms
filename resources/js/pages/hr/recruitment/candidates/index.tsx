import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, List, SquareKanban, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { ViewToggle } from '@/components/view-toggle';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import candidateRoutes from '@/routes/hr/recruitment/candidates';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Status =
    | 'New'
    | 'Screening'
    | 'Interview'
    | 'Offer'
    | 'Hired'
    | 'Rejected';

type Candidate = {
    id: number;
    job_id: number;
    source_id: number | null;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    gender: string | null;
    date_of_birth: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    zip_code: string | null;
    country: string | null;
    current_company: string | null;
    current_position: string | null;
    experience_years: number;
    current_salary: string | null;
    expected_salary: string | null;
    final_salary: string | null;
    notice_period: string | null;
    skills: string | null;
    education: string | null;
    portfolio_url: string | null;
    linkedin_url: string | null;
    status: Status;
    application_date: string | null;
    job: { id: number; title: string; job_code: string | null } | null;
    source: Option | null;
};

const STATUSES: Status[] = [
    'New',
    'Screening',
    'Interview',
    'Offer',
    'Hired',
    'Rejected',
];

const TEXT_FIELDS = [
    ['first_name', 'First Name', 'text'],
    ['last_name', 'Last Name', 'text'],
    ['email', 'Email', 'email'],
    ['phone', 'Phone', 'text'],
    ['date_of_birth', 'Date of Birth', 'date'],
    ['application_date', 'Application Date', 'date'],
    ['address', 'Address', 'text'],
    ['city', 'City', 'text'],
    ['state', 'State', 'text'],
    ['zip_code', 'Postcode', 'text'],
    ['country', 'Country', 'text'],
    ['current_company', 'Current Company', 'text'],
    ['current_position', 'Current Position', 'text'],
    ['experience_years', 'Experience (Years)', 'number'],
    ['notice_period', 'Notice Period', 'text'],
    ['current_salary', 'Current Salary', 'number'],
    ['expected_salary', 'Expected Salary', 'number'],
    ['final_salary', 'Final Salary', 'number'],
    ['portfolio_url', 'Portfolio URL', 'url'],
    ['linkedin_url', 'LinkedIn URL', 'url'],
    ['skills', 'Skills', 'text'],
    ['education', 'Education', 'text'],
] as const;

const REQUIRED = new Set([
    'first_name',
    'last_name',
    'email',
    'experience_years',
]);

type TextKey = (typeof TEXT_FIELDS)[number][0];

const blank = {
    ...(Object.fromEntries(TEXT_FIELDS.map(([key]) => [key, ''])) as Record<
        TextKey,
        string
    >),
    job_id: '' as number | '',
    source_id: '' as number | '',
    gender: '',
    status: 'New' as Status,
};

export default function Candidates({
    candidates,
    statusCounts,
    jobPostings,
    sources,
    filters,
}: {
    candidates: Paginated<Candidate>;
    statusCounts: Record<string, number>;
    jobPostings: { id: number; title: string; job_code: string | null }[];
    sources: Option[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Candidate | null>(null);
    const [deleting, setDeleting] = useState<Candidate | null>(null);
    const form = useForm(blank);
    const url = candidateRoutes.index();
    const jobOptions = jobPostings.map((job) => ({
        id: job.id,
        name: job.title,
    }));

    const openForm = (candidate: Candidate) => {
        setEditing(candidate);
        form.clearErrors();
        form.setData({
            ...(Object.fromEntries(
                TEXT_FIELDS.map(([key]) => [key, String(candidate[key] ?? '')]),
            ) as Record<TextKey, string>),
            job_id: candidate.job_id,
            source_id: candidate.source_id ?? '',
            gender: candidate.gender ?? '',
            status: candidate.status,
        });
    };

    const fullName = (c: Candidate) => `${c.first_name} ${c.last_name}`;
    const amount = (value: string | null) =>
        value === null ? '—' : money(Number(value));

    const columns: Column<Candidate>[] = [
        {
            key: 'first_name',
            label: 'Name',
            sortable: true,
            render: (c) => (
                <PersonCell
                    name={fullName(c)}
                    detail={c.email}
                    gender={c.gender as 'male' | 'female' | null}
                />
            ),
        },
        {
            key: 'job',
            label: 'Job',
            render: (c) =>
                c.job && (
                    <div className="grid justify-items-start gap-1">
                        <span>{c.job.title}</span>
                        {c.job.job_code && <IdBadge>{c.job.job_code}</IdBadge>}
                    </div>
                ),
        },
        { key: 'source', label: 'Source', render: (c) => c.source?.name },
        {
            key: 'experience_years',
            label: 'Experience',
            render: (c) => `${c.experience_years} ${t('years')}`,
        },
        {
            key: 'expected_salary',
            label: 'Expected Salary',
            render: (c) => amount(c.expected_salary),
        },
        {
            key: 'status',
            label: 'Status',
            render: (c) => <StatusBadge status={c.status} />,
        },
        {
            key: 'application_date',
            label: 'Applied',
            sortable: true,
            render: (c) => <DateCell value={c.application_date} />,
        },
    ];

    return (
        <>
            <Head title={t('Candidates')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Candidates"
                    description="Track applicants through your hiring pipeline."
                    action={
                        <ViewToggle
                            current="List"
                            views={[
                                {
                                    label: 'List',
                                    href: candidateRoutes.index(),
                                    icon: List,
                                },
                                {
                                    label: 'Kanban',
                                    href: candidateRoutes.kanban(),
                                    icon: SquareKanban,
                                },
                            ]}
                        />
                    }
                />

                <DataTable
                    data={candidates}
                    columns={columns}
                    filters={filters}
                    url={url}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="job_id"
                                label="All Jobs"
                                options={jobOptions}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="source_id"
                                label="All Sources"
                                options={sources}
                            />
                        </>
                    }
                    actions={(candidate) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={candidateRoutes.show(candidate.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-candidates') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(candidate)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-candidates') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(candidate)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
                title="Edit Candidate"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (editing) {
                        form.submit(candidateRoutes.update(editing.id), {
                            preserveScroll: true,
                            onSuccess: () => setEditing(null),
                        });
                    }
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="candidate-job_id">
                            {t('Job Posting')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="candidate-job_id"
                            required
                            value={form.data.job_id}
                            onChange={(e) =>
                                form.setData(
                                    'job_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">{t('Select')}</option>
                            {jobOptions.map((job) => (
                                <option key={job.id} value={job.id}>
                                    {job.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.job_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="candidate-source_id">
                            {t('Source')}
                        </Label>
                        <SelectField
                            id="candidate-source_id"
                            value={form.data.source_id}
                            onChange={(e) =>
                                form.setData(
                                    'source_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">{t('Select')}</option>
                            {sources.map((source) => (
                                <option key={source.id} value={source.id}>
                                    {source.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.source_id} />
                    </div>
                    {TEXT_FIELDS.map(([key, label, type]) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={`candidate-${key}`}>
                                {t(label)}
                                {REQUIRED.has(key) && (
                                    <span className="text-destructive">*</span>
                                )}
                            </Label>
                            <Input
                                id={`candidate-${key}`}
                                type={type}
                                min={type === 'number' ? 0 : undefined}
                                step={type === 'number' ? 'any' : undefined}
                                required={REQUIRED.has(key)}
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="candidate-gender">{t('Gender')}</Label>
                        <SelectField
                            id="candidate-gender"
                            value={form.data.gender}
                            onChange={(e) =>
                                form.setData('gender', e.target.value)
                            }
                        >
                            <option value="">{t('Select')}</option>
                            <option value="male">{t('Male')}</option>
                            <option value="female">{t('Female')}</option>
                            <option value="other">{t('Other')}</option>
                        </SelectField>
                        <InputError message={form.errors.gender} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="candidate-status">{t('Status')}</Label>
                        <SelectField
                            id="candidate-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value as Status)
                            }
                        >
                            {STATUSES.map((status) => (
                                <option key={status} value={status}>
                                    {t(status)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This candidate will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(candidateRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Candidates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: candidateRoutes.index() },
        { title: 'Candidates', href: candidateRoutes.index() },
    ],
};
