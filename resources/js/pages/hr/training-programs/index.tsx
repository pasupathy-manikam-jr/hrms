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
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import trainingProgramRoutes from '@/routes/hr/training-programs';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

const STATUSES = ['draft', 'active', 'completed', 'cancelled'] as const;

type TrainingProgram = {
    id: number;
    training_type_id: number;
    name: string;
    description: string | null;
    duration: number | null;
    cost: string;
    capacity: number | null;
    status: (typeof STATUSES)[number];
    prerequisites: string | null;
    is_mandatory: boolean;
    is_self_enrollment: boolean;
    training_type: Option;
    sessions_count: number;
    employee_trainings_count: number;
    created_at: string;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    training_type_id: '' as number | string,
    name: '',
    description: '',
    duration: '' as number | string,
    cost: '' as number | string,
    capacity: '' as number | string,
    status: 'draft' as TrainingProgram['status'],
    prerequisites: '',
    is_mandatory: false,
    is_self_enrollment: false,
};

const YES_NO = [
    { id: '1', name: 'Yes' },
    { id: '0', name: 'No' },
];

export default function TrainingPrograms({
    trainingPrograms,
    statusCounts,
    trainingTypes,
    filters,
}: {
    trainingPrograms: Paginated<TrainingProgram>;
    statusCounts: Record<string, number>;
    trainingTypes: Option[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const url = trainingProgramRoutes.index();
    const [editing, setEditing] = useState<TrainingProgram | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<TrainingProgram | null>(null);
    const form = useForm(blank);

    const openForm = (program: TrainingProgram | null) => {
        setEditing(program);
        form.clearErrors();
        form.setData(
            program
                ? {
                      training_type_id: program.training_type_id,
                      name: program.name,
                      description: program.description ?? '',
                      duration: program.duration ?? '',
                      cost: program.cost,
                      capacity: program.capacity ?? '',
                      status: program.status,
                      prerequisites: program.prerequisites ?? '',
                      is_mandatory: program.is_mandatory,
                      is_self_enrollment: program.is_self_enrollment,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<TrainingProgram>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (p) => (
                <div>
                    <div className="font-medium">{p.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {p.training_type.name}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (p) => <StatusBadge status={p.status} />,
        },
        {
            key: 'duration',
            label: 'Duration',
            sortable: true,
            className: 'whitespace-nowrap',
            render: (p) =>
                p.duration ? t(':hours hours', { hours: p.duration }) : '—',
        },
        {
            key: 'cost',
            label: 'Cost',
            sortable: true,
            className: 'whitespace-nowrap',
            render: (p) => money(Number(p.cost)),
        },
        {
            key: 'capacity',
            label: 'Capacity',
            sortable: true,
            render: (p) => p.capacity ?? '—',
        },
        {
            key: 'flags',
            label: 'Flags',
            render: (p) => (
                <div className="flex flex-wrap gap-1">
                    {p.is_mandatory && <StatusBadge status="mandatory" />}
                    {p.is_self_enrollment && (
                        <StatusBadge status="self_enrollment" />
                    )}
                    {!p.is_mandatory && !p.is_self_enrollment && '—'}
                </div>
            ),
        },
        {
            key: 'sessions_count',
            label: 'Sessions',
            render: (p) => p.sessions_count,
        },
        {
            key: 'employee_trainings_count',
            label: 'Employees',
            render: (p) => p.employee_trainings_count,
        },
    ];

    return (
        <>
            <Head title={t('Training Programs')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Training Programs"
                    description="Plan training programs with their duration, cost and capacity."
                    action={
                        can('create-training-programs') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Training Program')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={trainingPrograms}
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
                                name="training_type_id"
                                label="All Types"
                                options={trainingTypes}
                            />
                        </>
                    }
                    moreFilters={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="is_mandatory"
                                label="Mandatory: All"
                                options={YES_NO.map((o) => ({
                                    ...o,
                                    name: t(o.name),
                                }))}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="is_self_enrollment"
                                label="Self Enrollment: All"
                                options={YES_NO.map((o) => ({
                                    ...o,
                                    name: t(o.name),
                                }))}
                            />
                        </>
                    }
                    actions={(program) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link
                                    href={trainingProgramRoutes.show(
                                        program.id,
                                    )}
                                >
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-training-programs') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(program)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-training-programs') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(program)}
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
                title={
                    editing ? 'Edit Training Program' : 'Add Training Program'
                }
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? trainingProgramRoutes.update(editing.id)
                            : trainingProgramRoutes.store(),
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
                        <Label htmlFor="program-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="program-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="program-type">
                            {t('Training Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="program-type"
                            required
                            value={form.data.training_type_id}
                            onChange={(e) =>
                                form.setData('training_type_id', e.target.value)
                            }
                        >
                            <option value="">
                                {t('Select Training Type')}
                            </option>
                            {trainingTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.training_type_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="program-status">{t('Status')}</Label>
                        <SelectField
                            id="program-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as TrainingProgram['status'],
                                )
                            }
                        >
                            {STATUSES.map((status) => (
                                <option key={status} value={status}>
                                    {t(
                                        status.charAt(0).toUpperCase() +
                                            status.slice(1),
                                    )}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="program-duration">
                            {t('Duration (hours)')}
                        </Label>
                        <Input
                            id="program-duration"
                            type="number"
                            min={1}
                            value={form.data.duration}
                            onChange={(e) =>
                                form.setData('duration', e.target.value)
                            }
                        />
                        <InputError message={form.errors.duration} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="program-cost">
                            {t('Cost')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="program-cost"
                            type="number"
                            min={0}
                            step="0.01"
                            required
                            value={form.data.cost}
                            onChange={(e) =>
                                form.setData('cost', e.target.value)
                            }
                        />
                        <InputError message={form.errors.cost} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="program-capacity">
                            {t('Capacity')}
                        </Label>
                        <Input
                            id="program-capacity"
                            type="number"
                            min={1}
                            value={form.data.capacity}
                            onChange={(e) =>
                                form.setData('capacity', e.target.value)
                            }
                        />
                        <InputError message={form.errors.capacity} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="program-prerequisites">
                            {t('Prerequisites')}
                        </Label>
                        <Input
                            id="program-prerequisites"
                            value={form.data.prerequisites}
                            onChange={(e) =>
                                form.setData('prerequisites', e.target.value)
                            }
                        />
                        <InputError message={form.errors.prerequisites} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="program-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="program-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="program-mandatory"
                            checked={form.data.is_mandatory}
                            onCheckedChange={(checked) =>
                                form.setData('is_mandatory', checked)
                            }
                        />
                        <Label htmlFor="program-mandatory">
                            {t('Mandatory')}
                        </Label>
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="program-self-enrollment"
                            checked={form.data.is_self_enrollment}
                            onCheckedChange={(checked) =>
                                form.setData('is_self_enrollment', checked)
                            }
                        />
                        <Label htmlFor="program-self-enrollment">
                            {t('Allow Self Enrollment')}
                        </Label>
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This training program and its sessions, assignments and assessments will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(trainingProgramRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

TrainingPrograms.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Training & Development',
            href: trainingProgramRoutes.index(),
        },
        { title: 'Training Programs', href: trainingProgramRoutes.index() },
    ],
};
