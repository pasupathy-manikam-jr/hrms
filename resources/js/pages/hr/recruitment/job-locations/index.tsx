import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Lock, LockOpen, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
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
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import jobLocationRoutes from '@/routes/hr/recruitment/job-locations';
import type { Paginated, TableFilters } from '@/types';

type JobLocation = {
    id: number;
    name: string;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    is_remote: boolean;
    status: 'active' | 'inactive';
    created_at: string;
};

const fullAddress = (l: JobLocation, withPostcode = false) =>
    [l.address, l.city, l.state, l.country, withPostcode && l.postal_code]
        .filter(Boolean)
        .join(', ') || '—';

const FIELDS = [
    ['address', 'Address'],
    ['city', 'City'],
    ['state', 'State'],
    ['country', 'Country'],
    ['postal_code', 'Postcode'],
] as const;

const blank = {
    name: '',
    address: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    is_remote: false,
    status: 'active' as JobLocation['status'],
};

export default function JobLocations({
    jobLocations,
    statusCounts,
    filters,
}: {
    jobLocations: Paginated<JobLocation>;
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<JobLocation | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<JobLocation | null>(null);
    const [viewing, setViewing] = useState<JobLocation | null>(null);
    const form = useForm(blank);
    const url = jobLocationRoutes.index();

    const openForm = (location: JobLocation | null) => {
        setEditing(location);
        form.clearErrors();
        form.setData(
            location
                ? {
                      name: location.name,
                      address: location.address ?? '',
                      city: location.city ?? '',
                      state: location.state ?? '',
                      country: location.country ?? '',
                      postal_code: location.postal_code ?? '',
                      is_remote: location.is_remote,
                      status: location.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? jobLocationRoutes.update(editing.id)
                : jobLocationRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<JobLocation>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'address',
            label: 'Address',
            render: (row) =>
                row.is_remote ? (
                    <span className="text-blue-600">{t('Remote Work')}</span>
                ) : (
                    fullAddress(row)
                ),
        },
        {
            key: 'is_remote',
            label: 'Type',
            render: (row) => (
                <StatusBadge
                    status={row.is_remote ? 'remote' : 'on-site'}
                    label={row.is_remote ? 'Remote' : 'On-site'}
                />
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

    return (
        <>
            <Head title={t('Job Locations')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Job Locations"
                    description="Manage job locations where positions are available."
                    action={
                        can('create-job-locations') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Job Location')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={jobLocations}
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
                            name="is_remote"
                            label="All Types"
                            options={[
                                { id: '1', name: t('Remote') },
                                { id: '0', name: t('On-site') },
                            ]}
                        />
                    }
                    actions={(location) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(location)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-job-locations') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(location)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            location.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            location.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                jobLocationRoutes.toggleStatus(
                                                    location.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {location.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-job-locations') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(location)}
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
                title={editing ? 'Edit Job Location' : 'Add Job Location'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="job-location-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="job-location-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    {FIELDS.map(([key, label]) => (
                        <div
                            key={key}
                            className={
                                key === 'address'
                                    ? 'grid gap-2 sm:col-span-2'
                                    : 'grid gap-2'
                            }
                        >
                            <Label htmlFor={`job-location-${key}`}>
                                {t(label)}
                            </Label>
                            <Input
                                id={`job-location-${key}`}
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="job-location-status">
                            {t('Status')}
                        </Label>
                        <SelectField
                            id="job-location-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as JobLocation['status'],
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="job-location-remote"
                            checked={form.data.is_remote}
                            onCheckedChange={(checked) =>
                                form.setData('is_remote', checked)
                            }
                        />
                        <Label htmlFor="job-location-remote">
                            {t('Remote Location')}
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
                        <DialogTitle>{viewing?.name}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Address')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.is_remote
                                        ? t('Remote Work')
                                        : fullAddress(viewing, true)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Type')}
                                </dt>
                                <dd>
                                    <StatusBadge
                                        status={
                                            viewing.is_remote
                                                ? 'remote'
                                                : 'on-site'
                                        }
                                        label={
                                            viewing.is_remote
                                                ? 'Remote'
                                                : 'On-site'
                                        }
                                    />
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
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This job location will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(jobLocationRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

JobLocations.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: jobLocationRoutes.index() },
        { title: 'Job Locations', href: jobLocationRoutes.index() },
    ],
};
