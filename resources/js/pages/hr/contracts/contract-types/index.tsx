import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Clock,
    Eye,
    Lock,
    LockOpen,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { ViewDialog } from '@/components/view-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell } from '@/components/table-cells';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import contractTypeRoutes from '@/routes/hr/contracts/contract-types';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';
import type { Paginated, TableFilters } from '@/types';

type ContractType = {
    id: number;
    name: string;
    description: string | null;
    default_duration_months: number | null;
    probation_period_months: number;
    notice_period_days: number;
    is_renewable: boolean;
    status: string;
    contracts_count: number;
    created_at: string;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    name: '',
    description: '',
    default_duration_months: '' as number | string,
    probation_period_months: 0 as number | string,
    notice_period_days: 30 as number | string,
    is_renewable: false,
    status: 'active',
};

export default function ContractTypes({
    contractTypes,
    statusCounts,
    filters,
}: {
    contractTypes: Paginated<ContractType>;
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = contractTypeRoutes.index();
    const [editing, setEditing] = useState<ContractType | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<ContractType | null>(null);
    const [viewing, setViewing] = useState<ContractType | null>(null);
    const duration = (row: ContractType) =>
        row.default_duration_months ? (
            <span className="flex items-center gap-1.5 whitespace-nowrap">
                <CalendarDays className="size-4 text-muted-foreground" />
                {row.default_duration_months} {t('months')}
            </span>
        ) : (
            <Badge
                variant="outline"
                className="border-blue-200 bg-blue-50 text-blue-700"
            >
                {t('Permanent')}
            </Badge>
        );
    const form = useForm(blank);

    const openForm = (type: ContractType | null) => {
        setEditing(type);
        form.clearErrors();
        form.setData(
            type
                ? {
                      name: type.name,
                      description: type.description ?? '',
                      default_duration_months:
                          type.default_duration_months ?? '',
                      probation_period_months: type.probation_period_months,
                      notice_period_days: type.notice_period_days,
                      is_renewable: type.is_renewable,
                      status: type.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<ContractType>[] = [
        {
            key: 'name',
            label: 'Contract Type',
            sortable: true,
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'default_duration_months',
            label: 'Duration',
            render: duration,
        },
        {
            key: 'probation',
            label: 'Probation',
            render: (row) => (
                <span className="flex items-center gap-1.5 whitespace-nowrap">
                    <Clock className="size-4 text-muted-foreground" />
                    {row.probation_period_months} {t('months')}
                </span>
            ),
        },
        {
            key: 'notice_period_days',
            label: 'Notice Period',
            render: (row) => `${row.notice_period_days} ${t('days')}`,
        },
        {
            key: 'contracts_count',
            label: 'Contracts',
            render: (row) => (
                <span className="rounded-full border px-2 py-0.5 text-xs whitespace-nowrap">
                    {t(':count contracts', { count: row.contracts_count })}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (row) => <DateCell value={row.created_at} />,
        },
    ];

    const field = (
        name: keyof typeof blank,
        label: string,
        input: ReactNode,
        wide = false,
    ) => (
        <div className={wide ? 'grid gap-2 sm:col-span-2' : 'grid gap-2'}>
            <Label htmlFor={`contract-type-${name}`}>{t(label)}</Label>
            {input}
            <InputError message={form.errors[name]} />
        </div>
    );

    const number = (name: keyof typeof blank, required = true) => (
        <Input
            id={`contract-type-${name}`}
            type="number"
            min={name === 'default_duration_months' ? 1 : 0}
            required={required}
            value={form.data[name] as number | string}
            onChange={(e) => form.setData(name, e.target.value)}
        />
    );

    return (
        <>
            <Head title={t('Contract Types')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Contract Types"
                    description="Define categories used to classify employee contracts."
                    action={
                        can('create-contract-types') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Contract Type')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    data={contractTypes}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="is_renewable"
                            label="All"
                            options={[
                                { id: 'yes', name: t('Renewable') },
                                { id: 'no', name: t('Not Renewable') },
                            ]}
                        />
                    }
                    actions={(type) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(type)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-contract-types') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(type)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            type.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            type.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                contractTypeRoutes.toggleStatus(
                                                    type.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {type.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-contract-types') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(type)}
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
                title={editing ? 'Edit Contract Type' : 'Add Contract Type'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? contractTypeRoutes.update(editing.id)
                            : contractTypeRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {field(
                        'name',
                        'Name',
                        <Input
                            id="contract-type-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />,
                        true,
                    )}
                    {field(
                        'default_duration_months',
                        'Default Duration (months)',
                        number('default_duration_months', false),
                    )}
                    {field(
                        'probation_period_months',
                        'Probation Period (months)',
                        number('probation_period_months'),
                    )}
                    {field(
                        'notice_period_days',
                        'Notice Period (days)',
                        number('notice_period_days'),
                    )}
                    {field(
                        'status',
                        'Status',
                        <SelectField
                            id="contract-type-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>,
                    )}
                    {field(
                        'description',
                        'Description',
                        <textarea
                            id="contract-type-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />,
                        true,
                    )}
                    <div className="flex items-center gap-3">
                        <Switch
                            id="contract-type-renewable"
                            checked={form.data.is_renewable}
                            onCheckedChange={(checked) =>
                                form.setData('is_renewable', checked)
                            }
                        />
                        <Label htmlFor="contract-type-renewable">
                            {t('Renewable')}
                        </Label>
                    </div>
                </div>
            </FormDialog>

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Contract Type Details"
                fields={
                    viewing
                        ? [
                              ['Name', viewing.name],
                              [
                                  'Status',
                                  <StatusBadge
                                      key="status"
                                      status={viewing.status}
                                  />,
                              ],
                              ['Duration', duration(viewing)],
                              [
                                  'Probation',
                                  `${viewing.probation_period_months} ${t('months')}`,
                              ],
                              [
                                  'Notice Period',
                                  `${viewing.notice_period_days} ${t('days')}`,
                              ],
                              [
                                  'Renewable',
                                  viewing.is_renewable ? t('Yes') : t('No'),
                              ],
                              ['Contracts', viewing.contracts_count],
                              ['Description', viewing.description, true],
                          ]
                        : []
                }
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This contract type will be deleted. Contracts and templates using it are kept without a type."
                onConfirm={() =>
                    deleting &&
                    router.delete(contractTypeRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

ContractTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'Contract Types', href: contractTypeRoutes.index() },
    ],
};
