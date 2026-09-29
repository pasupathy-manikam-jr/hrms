import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Award,
    ClipboardCheck,
    Eye,
    LayoutDashboard,
    List,
    Plus,
    SquarePen,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { ViewToggle } from '@/components/view-toggle';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import {
    applyFilters,
    FilterSelect,
    StatusTabs,
} from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import employeeTrainingRoutes from '@/routes/hr/employee-trainings';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type EmployeeOption = Option & { employee_id: string };
type ProgramOption = Option & { status: string; is_self_enrollment: boolean };
type SessionOption = Option & {
    training_program_id: number;
    start_date: string;
};
type AssessmentOption = Option & {
    training_program_id: number;
    passing_score: string;
};

const STATUSES = ['assigned', 'in_progress', 'completed', 'failed'] as const;

type Result = {
    id: number;
    training_assessment_id: number;
    score: string;
    is_passed: boolean;
    feedback: string | null;
    assessment_date: string;
    assessment: { id: number; name: string; passing_score: string };
};

type EmployeeTraining = {
    id: number;
    employee_id: number;
    training_program_id: number;
    training_session_id: number | null;
    status: (typeof STATUSES)[number];
    assigned_date: string;
    completion_date: string | null;
    score: string | null;
    certification: boolean;
    feedback: string | null;
    notes: string | null;
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
    program: Option & { training_type: Option | null };
    session: (Option & { start_date: string }) | null;
    results: Result[];
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const today = () => new Date().toISOString().slice(0, 10);

const blank = {
    employee_id: '' as number | string,
    training_program_id: '' as number | string,
    training_session_id: '' as number | string,
    status: 'assigned' as EmployeeTraining['status'],
    assigned_date: '',
    completion_date: '',
    score: '' as number | string,
    certification: false,
    feedback: '',
    notes: '',
};

const label = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function EmployeeTrainings({
    employeeTrainings,
    statusCounts,
    employees,
    trainingPrograms,
    trainingSessions,
    assessments,
    filters,
}: {
    employeeTrainings: Paginated<EmployeeTraining>;
    statusCounts: Record<string, number>;
    employees: EmployeeOption[];
    trainingPrograms: ProgramOption[];
    trainingSessions: SessionOption[];
    assessments: AssessmentOption[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const url = employeeTrainingRoutes.index();
    const manageAny = can('manage-any-employee-trainings');
    const [editing, setEditing] = useState<EmployeeTraining | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [recording, setRecording] = useState<EmployeeTraining | null>(null);
    const [deleting, setDeleting] = useState<EmployeeTraining | null>(null);
    const form = useForm(blank);
    const bulkForm = useForm({
        training_program_id: '' as number | string,
        training_session_id: '' as number | string,
        employee_ids: [] as number[],
        assigned_date: '',
        notes: '',
    });
    const resultForm = useForm({
        training_assessment_id: '' as number | string,
        score: '' as number | string,
        assessment_date: '',
        feedback: '',
    });

    const sessionsFor = (programId: number | string) =>
        trainingSessions.filter(
            (s) => s.training_program_id === Number(programId),
        );

    // Employees can only enrol themselves, into active self-enrollment programs.
    const bulkPrograms = manageAny
        ? trainingPrograms
        : trainingPrograms.filter(
              (p) => p.is_self_enrollment && p.status === 'active',
          );

    const openForm = (training: EmployeeTraining | null) => {
        setEditing(training);
        form.clearErrors();
        form.setData(
            training
                ? {
                      employee_id: training.employee_id,
                      training_program_id: training.training_program_id,
                      training_session_id: training.training_session_id ?? '',
                      status: training.status,
                      assigned_date: training.assigned_date,
                      completion_date: training.completion_date ?? '',
                      score: training.score ?? '',
                      certification: training.certification,
                      feedback: training.feedback ?? '',
                      notes: training.notes ?? '',
                  }
                : { ...blank, assigned_date: today() },
        );
        setFormOpen(true);
    };

    const openBulk = () => {
        bulkForm.clearErrors();
        bulkForm.setData({
            training_program_id: '',
            training_session_id: '',
            employee_ids: [],
            assigned_date: today(),
            notes: '',
        });
        setBulkOpen(true);
    };

    const openRecord = (training: EmployeeTraining) => {
        resultForm.clearErrors();
        resultForm.setData({
            training_assessment_id: '',
            score: '',
            assessment_date: today(),
            feedback: '',
        });
        setRecording(training);
    };

    const programAssessments = recording
        ? assessments.filter(
              (a) => a.training_program_id === recording.training_program_id,
          )
        : [];

    const columns: Column<EmployeeTraining>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (e) => (
                <PersonCell
                    name={e.employee.user.name}
                    detail={e.employee.employee_id}
                    src={e.employee.user.avatar}
                    gender={e.employee.gender}
                />
            ),
        },
        {
            key: 'program',
            label: 'Training Program',
            render: (e) => (
                <div>
                    <div className="font-medium">{e.program.name}</div>
                    {e.program.training_type && (
                        <div className="text-xs text-muted-foreground">
                            {e.program.training_type.name}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (e) => <StatusBadge status={e.status} />,
        },
        {
            key: 'assigned_date',
            label: 'Assigned Date',
            sortable: true,
            render: (e) => <DateCell value={e.assigned_date} />,
        },
        {
            key: 'completion_date',
            label: 'Completion Date',
            sortable: true,
            render: (e) => <DateCell value={e.completion_date} />,
        },
        {
            key: 'score',
            label: 'Score',
            sortable: true,
            render: (e) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    {e.score !== null ? `${Number(e.score)}%` : '—'}
                    {e.certification && (
                        <Award
                            className="size-4 text-amber-500"
                            aria-label={t('Certified')}
                        />
                    )}
                </span>
            ),
        },
        {
            key: 'result',
            label: 'Result',
            render: (e) =>
                e.results.length > 0 ? (
                    <StatusBadge
                        status={
                            e.results.every((r) => r.is_passed)
                                ? 'passed'
                                : 'failed'
                        }
                    />
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <>
            <Head title={t('Employee Trainings')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employee Trainings"
                    description="Assign training programs to employees and track their progress."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <ViewToggle
                                current="List"
                                views={[
                                    {
                                        label: 'List',
                                        href: employeeTrainingRoutes.index(),
                                        icon: List,
                                    },
                                    {
                                        label: 'Dashboard',
                                        href: employeeTrainingRoutes.dashboard(),
                                        icon: LayoutDashboard,
                                    },
                                ]}
                            />
                            {can('assign-trainings') && (
                                <Button variant="outline" onClick={openBulk}>
                                    <Users />{' '}
                                    {t(manageAny ? 'Bulk Assign' : 'Enroll')}
                                </Button>
                            )}
                            {can('create-employee-trainings') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Assign Training')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={employeeTrainings}
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
                    moreFilters={
                        employees.length > 0 && (
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="employee_id"
                                label="All Employees"
                                options={employees}
                            />
                        )
                    }
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="training_program_id"
                                label="All Programs"
                                options={trainingPrograms}
                            />
                            <Input
                                type="date"
                                aria-label={t('From Date')}
                                className="w-auto"
                                value={filters.assigned_date_from ?? ''}
                                onChange={(e) =>
                                    applyFilters(url, filters, {
                                        assigned_date_from: e.target.value,
                                    })
                                }
                            />
                            <Input
                                type="date"
                                aria-label={t('To Date')}
                                className="w-auto"
                                value={filters.assigned_date_to ?? ''}
                                onChange={(e) =>
                                    applyFilters(url, filters, {
                                        assigned_date_to: e.target.value,
                                    })
                                }
                            />
                        </>
                    }
                    actions={(training) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link
                                    href={employeeTrainingRoutes.show(
                                        training.id,
                                    )}
                                >
                                    <Eye />
                                </Link>
                            </Button>
                            {can('record-assessment-results') &&
                                manageAny &&
                                assessments.some(
                                    (a) =>
                                        a.training_program_id ===
                                        training.training_program_id,
                                ) && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Record Assessment')}
                                        onClick={() => openRecord(training)}
                                    >
                                        <ClipboardCheck />
                                    </Button>
                                )}
                            {can('edit-employee-trainings') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(training)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-employee-trainings') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(training)}
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
                title={editing ? 'Edit Employee Training' : 'Assign Training'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? employeeTrainingRoutes.update(editing.id)
                            : employeeTrainingRoutes.store(),
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
                        <Label htmlFor="et-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="et-employee"
                            required
                            value={form.data.employee_id}
                            onChange={(e) =>
                                form.setData('employee_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Employee')}</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name} ({employee.employee_id})
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.employee_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="et-program">
                            {t('Training Program')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="et-program"
                            required
                            value={form.data.training_program_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    training_program_id: e.target.value,
                                    training_session_id: '',
                                })
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
                        <Label htmlFor="et-session">
                            {t('Training Session')}
                        </Label>
                        <SelectField
                            id="et-session"
                            value={form.data.training_session_id}
                            onChange={(e) =>
                                form.setData(
                                    'training_session_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('No specific session')}</option>
                            {sessionsFor(form.data.training_program_id).map(
                                (session) => (
                                    <option key={session.id} value={session.id}>
                                        {session.name} ·{' '}
                                        {date(session.start_date.slice(0, 10))}
                                    </option>
                                ),
                            )}
                        </SelectField>
                        <InputError message={form.errors.training_session_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="et-status">{t('Status')}</Label>
                        <SelectField
                            id="et-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target
                                        .value as EmployeeTraining['status'],
                                )
                            }
                        >
                            {STATUSES.map((status) => (
                                <option key={status} value={status}>
                                    {t(label(status))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="et-assigned">
                            {t('Assigned Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="et-assigned"
                            type="date"
                            required
                            value={form.data.assigned_date}
                            onChange={(e) =>
                                form.setData('assigned_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.assigned_date} />
                    </div>
                    {(form.data.status === 'completed' ||
                        form.data.status === 'failed') && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="et-completed">
                                    {t('Completion Date')}
                                </Label>
                                <Input
                                    id="et-completed"
                                    type="date"
                                    min={form.data.assigned_date || undefined}
                                    value={form.data.completion_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'completion_date',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.completion_date}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="et-score">
                                    {t('Score (%)')}
                                </Label>
                                <Input
                                    id="et-score"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.01"
                                    value={form.data.score}
                                    onChange={(e) =>
                                        form.setData('score', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.score} />
                            </div>
                        </>
                    )}
                    {form.data.status === 'completed' && (
                        <div className="flex items-center gap-3 self-end pb-2">
                            <Switch
                                id="et-certification"
                                checked={form.data.certification}
                                onCheckedChange={(checked) =>
                                    form.setData('certification', checked)
                                }
                            />
                            <Label htmlFor="et-certification">
                                {t('Certificate Issued')}
                            </Label>
                        </div>
                    )}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="et-feedback">{t('Feedback')}</Label>
                        <textarea
                            id="et-feedback"
                            rows={2}
                            className={textareaClass}
                            value={form.data.feedback}
                            onChange={(e) =>
                                form.setData('feedback', e.target.value)
                            }
                        />
                        <InputError message={form.errors.feedback} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="et-notes">{t('Notes')}</Label>
                        <textarea
                            id="et-notes"
                            rows={2}
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={bulkOpen}
                onOpenChange={setBulkOpen}
                title={
                    manageAny ? 'Bulk Assign Training' : 'Enroll in Training'
                }
                onSubmit={(e) => {
                    e.preventDefault();
                    bulkForm.submit(employeeTrainingRoutes.bulkAssign(), {
                        preserveScroll: true,
                        onSuccess: () => setBulkOpen(false),
                    });
                }}
                processing={bulkForm.processing}
                submitLabel={manageAny ? 'Assign' : 'Enroll'}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="bulk-program">
                            {t('Training Program')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="bulk-program"
                            required
                            value={bulkForm.data.training_program_id}
                            onChange={(e) =>
                                bulkForm.setData({
                                    ...bulkForm.data,
                                    training_program_id: e.target.value,
                                    training_session_id: '',
                                })
                            }
                        >
                            <option value="">{t('Select Program')}</option>
                            {bulkPrograms.map((program) => (
                                <option key={program.id} value={program.id}>
                                    {program.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError
                            message={bulkForm.errors.training_program_id}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="bulk-session">
                            {t('Training Session')}
                        </Label>
                        <SelectField
                            id="bulk-session"
                            value={bulkForm.data.training_session_id}
                            onChange={(e) =>
                                bulkForm.setData(
                                    'training_session_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('No specific session')}</option>
                            {sessionsFor(bulkForm.data.training_program_id).map(
                                (session) => (
                                    <option key={session.id} value={session.id}>
                                        {session.name} ·{' '}
                                        {date(session.start_date.slice(0, 10))}
                                    </option>
                                ),
                            )}
                        </SelectField>
                        <InputError
                            message={bulkForm.errors.training_session_id}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="bulk-assigned">
                            {t('Assigned Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="bulk-assigned"
                            type="date"
                            required
                            value={bulkForm.data.assigned_date}
                            onChange={(e) =>
                                bulkForm.setData(
                                    'assigned_date',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={bulkForm.errors.assigned_date} />
                    </div>
                    {manageAny && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label>
                                {t('Employees')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                                {employees.map((employee) => (
                                    <label
                                        key={employee.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={bulkForm.data.employee_ids.includes(
                                                employee.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                bulkForm.setData(
                                                    'employee_ids',
                                                    checked === true
                                                        ? [
                                                              ...bulkForm.data
                                                                  .employee_ids,
                                                              employee.id,
                                                          ]
                                                        : bulkForm.data.employee_ids.filter(
                                                              (id) =>
                                                                  id !==
                                                                  employee.id,
                                                          ),
                                                )
                                            }
                                        />
                                        {employee.name}
                                    </label>
                                ))}
                            </div>
                            <InputError
                                message={bulkForm.errors.employee_ids}
                            />
                        </div>
                    )}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="bulk-notes">{t('Notes')}</Label>
                        <textarea
                            id="bulk-notes"
                            rows={2}
                            className={textareaClass}
                            value={bulkForm.data.notes}
                            onChange={(e) =>
                                bulkForm.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={bulkForm.errors.notes} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={recording !== null}
                onOpenChange={(open) => !open && setRecording(null)}
                title="Record Assessment Result"
                description={
                    recording
                        ? `${recording.employee.user.name} · ${recording.program.name}`
                        : undefined
                }
                onSubmit={(e) => {
                    e.preventDefault();

                    if (recording) {
                        resultForm.submit(
                            employeeTrainingRoutes.recordAssessment(
                                recording.id,
                            ),
                            {
                                preserveScroll: true,
                                onSuccess: () => setRecording(null),
                            },
                        );
                    }
                }}
                processing={resultForm.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="result-assessment">
                            {t('Assessment')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="result-assessment"
                            required
                            value={resultForm.data.training_assessment_id}
                            onChange={(e) =>
                                resultForm.setData(
                                    'training_assessment_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('Select Assessment')}</option>
                            {programAssessments.map((assessment) => (
                                <option
                                    key={assessment.id}
                                    value={assessment.id}
                                >
                                    {assessment.name} (
                                    {t('pass :score%', {
                                        score: Number(assessment.passing_score),
                                    })}
                                    )
                                </option>
                            ))}
                        </SelectField>
                        <InputError
                            message={resultForm.errors.training_assessment_id}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="result-score">
                            {t('Score (%)')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="result-score"
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            required
                            value={resultForm.data.score}
                            onChange={(e) =>
                                resultForm.setData('score', e.target.value)
                            }
                        />
                        <InputError message={resultForm.errors.score} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="result-date">
                            {t('Assessment Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="result-date"
                            type="date"
                            required
                            value={resultForm.data.assessment_date}
                            onChange={(e) =>
                                resultForm.setData(
                                    'assessment_date',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={resultForm.errors.assessment_date}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="result-feedback">{t('Feedback')}</Label>
                        <textarea
                            id="result-feedback"
                            rows={3}
                            className={textareaClass}
                            value={resultForm.data.feedback}
                            onChange={(e) =>
                                resultForm.setData('feedback', e.target.value)
                            }
                        />
                        <InputError message={resultForm.errors.feedback} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This training assignment and its assessment results will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(employeeTrainingRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

EmployeeTrainings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Training & Development',
            href: employeeTrainingRoutes.index(),
        },
        { title: 'Employee Trainings', href: employeeTrainingRoutes.index() },
    ],
};
