import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { CandidateCell, PersonCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import assessmentRoutes from '@/routes/hr/recruitment/candidate-assessments';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Assessment = {
    id: number;
    candidate_id: number;
    assessment_name: string;
    assessment_date: string;
    score: string | null;
    max_score: string;
    pass_fail_status: 'Pass' | 'Fail' | 'Pending';
    comments: string | null;
    conducted_by: number | null;
    candidate: {
        id: number;
        first_name: string;
        last_name: string;
        email: string | null;
    };
    conductor: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
    } | null;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    candidate_id: '' as number | string,
    assessment_name: '',
    assessment_date: '',
    score: '' as number | string,
    max_score: 100 as number | string,
    comments: '',
    conducted_by: '' as number | string,
};

export default function CandidateAssessments({
    assessments,
    statusCounts,
    candidates,
    employees,
    passMark,
    filters,
}: {
    assessments: Paginated<Assessment>;
    statusCounts: Record<string, number>;
    candidates: Option[];
    employees: Option[];
    passMark: number;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Assessment | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Assessment | null>(null);
    const [viewing, setViewing] = useState<Assessment | null>(null);
    const form = useForm(blank);
    const url = assessmentRoutes.index();

    const openForm = (assessment: Assessment | null) => {
        setEditing(assessment);
        form.clearErrors();
        form.setData(
            assessment
                ? {
                      candidate_id: assessment.candidate_id,
                      assessment_name: assessment.assessment_name,
                      assessment_date: assessment.assessment_date,
                      score:
                          assessment.score === null
                              ? ''
                              : Number(assessment.score),
                      max_score: Number(assessment.max_score),
                      comments: assessment.comments ?? '',
                      conducted_by: assessment.conducted_by ?? '',
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Assessment>[] = [
        {
            key: 'candidate',
            label: 'Candidate',
            render: (row) => (
                <CandidateCell
                    id={row.candidate.id}
                    name={`${row.candidate.first_name} ${row.candidate.last_name}`}
                    detail={row.candidate.email}
                />
            ),
        },
        {
            key: 'assessment_name',
            label: 'Assessment',
            sortable: true,
            render: (row) => (
                <span className="font-medium">{row.assessment_name}</span>
            ),
        },
        {
            key: 'score',
            label: 'Score',
            sortable: true,
            render: (row) =>
                row.score === null ? (
                    '—'
                ) : (
                    <div className="whitespace-nowrap">
                        <div>
                            {Number(row.score)}/{Number(row.max_score)}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {Math.round(
                                (Number(row.score) / Number(row.max_score)) *
                                    100,
                            )}
                            %
                        </div>
                    </div>
                ),
        },
        {
            key: 'pass_fail_status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.pass_fail_status} />,
        },
        {
            key: 'conductor',
            label: 'Conducted By',
            render: (row) =>
                row.conductor ? (
                    <PersonCell
                        name={row.conductor.name}
                        detail={row.conductor.email}
                        src={row.conductor.avatar}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'assessment_date',
            label: 'Date',
            sortable: true,
            render: (row) => <DateCell value={row.assessment_date} />,
        },
    ];

    return (
        <>
            <Head title={t('Candidate Assessments')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Candidate Assessments"
                    description="View and manage assessments assigned to candidates."
                    action={
                        can('create-candidate-assessments') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Assessment')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={assessments}
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
                            name="candidate_id"
                            label="All Candidates"
                            options={candidates}
                        />
                    }
                    actions={(assessment) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(assessment)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-candidate-assessments') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(assessment)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-candidate-assessments') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(assessment)}
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
                title={editing ? 'Edit Assessment' : 'Add Assessment'}
                description={t(
                    'Scores of :mark% or more of the max score pass; leave the score empty while pending.',
                    { mark: passMark },
                )}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? assessmentRoutes.update(editing.id)
                            : assessmentRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-candidate">
                            {t('Candidate')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="assessment-candidate"
                            required
                            value={form.data.candidate_id}
                            onChange={(e) =>
                                form.setData('candidate_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Candidate')}</option>
                            {candidates.map((candidate) => (
                                <option key={candidate.id} value={candidate.id}>
                                    {candidate.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.candidate_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-name">
                            {t('Assessment Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="assessment-name"
                            required
                            value={form.data.assessment_name}
                            onChange={(e) =>
                                form.setData('assessment_name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.assessment_name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-date">
                            {t('Assessment Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="assessment-date"
                            type="date"
                            required
                            value={form.data.assessment_date}
                            onChange={(e) =>
                                form.setData('assessment_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.assessment_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-conductor">
                            {t('Conducted By')}
                        </Label>
                        <SelectField
                            id="assessment-conductor"
                            value={form.data.conducted_by}
                            onChange={(e) =>
                                form.setData('conducted_by', e.target.value)
                            }
                        >
                            <option value="">{t('Select Employee')}</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.conducted_by} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-score">{t('Score')}</Label>
                        <Input
                            id="assessment-score"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.score}
                            onChange={(e) =>
                                form.setData('score', e.target.value)
                            }
                        />
                        <InputError message={form.errors.score} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-max">
                            {t('Max Score')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="assessment-max"
                            type="number"
                            min={0.01}
                            step="0.01"
                            required
                            value={form.data.max_score}
                            onChange={(e) =>
                                form.setData('max_score', e.target.value)
                            }
                        />
                        <InputError message={form.errors.max_score} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="assessment-comments">
                            {t('Comments')}
                        </Label>
                        <textarea
                            id="assessment-comments"
                            rows={3}
                            className={textareaClass}
                            value={form.data.comments}
                            onChange={(e) =>
                                form.setData('comments', e.target.value)
                            }
                        />
                        <InputError message={form.errors.comments} />
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{viewing?.assessment_name}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div className="col-span-2">
                                <dt className="mb-1 text-muted-foreground">
                                    {t('Candidate')}
                                </dt>
                                <dd>
                                    <CandidateCell
                                        id={viewing.candidate.id}
                                        name={`${viewing.candidate.first_name} ${viewing.candidate.last_name}`}
                                        detail={viewing.candidate.email}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Score')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.score === null
                                        ? '—'
                                        : `${Number(viewing.score)}/${Number(viewing.max_score)}`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge
                                        status={viewing.pass_fail_status}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Date')}
                                </dt>
                                <dd className="font-medium">
                                    {date(viewing.assessment_date)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Conducted By')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.conductor?.name ?? '—'}
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Comments')}
                                </dt>
                                <dd className="font-medium whitespace-pre-line">
                                    {viewing.comments || '—'}
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This assessment will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(assessmentRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

CandidateAssessments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: assessmentRoutes.index() },
        { title: 'Candidate Assessments', href: assessmentRoutes.index() },
    ],
};
