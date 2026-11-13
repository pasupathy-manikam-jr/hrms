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
    created_at: string;
};

const STATUSES = ['draft', 'open', 'closed'] as const;

const blank = {
    name: '',
    description: '',
    status: 'draft' as Campaign['status'],
    closes_on: '',
    min_group_size: 5,
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function EngagementCampaigns({
    campaigns,
    filters,
    activeStatements,
}: {
    campaigns: Paginated<Campaign>;
    filters: TableFilters;
    activeStatements: number;
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
                  }
                : blank,
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
                    {!editing && (
                        <p className="text-sm text-muted-foreground sm:col-span-2">
                            {t(
                                'The campaign will ask the :count active statements in the question bank.',
                                { count: String(activeStatements) },
                            )}
                        </p>
                    )}
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

EngagementCampaigns.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Engagement Survey', href: campaignRoutes.index() },
        { title: 'Campaigns', href: campaignRoutes.index() },
    ],
};
