import { Head, router, useForm } from '@inertiajs/react';
import {
    CircleCheck,
    Clock,
    Banknote,
    Eye,
    Lock,
    LockOpen,
    Plus,
    Shield,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatCards } from '@/components/stat-cards';
import { StatusBadge } from '@/components/status-badge';
import { StatusTabs } from '@/components/table-filters';
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
import policyRoutes from '@/routes/hr/attendance-policies';
import type { Paginated, TableFilters } from '@/types';

type Policy = {
    id: number;
    name: string;
    description: string | null;
    late_arrival_grace: number;
    early_departure_grace: number;
    half_day_threshold: number;
    overtime_rate_per_hour: number;
    status: 'active' | 'inactive';
};

const blank = {
    name: '',
    description: '',
    late_arrival_grace: 15 as number | string,
    early_departure_grace: 15 as number | string,
    half_day_threshold: 4 as number | string,
    overtime_rate_per_hour: 0 as number | string,
    status: 'active' as Policy['status'],
};

type NumberKey =
    | 'late_arrival_grace'
    | 'early_departure_grace'
    | 'half_day_threshold'
    | 'overtime_rate_per_hour';

export default function AttendancePolicies({
    attendancePolicies,
    stats,
    statusCounts,
    filters,
}: {
    attendancePolicies: Paginated<Policy>;
    stats: {
        total: number;
        active: number;
        avg_late_grace: number;
        avg_overtime_rate: number;
    };
    statusCounts: { all: number; active: number; inactive: number };
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Policy | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Policy | null>(null);
    const [viewing, setViewing] = useState<Policy | null>(null);
    const form = useForm(blank);

    const openForm = (policy: Policy | null) => {
        setEditing(policy);
        form.clearErrors();
        form.setData(
            policy
                ? {
                      name: policy.name,
                      description: policy.description ?? '',
                      late_arrival_grace: policy.late_arrival_grace,
                      early_departure_grace: policy.early_departure_grace,
                      half_day_threshold: policy.half_day_threshold,
                      overtime_rate_per_hour: policy.overtime_rate_per_hour,
                      status: policy.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const minutes = (value: number) => t(':minutes min', { minutes: value });

    const facts = (p: Policy) =>
        [
            [
                Clock,
                'text-orange-500',
                t(':minutes minutes', { minutes: p.late_arrival_grace }),
                'Late Arrival Grace',
            ],
            [
                Banknote,
                'text-emerald-600',
                `${money(p.overtime_rate_per_hour)}/hr`,
                'Overtime Rate',
            ],
            [
                Clock,
                'text-blue-500',
                t(':minutes minutes', { minutes: p.early_departure_grace }),
                'Early Departure Grace',
            ],
            [
                Clock,
                'text-muted-foreground',
                t(':hours hours', { hours: Number(p.half_day_threshold) }),
                'Half Day Threshold',
            ],
        ] as const;

    const numberField = (key: NumberKey, label: string, step = '1') => (
        <div className="grid gap-2">
            <Label htmlFor={`policy-${key}`}>
                {t(label)}
                <span className="text-destructive">*</span>
            </Label>
            <Input
                id={`policy-${key}`}
                type="number"
                min={0}
                step={step}
                required
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
            />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <>
            <Head title={t('Attendance Policies')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Attendance Policies"
                    description="Manage attendance rules and policies for your organization."
                    action={
                        can('create-attendance-policies') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Attendance Policy')}
                            </Button>
                        )
                    }
                />

                <StatCards
                    stats={[
                        {
                            label: 'Total Policies',
                            value: stats.total,
                            note: 'All policies',
                            icon: Shield,
                            tone: 'bg-muted text-muted-foreground',
                        },
                        {
                            label: 'Active Policies',
                            value: stats.active,
                            note: 'Currently active',
                            icon: CircleCheck,
                            tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950',
                        },
                        {
                            label: 'Avg Late Grace',
                            value: minutes(stats.avg_late_grace),
                            note: 'Late arrival grace',
                            icon: Clock,
                            tone: 'bg-orange-100 text-orange-600 dark:bg-orange-950',
                        },
                        {
                            label: 'Avg Overtime Rate',
                            value: money(stats.avg_overtime_rate),
                            note: 'Per hour',
                            icon: Banknote,
                            tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950',
                        },
                    ]}
                />

                <DataTable
                    cardsOnly
                    data={attendancePolicies}
                    columns={[]}
                    renderCard={(p, actions) => (
                        <div className="flex h-full flex-col gap-4 rounded-xl border bg-card p-5 shadow-sm">
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950">
                                    <Shield className="size-5" />
                                </span>
                                <div className="grid min-w-0 flex-1 justify-items-start gap-1.5">
                                    <div className="text-lg leading-tight font-semibold">
                                        {p.name}
                                    </div>
                                    <StatusBadge status={p.status} />
                                </div>
                                {actions}
                            </div>
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                {facts(p).map(([Icon, tone, value, label]) => (
                                    <div
                                        key={label}
                                        className="flex items-start gap-2"
                                    >
                                        <Icon
                                            className={`mt-0.5 size-4 shrink-0 ${tone}`}
                                        />
                                        <div>
                                            <dd className="font-semibold tabular-nums">
                                                {value}
                                            </dd>
                                            <dt className="text-xs text-muted-foreground">
                                                {t(label)}
                                            </dt>
                                        </div>
                                    </div>
                                ))}
                            </dl>
                            {p.description && (
                                <p className="border-t pt-3 text-sm text-muted-foreground">
                                    {p.description}
                                </p>
                            )}
                        </div>
                    )}
                    filters={filters}
                    url={policyRoutes.index()}
                    tabs={
                        <StatusTabs
                            url={policyRoutes.index()}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    actions={(policy) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(policy)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-attendance-policies') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(policy)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            policy.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            policy.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                policyRoutes.toggleStatus(
                                                    policy.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {policy.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-attendance-policies') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(policy)}
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
                title={editing ? 'Edit Policy' : 'Add Policy'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? policyRoutes.update(editing.id)
                            : policyRoutes.store(),
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
                        <Label htmlFor="policy-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="policy-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="policy-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="policy-description"
                            rows={3}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    {numberField(
                        'late_arrival_grace',
                        'Late Arrival Grace (minutes)',
                    )}
                    {numberField(
                        'early_departure_grace',
                        'Early Departure Grace (minutes)',
                    )}
                    {numberField(
                        'half_day_threshold',
                        'Half Day Threshold (hours)',
                        '0.01',
                    )}
                    {numberField(
                        'overtime_rate_per_hour',
                        'Overtime Rate Per Hour',
                        '0.01',
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="policy-status">{t('Status')}</Label>
                        <SelectField
                            id="policy-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as Policy['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{viewing?.name}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Description')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.description || '—'}
                                </dd>
                            </div>
                            {facts(viewing).map(([, , value, label]) => (
                                <div key={label}>
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium">{value}</dd>
                                </div>
                            ))}
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Status')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.status} />
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This attendance policy will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(policyRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

AttendancePolicies.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: policyRoutes.index() },
        { title: 'Attendance Policies', href: policyRoutes.index() },
    ],
};
