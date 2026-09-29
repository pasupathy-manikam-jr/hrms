import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import trainingAssessmentRoutes from '@/routes/hr/training-assessments';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

const TYPES = ['quiz', 'practical', 'presentation'] as const;

type TrainingAssessment = {
    id: number;
    training_program_id: number;
    name: string;
    description: string | null;
    type: (typeof TYPES)[number];
    passing_score: string;
    criteria: string | null;
    program: Option;
    results_count: number;
    results: {
        id: number;
        score: string;
        is_passed: boolean;
        assessment_date: string;
        employee_training: {
            id: number;
            employee: {
                id: number;
                employee_id: string;
                gender: 'male' | 'female' | 'other' | null;
                user: Option & { avatar: string | null };
            };
        };
    }[];
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    training_program_id: '' as number | string,
    name: '',
    description: '',
    type: 'quiz' as TrainingAssessment['type'],
    passing_score: '' as number | string,
    criteria: '',
};

const label = (value: string) => value.charAt(0).toUpperCase() + value.slice(1);

export default function TrainingAssessments({
    trainingAssessments,
    typeCounts,
    trainingPrograms,
    filters,
}: {
    trainingAssessments: Paginated<TrainingAssessment>;
    typeCounts: Record<string, number>;
    trainingPrograms: Option[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();

    const can = useCan();
    const url = trainingAssessmentRoutes.index();
    const [editing, setEditing] = useState<TrainingAssessment | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<TrainingAssessment | null>(null);
    const form = useForm(blank);

    const openForm = (assessment: TrainingAssessment | null) => {
        setEditing(assessment);
        form.clearErrors();
        form.setData(
            assessment
                ? {
                      training_program_id: assessment.training_program_id,
                      name: assessment.name,
                      description: assessment.description ?? '',
                      type: assessment.type,
                      passing_score: Number(assessment.passing_score),
                      criteria: assessment.criteria ?? '',
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<TrainingAssessment>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (a) => (
                <div>
                    <div className="font-medium">{a.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {a.program.name}
                    </div>
                </div>
            ),
        },
        {
            key: 'type',
            label: 'Type',
            sortable: true,
            render: (a) => <StatusBadge status={a.type} />,
        },
        {
            key: 'passing_score',
            label: 'Passing Score',
            sortable: true,
            render: (a) => `${Number(a.passing_score)}%`,
        },
        {
            key: 'results',
            label: 'Results',
            render: (a) => a.results_count,
        },
    ];

    return (
        <>
            <Head title={t('Training Assessments')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Training Assessments"
                    description="Define assessments for each program; record results from Employee Trainings."
                    action={
                        can('create-training-assessments') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Assessment')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={trainingAssessments}
                    columns={columns}
                    filters={filters}
                    url={url}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={typeCounts}
                            name="type"
                        />
                    }
                    toolbar={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="training_program_id"
                            label="All Programs"
                            options={trainingPrograms}
                        />
                    }
                    actions={(assessment) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link
                                    href={trainingAssessmentRoutes.show(
                                        assessment.id,
                                    )}
                                >
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-training-assessments') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(assessment)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-training-assessments') && (
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
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? trainingAssessmentRoutes.update(editing.id)
                            : trainingAssessmentRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="assessment-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="assessment-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="assessment-program">
                            {t('Training Program')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="assessment-program"
                            required
                            value={form.data.training_program_id}
                            onChange={(e) =>
                                form.setData(
                                    'training_program_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('Select Program')}</option>
                            {trainingPrograms.map((program) => (
                                <option key={program.id} value={program.id}>
                                    {program.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.training_program_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-type">{t('Type')}</Label>
                        <SelectField
                            id="assessment-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData(
                                    'type',
                                    e.target
                                        .value as TrainingAssessment['type'],
                                )
                            }
                        >
                            {TYPES.map((type) => (
                                <option key={type} value={type}>
                                    {t(label(type))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-passing">
                            {t('Passing Score (%)')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="assessment-passing"
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            required
                            value={form.data.passing_score}
                            onChange={(e) =>
                                form.setData('passing_score', e.target.value)
                            }
                        />
                        <InputError message={form.errors.passing_score} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="assessment-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="assessment-description"
                            rows={2}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="assessment-criteria">
                            {t('Criteria')}
                        </Label>
                        <textarea
                            id="assessment-criteria"
                            rows={2}
                            className={textareaClass}
                            value={form.data.criteria}
                            onChange={(e) =>
                                form.setData('criteria', e.target.value)
                            }
                        />
                        <InputError message={form.errors.criteria} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This assessment and its recorded results will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(
                        trainingAssessmentRoutes.destroy(deleting.id),
                        {
                            preserveScroll: true,
                            onSuccess: () => setDeleting(null),
                        },
                    )
                }
            />
        </>
    );
}

TrainingAssessments.layout = {
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
    ],
};
