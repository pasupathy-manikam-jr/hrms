import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Plus, Send, SquarePen, Star, Trash2, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import jobPostingRoutes from '@/routes/hr/recruitment/job-postings';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Status = 'Draft' | 'Published' | 'Closed';
type Priority = 'Low' | 'Medium' | 'High';

type JobPosting = {
    id: number;
    job_code: string | null;
    title: string;
    job_category_id: number | null;
    job_type_id: number | null;
    location_id: number | null;
    branch_id: number | null;
    department_id: number | null;
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
    priority: Priority;
    is_featured: boolean;
    is_published: boolean;
    publish_date: string | null;
    status: Status;
    candidates_count: number;
    created_at: string;
    category: Option | null;
    job_type: Option | null;
    location: Option | null;
    branch: Option | null;
    department: Option | null;
};

const STATUSES: Status[] = ['Draft', 'Published', 'Closed'];
const PRIORITIES: Priority[] = ['Low', 'Medium', 'High'];

const blank = {
    title: '',
    job_category_id: '' as number | '',
    job_type_id: '' as number | '',
    location_id: '' as number | '',
    branch_id: '' as number | '',
    department_id: '' as number | '',
    positions: 1 as number | '',
    min_experience: '0',
    max_experience: '',
    min_salary: '',
    max_salary: '',
    start_date: '',
    application_deadline: '',
    skills: '',
    description: '',
    requirements: '',
    benefits: '',
    priority: 'Medium' as Priority,
    is_featured: false,
    status: 'Draft' as Status,
};

type FormData = typeof blank;

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function JobPostings({
    jobPostings,
    statusCounts,
    jobCategories,
    jobTypes,
    locations,
    branches,
    departments,
    filters,
}: {
    jobPostings: Paginated<JobPosting>;
    statusCounts: Record<string, number>;
    jobCategories: Option[];
    jobTypes: Option[];
    locations: Option[];
    branches: Option[];
    departments: (Option & { branch_id: number })[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<JobPosting | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<JobPosting | null>(null);
    const form = useForm(blank);
    const url = jobPostingRoutes.index();

    const openForm = (posting: JobPosting | null) => {
        setEditing(posting);
        form.clearErrors();
        form.setData(
            posting
                ? {
                      title: posting.title,
                      job_category_id: posting.job_category_id ?? '',
                      job_type_id: posting.job_type_id ?? '',
                      location_id: posting.location_id ?? '',
                      branch_id: posting.branch_id ?? '',
                      department_id: posting.department_id ?? '',
                      positions: posting.positions,
                      min_experience: posting.min_experience,
                      max_experience: posting.max_experience ?? '',
                      min_salary: posting.min_salary ?? '',
                      max_salary: posting.max_salary ?? '',
                      start_date: posting.start_date ?? '',
                      application_deadline: posting.application_deadline ?? '',
                      skills: (posting.skills ?? []).join(', '),
                      description: posting.description ?? '',
                      requirements: posting.requirements ?? '',
                      benefits: posting.benefits ?? '',
                      priority: posting.priority,
                      is_featured: posting.is_featured,
                      status: posting.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? jobPostingRoutes.update(editing.id)
                : jobPostingRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const salary = (p: JobPosting) =>
        p.min_salary || p.max_salary
            ? [p.min_salary, p.max_salary]
                  .filter((v): v is string => v !== null)
                  .map((v) => money(Number(v)))
                  .join(' – ')
            : '—';

    const select = (
        key: keyof FormData,
        label: string,
        options: Option[],
        required = false,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`job-posting-${key}`}>
                {t(label)}
                {required && <span className="text-destructive">*</span>}
            </Label>
            <SelectField
                id={`job-posting-${key}`}
                required={required}
                value={form.data[key] as number | ''}
                onChange={(e) => {
                    const value = e.target.value ? Number(e.target.value) : '';
                    form.setData(
                        key === 'branch_id'
                            ? {
                                  ...form.data,
                                  branch_id: value,
                                  department_id: '',
                              }
                            : { ...form.data, [key]: value },
                    );
                }}
            >
                <option value="">{t('Select')}</option>
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.name}
                    </option>
                ))}
            </SelectField>
            <InputError message={form.errors[key]} />
        </div>
    );

    const input = (
        key: keyof FormData,
        label: string,
        type: 'text' | 'number' | 'date' = 'text',
        required = false,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`job-posting-${key}`}>
                {t(label)}
                {required && <span className="text-destructive">*</span>}
            </Label>
            <Input
                id={`job-posting-${key}`}
                type={type}
                min={type === 'number' ? 0 : undefined}
                step={type === 'number' ? 'any' : undefined}
                required={required}
                value={form.data[key] as string | number}
                onChange={(e) =>
                    form.setData({ ...form.data, [key]: e.target.value })
                }
            />
            <InputError message={form.errors[key]} />
        </div>
    );

    const textarea = (key: keyof FormData, label: string) => (
        <div className="grid gap-2 sm:col-span-2">
            <Label htmlFor={`job-posting-${key}`}>{t(label)}</Label>
            <textarea
                id={`job-posting-${key}`}
                rows={3}
                className={textareaClass}
                value={form.data[key] as string}
                onChange={(e) =>
                    form.setData({ ...form.data, [key]: e.target.value })
                }
            />
            <InputError message={form.errors[key]} />
        </div>
    );

    const columns: Column<JobPosting>[] = [
        {
            key: 'title',
            label: 'Title',
            sortable: true,
            render: (p) => (
                <div className="grid justify-items-start gap-1">
                    <div className="flex items-center gap-1 font-medium">
                        {p.title}
                        {p.is_featured && (
                            <Star
                                className="size-3.5 fill-amber-400 text-amber-400"
                                aria-label={t('Featured')}
                            />
                        )}
                    </div>
                    {p.job_code && <IdBadge>{p.job_code}</IdBadge>}
                </div>
            ),
        },
        {
            key: 'location',
            label: 'Location',
            render: (p) => p.location?.name ?? '—',
        },
        {
            key: 'salary',
            label: 'Salary Range',
            render: (p) => (
                <span className="whitespace-nowrap">{salary(p)}</span>
            ),
        },
        {
            key: 'candidates_count',
            label: 'Applications',
            render: (p) => (
                <span
                    title={t(':count of :positions positions', {
                        count: p.candidates_count,
                        positions: p.positions,
                    })}
                >
                    <IdBadge>{p.candidates_count}</IdBadge>
                </span>
            ),
        },
        {
            key: 'job_type',
            label: 'Type',
            render: (p) => p.job_type?.name ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            render: (p) => <StatusBadge status={p.status} />,
        },
        {
            key: 'application_deadline',
            label: 'Deadline',
            sortable: true,
            render: (p) => <DateCell value={p.application_deadline} />,
        },
    ];

    return (
        <>
            <Head title={t('Job Postings')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Job Postings"
                    description="Create and manage your open positions."
                    action={
                        can('create-job-postings') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Job Posting')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={jobPostings}
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
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="job_type_id"
                            label="All Job Types"
                            options={jobTypes}
                        />
                    }
                    actions={(posting) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={jobPostingRoutes.show(posting.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {can('publish-job-postings') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        posting.is_published
                                            ? 'Unpublish'
                                            : 'Publish',
                                    )}
                                    onClick={() =>
                                        router.put(
                                            jobPostingRoutes.publish(
                                                posting.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {posting.is_published ? (
                                        <Undo2 />
                                    ) : (
                                        <Send />
                                    )}
                                </Button>
                            )}
                            {can('edit-job-postings') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(posting)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-job-postings') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(posting)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Job Posting' : 'Add Job Posting'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        {input('title', 'Job Title', 'text', true)}
                    </div>
                    {select('job_category_id', 'Job Category', jobCategories)}
                    {select('job_type_id', 'Job Type', jobTypes, true)}
                    {select('location_id', 'Location', locations, true)}
                    {input('positions', 'Positions', 'number', true)}
                    {select('branch_id', 'Branch', branches, true)}
                    {select(
                        'department_id',
                        'Department',
                        departments.filter(
                            (d) => d.branch_id === form.data.branch_id,
                        ),
                        true,
                    )}
                    {input(
                        'min_experience',
                        'Min Experience (Years)',
                        'number',
                        true,
                    )}
                    {input(
                        'max_experience',
                        'Max Experience (Years)',
                        'number',
                    )}
                    {input('min_salary', 'Min Salary', 'number')}
                    {input('max_salary', 'Max Salary', 'number')}
                    {input('start_date', 'Start Date', 'date')}
                    {input(
                        'application_deadline',
                        'Application Deadline',
                        'date',
                    )}
                    <div className="sm:col-span-2">
                        {input('skills', 'Skills (comma separated)')}
                    </div>
                    {textarea('description', 'Description')}
                    {textarea('requirements', 'Requirements')}
                    {textarea('benefits', 'Benefits')}
                    <div className="grid gap-2">
                        <Label htmlFor="job-posting-priority">
                            {t('Priority')}
                        </Label>
                        <SelectField
                            id="job-posting-priority"
                            value={form.data.priority}
                            onChange={(e) =>
                                form.setData(
                                    'priority',
                                    e.target.value as Priority,
                                )
                            }
                        >
                            {PRIORITIES.map((p) => (
                                <option key={p} value={p}>
                                    {t(p)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.priority} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="job-posting-status">
                            {t('Status')}
                        </Label>
                        <SelectField
                            id="job-posting-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value as Status)
                            }
                        >
                            {STATUSES.map((s) => (
                                <option key={s} value={s}>
                                    {t(s)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="job-posting-featured"
                            checked={form.data.is_featured}
                            onCheckedChange={(checked) =>
                                form.setData('is_featured', checked)
                            }
                        />
                        <Label htmlFor="job-posting-featured">
                            {t('Featured Job')}
                        </Label>
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This job posting and its candidates will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(jobPostingRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

JobPostings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: jobPostingRoutes.index() },
        { title: 'Job Postings', href: jobPostingRoutes.index() },
    ],
};
