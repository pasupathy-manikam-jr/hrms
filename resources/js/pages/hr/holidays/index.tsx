import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarRange,
    Eye,
    List,
    Plus,
    Repeat,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ViewToggle } from '@/components/view-toggle';
import { applyFilters, FilterSelect } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import branchRoutes from '@/routes/hr/branches';
import holidayRoutes from '@/routes/hr/holidays';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Holiday = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    category: string;
    description: string | null;
    is_paid: boolean;
    is_half_day: boolean;
    is_recurring: boolean;
    branches: Option[];
};

const blank = {
    name: '',
    start_date: '',
    end_date: '',
    category: 'national',
    description: '',
    is_paid: true,
    is_half_day: false,
    is_recurring: false,
    branch_ids: [] as number[],
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

// "company-specific" -> "Company Specific"
const categoryLabel = (c: string) =>
    c.replace(/-/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());

export default function Holidays({
    holidays,
    branches,
    categories,
    years,
    filters,
}: {
    holidays: Paginated<Holiday>;
    branches: Option[];
    categories: string[];
    years: number[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Holiday | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Holiday | null>(null);
    const [viewing, setViewing] = useState<Holiday | null>(null);
    const form = useForm(blank);
    const url = holidayRoutes.index();

    const openForm = (h: Holiday | null) => {
        setEditing(h);
        form.clearErrors();
        form.setData(
            h
                ? {
                      name: h.name,
                      start_date: h.start_date,
                      end_date: h.end_date,
                      category: h.category,
                      description: h.description ?? '',
                      is_paid: h.is_paid,
                      is_half_day: h.is_half_day,
                      is_recurring: h.is_recurring,
                      branch_ids: h.branches.map((b) => b.id),
                  }
                : { ...blank, branch_ids: branches.map((b) => b.id) },
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? holidayRoutes.update(editing.id) : holidayRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<Holiday>[] = [
        {
            key: 'name',
            label: 'Holiday Name',
            sortable: true,
            render: (h) => (
                <div className="flex items-center gap-2 font-medium">
                    {h.name}
                    {h.is_recurring && (
                        <Repeat
                            className="size-3.5 text-muted-foreground"
                            aria-label={t('Recurring')}
                        />
                    )}
                </div>
            ),
        },
        {
            key: 'start_date',
            label: 'Date',
            sortable: true,
            render: (h) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {date(h.start_date)}
                    {h.end_date !== h.start_date && ` – ${date(h.end_date)}`}
                </span>
            ),
        },
        {
            key: 'category',
            label: 'Category',
            sortable: true,
            render: (h) => <StatusBadge status={h.category} />,
        },
        {
            key: 'branches',
            label: 'Branches',
            render: (h) =>
                h.branches.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        <Badge variant="outline">{h.branches[0].name}</Badge>
                        {h.branches.length > 1 && (
                            <Badge
                                variant="outline"
                                title={h.branches
                                    .slice(1)
                                    .map((b) => b.name)
                                    .join(', ')}
                            >
                                {t('+:count more', {
                                    count: h.branches.length - 1,
                                })}
                            </Badge>
                        )}
                    </div>
                ),
        },
    ];

    const toggle = (
        key: 'is_paid' | 'is_half_day' | 'is_recurring',
        label: string,
    ) => (
        <div className="flex items-center gap-3">
            <Switch
                id={`holiday-${key}`}
                checked={form.data[key]}
                onCheckedChange={(checked) => form.setData(key, checked)}
            />
            <Label htmlFor={`holiday-${key}`}>{t(label)}</Label>
        </div>
    );

    return (
        <>
            <Head title={t('Holidays')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Holidays"
                    description="Manage company holidays for each branch."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <ViewToggle
                                current="List"
                                views={[
                                    {
                                        label: 'List',
                                        href: holidayRoutes.index(),
                                        icon: List,
                                    },
                                    {
                                        label: 'Calendar',
                                        href: holidayRoutes.calendar(),
                                        icon: CalendarRange,
                                    },
                                ]}
                            />
                            {can('create-holidays') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Add Holiday')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={holidays}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="category"
                                label="All Categories"
                                options={categories.map((c) => ({
                                    id: c,
                                    name: t(categoryLabel(c)),
                                }))}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="year"
                                label="All Years"
                                options={years.map((y) => ({
                                    id: y,
                                    name: String(y),
                                }))}
                            />
                            <Input
                                type="date"
                                aria-label={t('From Date')}
                                className="w-auto"
                                value={filters.date_from ?? ''}
                                onChange={(e) =>
                                    applyFilters(url, filters, {
                                        date_from: e.target.value,
                                    })
                                }
                            />
                            <Input
                                type="date"
                                aria-label={t('To Date')}
                                className="w-auto"
                                value={filters.date_to ?? ''}
                                onChange={(e) =>
                                    applyFilters(url, filters, {
                                        date_to: e.target.value,
                                    })
                                }
                            />
                        </>
                    }
                    moreFilters={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="branch_id"
                            label="All Branches"
                            options={branches}
                        />
                    }
                    actions={(h) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(h)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-holidays') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(h)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-holidays') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(h)}
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
                title={editing ? 'Edit Holiday' : 'Add Holiday'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="holiday-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="holiday-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="holiday-category">
                            {t('Category')}
                        </Label>
                        <SelectField
                            id="holiday-category"
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                        >
                            {categories.map((c) => (
                                <option key={c} value={c}>
                                    {t(categoryLabel(c))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.category} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="holiday-start">
                            {t('Start Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="holiday-start"
                            type="date"
                            required
                            value={form.data.start_date}
                            onChange={(e) =>
                                form.setData((data) => ({
                                    ...data,
                                    start_date: e.target.value,
                                    end_date: data.end_date || e.target.value,
                                }))
                            }
                        />
                        <InputError message={form.errors.start_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="holiday-end">
                            {t('End Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="holiday-end"
                            type="date"
                            required
                            min={form.data.start_date || undefined}
                            value={form.data.end_date}
                            onChange={(e) =>
                                form.setData('end_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.end_date} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="holiday-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="holiday-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="flex flex-wrap gap-6 sm:col-span-2">
                        {toggle('is_paid', 'Paid Holiday')}
                        {toggle('is_half_day', 'Half Day')}
                        {toggle('is_recurring', 'Recurring Yearly')}
                    </div>
                    <fieldset className="grid gap-2 sm:col-span-2">
                        <legend className="mb-2 text-sm font-medium">
                            {t('Branches')}
                            <span className="text-destructive">*</span>
                        </legend>
                        <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                            {branches.map((b) => (
                                <label
                                    key={b.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.branch_ids.includes(
                                            b.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'branch_ids',
                                                checked
                                                    ? [
                                                          ...form.data
                                                              .branch_ids,
                                                          b.id,
                                                      ]
                                                    : form.data.branch_ids.filter(
                                                          (id) => id !== b.id,
                                                      ),
                                            )
                                        }
                                    />
                                    {b.name}
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.branch_ids} />
                    </fieldset>
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
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Date')}
                                </dt>
                                <dd className="font-medium">
                                    {date(viewing.start_date)}
                                    {viewing.end_date !== viewing.start_date &&
                                        ` – ${date(viewing.end_date)}`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Category')}
                                </dt>
                                <dd>
                                    <StatusBadge status={viewing.category} />
                                </dd>
                            </div>
                            {(
                                [
                                    ['Paid', viewing.is_paid],
                                    ['Half Day', viewing.is_half_day],
                                    ['Recurring', viewing.is_recurring],
                                ] as const
                            ).map(([name, value]) => (
                                <div key={name}>
                                    <dt className="text-muted-foreground">
                                        {t(name)}
                                    </dt>
                                    <dd className="font-medium">
                                        {t(value ? 'Yes' : 'No')}
                                    </dd>
                                </div>
                            ))}
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Branches')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.branches
                                        .map((b) => b.name)
                                        .join(', ') || '—'}
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">
                                    {t('Description')}
                                </dt>
                                <dd className="font-medium">
                                    {viewing.description || '—'}
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This holiday will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(holidayRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Holidays.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: branchRoutes.index() },
        { title: 'Holidays', href: holidayRoutes.index() },
    ],
};
