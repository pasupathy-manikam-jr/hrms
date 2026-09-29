import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ViewDialog } from '@/components/view-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import currencyRoutes from '@/routes/currencies';
import type { Paginated, TableFilters } from '@/types';

type Currency = {
    id: number;
    name: string;
    code: string;
    symbol: string;
    description: string | null;
    is_default: boolean;
};

const FIELDS = [
    ['name', 'Name', true],
    ['code', 'Code', true],
    ['symbol', 'Symbol', true],
    ['description', 'Description', false],
] as const;

const blank = { name: '', code: '', symbol: '', description: '' };

export default function Currencies({
    currencies,
    filters,
}: {
    currencies: Paginated<Currency>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<Currency | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Currency | null>(null);
    const [viewing, setViewing] = useState<Currency | null>(null);
    const form = useForm(blank);

    const openForm = (currency: Currency | null) => {
        setEditing(currency);
        form.clearErrors();
        form.setData(
            currency
                ? { ...currency, description: currency.description ?? '' }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Currency>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (c) => <span className="font-medium">{c.name}</span>,
        },
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (c) => <IdBadge>{c.code}</IdBadge>,
        },
        {
            key: 'symbol',
            label: 'Symbol',
            sortable: true,
            render: (c) => c.symbol,
        },
        {
            key: 'is_default',
            label: 'Default',
            render: (c) => <StatusBadge status={c.is_default ? 'yes' : 'no'} />,
        },
    ];

    return (
        <>
            <Head title={t('Currency')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Currency"
                    description="Manage the currencies available in the system."
                    action={
                        can('create-currencies') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Currency')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={currencies}
                    columns={columns}
                    filters={filters}
                    url={currencyRoutes.index()}
                    actions={(currency) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(currency)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-currencies') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(currency)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-currencies') &&
                                !currency.is_default && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(currency)}
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
                title={editing ? 'Edit Currency' : 'Add Currency'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? currencyRoutes.update(editing.id)
                            : currencyRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {FIELDS.map(([key, label, required]) => (
                        <div
                            key={key}
                            className={
                                key === 'description'
                                    ? 'grid gap-2 sm:col-span-2'
                                    : 'grid gap-2'
                            }
                        >
                            <Label htmlFor={`currency-${key}`}>
                                {t(label)}
                                {required && (
                                    <span className="text-destructive">*</span>
                                )}
                            </Label>
                            <Input
                                id={`currency-${key}`}
                                required={required}
                                maxLength={key === 'code' ? 3 : undefined}
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                </div>
            </FormDialog>

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Currency Details"
                fields={
                    viewing
                        ? [
                              ['Name', viewing.name],
                              ['Code', viewing.code],
                              ['Symbol', viewing.symbol],
                              ['Default', t(viewing.is_default ? 'Yes' : 'No')],
                              ['Description', viewing.description, true],
                          ]
                        : []
                }
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This currency will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(currencyRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Currencies.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Currency', href: currencyRoutes.index() },
    ],
};
