import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Plus, SquarePen, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import statementRoutes from '@/routes/engagement/statements';
import type { Paginated, TableFilters } from '@/types';

type Statement = {
    id: number;
    engagement_element_id: number | null;
    element: { id: number; name: string } | null;
    text: string;
    reverse_scored: boolean;
    is_active: boolean;
    sort_order: number;
    answers_count: number;
};

type Element = { id: number; name: string; description: string | null };

type Tier = {
    id: number;
    level: number;
    name: string;
    min_score: string;
    need: string | null;
    profile: string | null;
    action: string | null;
};

const blank = {
    engagement_element_id: '' as number | '',
    text: '',
    reverse_scored: false,
    is_active: true,
    sort_order: 0,
};

export default function EngagementStatements({
    statements,
    filters,
    elements,
    tiers,
}: {
    statements: Paginated<Statement>;
    filters: TableFilters;
    elements: Element[];
    tiers: Tier[];
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<Statement | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Statement | null>(null);
    const form = useForm(blank);

    const openForm = (statement: Statement | null) => {
        setEditing(statement);
        form.clearErrors();
        form.setData(
            statement
                ? {
                      engagement_element_id:
                          statement.engagement_element_id ?? '',
                      text: statement.text,
                      reverse_scored: statement.reverse_scored,
                      is_active: statement.is_active,
                      sort_order: statement.sort_order,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () => {
        form.transform((data) => ({
            ...data,
            engagement_element_id: data.engagement_element_id || null,
        }));
        form.submit(
            editing
                ? statementRoutes.update(editing.id)
                : statementRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );
    };

    const columns: Column<Statement>[] = [
        {
            key: 'text',
            label: 'Statement',
            sortable: true,
            render: (s) => (
                <div className="max-w-xl">
                    <div className="font-medium whitespace-normal">
                        {s.text}
                    </div>
                    {s.reverse_scored && (
                        <div className="text-xs text-muted-foreground">
                            {t('Reverse scored')}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'element',
            label: 'Element',
            render: (s) => s.element?.name ?? t('Overall'),
        },
        {
            key: 'answers_count',
            label: 'Answers',
            render: (s) => s.answers_count,
        },
        {
            key: 'status',
            label: 'Status',
            render: (s) => (
                <StatusBadge status={s.is_active ? 'active' : 'inactive'} />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Question Bank')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Question Bank"
                    description="Agree/disagree statements grouped by element. New campaigns ask every active statement."
                    action={
                        can('create-engagement-survey') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Statement')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={statements}
                    columns={columns}
                    filters={filters}
                    url={statementRoutes.index()}
                    toolbar={
                        <FilterSelect
                            url={statementRoutes.index()}
                            filters={filters}
                            name="element"
                            label="All Elements"
                            options={[
                                ...elements.map((e) => ({
                                    id: e.id,
                                    name: e.name,
                                })),
                                { id: 'overall', name: t('Overall') },
                            ]}
                        />
                    }
                    actions={(statement) => (
                        <>
                            {can('edit-engagement-survey') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(statement)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            statement.is_active
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                statementRoutes.toggleStatus(
                                                    statement.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {statement.is_active ? (
                                            <Lock />
                                        ) : (
                                            <Unlock />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-engagement-survey') &&
                                statement.answers_count === 0 && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(statement)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                        </>
                    )}
                />

                <section className="rounded-xl border bg-card p-5 shadow-sm">
                    <h2 className="font-semibold">{t('Engagement tiers')}</h2>
                    <p className="mb-4 text-sm text-muted-foreground">
                        {t(
                            'A score is the share of favourable answers (Agree or Strongly Agree, after reverse scoring), from 0 to 1.',
                        )}
                    </p>
                    <div className="grid gap-3 md:grid-cols-5">
                        {tiers.map((tier) => (
                            <div
                                key={tier.id}
                                className="rounded-lg border p-3"
                            >
                                <div className="font-medium">{tier.name}</div>
                                <div className="text-sm text-muted-foreground">
                                    {t('Score')} ≥ {tier.min_score} ·{' '}
                                    {tier.need}
                                </div>
                                <p className="mt-2 text-sm">{tier.action}</p>
                            </div>
                        ))}
                    </div>
                </section>
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Statement' : 'Add Statement'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="statement-text">
                            {t('Statement')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="statement-text"
                            required
                            value={form.data.text}
                            onChange={(e) =>
                                form.setData('text', e.target.value)
                            }
                        />
                        <InputError message={form.errors.text} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="statement-element">
                                {t('Element')}
                            </Label>
                            <SelectField
                                id="statement-element"
                                value={form.data.engagement_element_id}
                                onChange={(e) =>
                                    form.setData(
                                        'engagement_element_id',
                                        e.target.value
                                            ? Number(e.target.value)
                                            : '',
                                    )
                                }
                            >
                                <option value="">{t('Overall')}</option>
                                {elements.map((element) => (
                                    <option key={element.id} value={element.id}>
                                        {element.name}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError
                                message={form.errors.engagement_element_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="statement-order">
                                {t('Order')}
                            </Label>
                            <Input
                                id="statement-order"
                                type="number"
                                min={0}
                                value={form.data.sort_order}
                                onChange={(e) =>
                                    form.setData(
                                        'sort_order',
                                        Number(e.target.value),
                                    )
                                }
                            />
                            <InputError message={form.errors.sort_order} />
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="statement-reverse"
                            checked={form.data.reverse_scored}
                            onCheckedChange={(checked) =>
                                form.setData('reverse_scored', checked)
                            }
                        />
                        <Label htmlFor="statement-reverse">
                            {t('Reverse scored (agreeing is unfavourable)')}
                        </Label>
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="statement-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                        <Label htmlFor="statement-active">{t('Active')}</Label>
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This statement will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(statementRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

EngagementStatements.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Engagement Survey', href: statementRoutes.index() },
        { title: 'Question Bank', href: statementRoutes.index() },
    ],
};
