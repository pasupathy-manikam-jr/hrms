import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
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
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import customQuestionRoutes from '@/routes/hr/recruitment/custom-questions';
import type { Paginated, TableFilters } from '@/types';

const TYPES = [
    'text',
    'textarea',
    'number',
    'email',
    'date',
    'select',
    'radio',
    'checkbox',
] as const;
const OPTION_TYPES: string[] = ['select', 'radio', 'checkbox'];

/** The demo's green "Yes" / grey "No" (the shared "no" badge is red). */
function RequiredBadge({ required }: { required: boolean }) {
    return required ? (
        <StatusBadge status="yes" />
    ) : (
        <StatusBadge status="optional" label="No" />
    );
}

type CustomQuestion = {
    id: number;
    created_at: string;
    question: string;
    type: (typeof TYPES)[number];
    options: string[] | null;
    required: boolean;
    sort_order: number;
    status: 'active' | 'inactive';
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    question: '',
    type: 'text' as CustomQuestion['type'],
    options: '',
    required: false,
    sort_order: 0 as number | string,
    status: 'active' as CustomQuestion['status'],
};

const label = (value: string) => value.charAt(0).toUpperCase() + value.slice(1);

export default function CustomQuestions({
    customQuestions,
    filters,
}: {
    customQuestions: Paginated<CustomQuestion>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<CustomQuestion | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<CustomQuestion | null>(null);
    const [viewing, setViewing] = useState<CustomQuestion | null>(null);
    const form = useForm(blank);
    const url = customQuestionRoutes.index();
    const hasOptions = OPTION_TYPES.includes(form.data.type);

    const openForm = (question: CustomQuestion | null) => {
        setEditing(question);
        form.clearErrors();
        form.setData(
            question
                ? {
                      question: question.question,
                      type: question.type,
                      options: (question.options ?? []).join('\n'),
                      required: question.required,
                      sort_order: question.sort_order,
                      status: question.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () => {
        // One option per line in the textarea; the API takes a list.
        form.transform((data) => ({
            ...data,
            options: data.options
                .split('\n')
                .map((option) => option.trim())
                .filter(Boolean),
        }));
        form.submit(
            editing
                ? customQuestionRoutes.update(editing.id)
                : customQuestionRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );
    };

    const columns: Column<CustomQuestion>[] = [
        {
            key: 'question',
            label: 'Question',
            sortable: true,
            render: (row) => (
                <span className="font-medium">{row.question}</span>
            ),
        },
        {
            key: 'required',
            label: 'Required',
            render: (row) => <RequiredBadge required={row.required} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (row) => <DateCell value={row.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Custom Questions')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Custom Questions"
                    description="Manage custom questions used in job application forms."
                    action={
                        can('create-custom-questions') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Custom Question')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={customQuestions}
                    columns={columns}
                    filters={filters}
                    url={url}
                    actions={(question) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(question)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-custom-questions') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(question)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-custom-questions') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(question)}
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
                title={editing ? 'Edit Custom Question' : 'Add Custom Question'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="question-text">
                            {t('Question')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="question-text"
                            required
                            value={form.data.question}
                            onChange={(e) =>
                                form.setData('question', e.target.value)
                            }
                        />
                        <InputError message={form.errors.question} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="question-type">{t('Field Type')}</Label>
                        <SelectField
                            id="question-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData(
                                    'type',
                                    e.target.value as CustomQuestion['type'],
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
                        <Label htmlFor="question-sort">{t('Sort Order')}</Label>
                        <Input
                            id="question-sort"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(e) =>
                                form.setData('sort_order', e.target.value)
                            }
                        />
                        <InputError message={form.errors.sort_order} />
                    </div>
                    {hasOptions && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="question-options">
                                {t('Options (one per line)')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <textarea
                                id="question-options"
                                rows={4}
                                className={textareaClass}
                                value={form.data.options}
                                onChange={(e) =>
                                    form.setData('options', e.target.value)
                                }
                            />
                            <InputError
                                message={
                                    form.errors.options ??
                                    Object.entries(form.errors).find(([key]) =>
                                        key.startsWith('options.'),
                                    )?.[1]
                                }
                            />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="question-status">{t('Status')}</Label>
                        <SelectField
                            id="question-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as CustomQuestion['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="flex items-center gap-3 self-end pb-2">
                        <Switch
                            id="question-required"
                            checked={form.data.required}
                            onCheckedChange={(checked) =>
                                form.setData('required', checked)
                            }
                        />
                        <Label htmlFor="question-required">
                            {t('Required')}
                        </Label>
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{viewing?.question}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Field Type')}
                                </dt>
                                <dd>
                                    <Badge variant="outline">
                                        {t(label(viewing.type))}
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Required')}
                                </dt>
                                <dd>
                                    <RequiredBadge
                                        required={viewing.required}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Sort Order')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.sort_order}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.status} />
                                </dd>
                            </div>
                            {viewing.options && (
                                <div className="col-span-2">
                                    <dt className="text-muted-foreground">
                                        {t('Options')}
                                    </dt>
                                    <dd className="mt-1 flex flex-wrap gap-1">
                                        {viewing.options.map((option) => (
                                            <Badge
                                                key={option}
                                                variant="secondary"
                                            >
                                                {option}
                                            </Badge>
                                        ))}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This custom question will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(customQuestionRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

CustomQuestions.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: customQuestionRoutes.index() },
        { title: 'Custom Questions', href: customQuestionRoutes.index() },
    ],
};
