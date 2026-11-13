import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, ChartBar, Plus, SquarePen, Trash2 } from 'lucide-react';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import campaignRoutes from '@/routes/engagement/campaigns';
import type { Paginated, TableFilters } from '@/types';

type Campaign = {
    id: number;
    name: string;
    description: string | null;
    status: 'draft' | 'open' | 'closed';
    closes_on: string | null;
    min_group_size: number;
    responses_count: number;
    statements_count: number;
    statements: { id: number }[];
    created_at: string;
};

type Statement = {
    id: number;
    text: string;
    element: string | null;
    is_active: boolean;
};

const STATUSES = ['draft', 'open', 'closed'] as const;

const blank = {
    name: '',
    description: '',
    status: 'draft' as Campaign['status'],
    closes_on: '',
    min_group_size: 5,
    statement_ids: [] as number[],
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function EngagementCampaigns({
    campaigns,
    filters,
    statements,
}: {
    campaigns: Paginated<Campaign>;
    filters: TableFilters;
    statements: Statement[];
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Campaign | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Campaign | null>(null);
    const form = useForm(blank);

    const openForm = (campaign: Campaign | null) => {
        setEditing(campaign);
        form.clearErrors();
        form.setData(
            campaign
                ? {
                      name: campaign.name,
                      description: campaign.description ?? '',
                      status: campaign.status,
                      closes_on: campaign.closes_on ?? '',
                      min_group_size: campaign.min_group_size,
                      statement_ids: campaign.statements.map((s) => s.id),
                  }
                : {
                      ...blank,
                      statement_ids: statements
                          .filter((s) => s.is_active)
                          .map((s) => s.id),
                  },
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? campaignRoutes.update(editing.id)
                : campaignRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<Campaign>[] = [
        {
            key: 'name',
            label: 'Campaign',
            sortable: true,
            render: (c) => (
                <div>
                    <Link
                        href={campaignRoutes.show(c.id)}
                        className="font-medium hover:underline"
                    >
                        {c.name}
                    </Link>
                    <div className="text-muted-foreground">
                        {t(':count statements', {
                            count: String(c.statements_count),
                        })}
                    </div>
                </div>
            ),
        },
        {
            key: 'responses_count',
            label: 'Responses',
            render: (c) => c.responses_count,
        },
        {
            key: 'closes_on',
            label: 'Closes On',
            sortable: true,
            render: (c) =>
                c.closes_on ? (
                    <span className="flex items-center gap-2 whitespace-nowrap">
                        <CalendarDays className="size-4 text-muted-foreground" />
                        {date(c.closes_on)}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (c) => <StatusBadge status={c.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Engagement Campaigns')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Engagement Campaigns"
                    description="Run employee engagement surveys and see the results by element, tier and group."
                    action={
                        can('create-engagement-survey') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Campaign')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={campaigns}
                    columns={columns}
                    filters={filters}
                    url={campaignRoutes.index()}
                    toolbar={
                        <FilterSelect
                            url={campaignRoutes.index()}
                            filters={filters}
                            name="status"
                            label="All Statuses"
                            options={STATUSES.map((s) => ({
                                id: s,
                                name: t(s.charAt(0).toUpperCase() + s.slice(1)),
                            }))}
                        />
                    }
                    actions={(campaign) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Results')}
                                asChild
                            >
                                <Link href={campaignRoutes.show(campaign.id)}>
                                    <ChartBar />
                                </Link>
                            </Button>
                            {can('edit-engagement-survey') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(campaign)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-engagement-survey') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(campaign)}
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
                title={editing ? 'Edit Campaign' : 'Add Campaign'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="campaign-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="campaign-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="campaign-description">
                            {t('Message to employees')}
                        </Label>
                        <textarea
                            id="campaign-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="campaign-status">{t('Status')}</Label>
                        <SelectField
                            id="campaign-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Campaign['status'],
                                )
                            }
                        >
                            <option value="draft">{t('Draft')}</option>
                            <option value="open">
                                {t('Open (employees can answer)')}
                            </option>
                            <option value="closed">{t('Closed')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="campaign-closes">
                            {t('Closes On')}
                        </Label>
                        <Input
                            id="campaign-closes"
                            type="date"
                            value={form.data.closes_on}
                            onChange={(e) =>
                                form.setData('closes_on', e.target.value)
                            }
                        />
                        <InputError message={form.errors.closes_on} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="campaign-min-group">
                            {t('Smallest group shown in results')}
                        </Label>
                        <Input
                            id="campaign-min-group"
                            type="number"
                            min={3}
                            max={50}
                            value={form.data.min_group_size}
                            onChange={(e) =>
                                form.setData(
                                    'min_group_size',
                                    Number(e.target.value),
                                )
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Groups with fewer respondents are hidden so answers cannot be traced to a person.',
                            )}
                        </p>
                        <InputError message={form.errors.min_group_size} />
                    </div>
                    <div className="sm:col-span-2">
                        <QuestionPicker
                            statements={statements}
                            selected={form.data.statement_ids}
                            onChange={(ids) =>
                                form.setData('statement_ids', ids)
                            }
                            locked={(editing?.responses_count ?? 0) > 0}
                        />
                        <InputError message={form.errors.statement_ids} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This campaign and all its responses will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(campaignRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

/**
 * Tick the statements to ask, grouped by element (bank order). Inactive statements appear only when already picked.
 */
function QuestionPicker({
    statements,
    selected,
    onChange,
    locked,
}: {
    statements: Statement[];
    selected: number[];
    onChange: (ids: number[]) => void;
    locked: boolean;
}) {
    const { t } = useTranslation();
    const picked = new Set(selected);
    const shown = statements.filter((s) => s.is_active || picked.has(s.id));
    const groups = [...new Set(shown.map((s) => s.element))].map((element) => ({
        element,
        items: shown.filter((s) => s.element === element),
    }));
    const setMany = (ids: number[], on: boolean) => {
        const next = new Set(picked);
        ids.forEach((id) => (on ? next.add(id) : next.delete(id)));
        onChange(shown.filter((s) => next.has(s.id)).map((s) => s.id));
    };

    return (
        <fieldset className="grid gap-2" disabled={locked}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <legend className="text-sm font-medium">
                    {t('Questions')}{' '}
                    <span className="font-normal text-muted-foreground">
                        {t(':selected of :total selected', {
                            selected: String(selected.length),
                            total: String(shown.length),
                        })}
                    </span>
                </legend>
                {!locked && (
                    <div className="flex gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                setMany(
                                    shown.map((s) => s.id),
                                    true,
                                )
                            }
                        >
                            {t('Select all')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => onChange([])}
                        >
                            {t('Clear')}
                        </Button>
                    </div>
                )}
            </div>
            {locked && (
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Employees have already answered, so the questions can no longer change.',
                    )}
                </p>
            )}
            <div className="max-h-80 overflow-y-auto rounded-md border">
                {groups.map(({ element, items }) => {
                    const ids = items.map((s) => s.id);
                    const count = ids.filter((id) => picked.has(id)).length;

                    return (
                        <div
                            key={element ?? 'overall'}
                            className="border-b last:border-b-0"
                        >
                            <label className="sticky top-0 flex items-center gap-2 bg-muted px-3 py-2 text-sm font-medium">
                                <Checkbox
                                    checked={
                                        count === ids.length
                                            ? true
                                            : count > 0
                                              ? 'indeterminate'
                                              : false
                                    }
                                    onCheckedChange={(on) =>
                                        setMany(ids, on === true)
                                    }
                                    disabled={locked}
                                />
                                {element ?? t('Overall')}
                                <span className="ms-auto text-xs font-normal text-muted-foreground">
                                    {count}/{ids.length}
                                </span>
                            </label>
                            {items.map((statement) => (
                                <label
                                    key={statement.id}
                                    className="flex items-start gap-2 px-3 py-1.5 text-sm"
                                >
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={picked.has(statement.id)}
                                        onCheckedChange={(on) =>
                                            setMany([statement.id], on === true)
                                        }
                                        disabled={locked}
                                    />
                                    <span>
                                        {statement.text}
                                        {!statement.is_active && (
                                            <span className="ms-1 text-xs text-muted-foreground">
                                                ({t('inactive')})
                                            </span>
                                        )}
                                    </span>
                                </label>
                            ))}
                        </div>
                    );
                })}
            </div>
        </fieldset>
    );
}

EngagementCampaigns.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Engagement Survey', href: campaignRoutes.index() },
        { title: 'Campaigns', href: campaignRoutes.index() },
    ],
};
