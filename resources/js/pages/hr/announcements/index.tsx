import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    ChartNoAxesColumnIncreasing,
    Eye,
    LayoutDashboard,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DocumentInput } from '@/components/document-input';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DocumentLink } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import announcementRoutes from '@/routes/hr/announcements';
import type { Paginated, TableFilters } from '@/types';
import { AnnouncementFlags } from './show';

type Option = { id: number; name: string };
type Department = Option & { branch: Option | null };

type Announcement = {
    id: number;
    title: string;
    category: string;
    description: string | null;
    content: string;
    start_date: string;
    end_date: string | null;
    is_featured: boolean;
    is_high_priority: boolean;
    is_company_wide: boolean;
    status: 'active' | 'upcoming' | 'expired';
    file_name: string | null;
    departments: Option[];
    branches: Option[];
};

const blank = {
    title: '',
    category: '',
    description: '',
    content: '',
    start_date: '',
    end_date: '',
    is_featured: false,
    is_high_priority: false,
    is_company_wide: true,
    department_ids: [] as number[],
    branch_ids: [] as number[],
    document: null as File | null,
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function Announcements({
    announcements,
    departments,
    branches,
    categories,
    filters,
}: {
    announcements: Paginated<Announcement>;
    departments: Department[];
    branches: Option[];
    categories: string[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Announcement | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Announcement | null>(null);
    const form = useForm(blank);

    const openForm = (a: Announcement | null) => {
        setEditing(a);
        form.clearErrors();
        form.setData(
            a
                ? {
                      title: a.title,
                      category: a.category,
                      description: a.description ?? '',
                      content: a.content,
                      start_date: a.start_date,
                      end_date: a.end_date ?? '',
                      is_featured: a.is_featured,
                      is_high_priority: a.is_high_priority,
                      is_company_wide: a.is_company_wide,
                      department_ids: a.departments.map((d) => d.id),
                      branch_ids: a.branches.map((b) => b.id),
                      document: null,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
    const submit = () =>
        form.post(
            editing
                ? announcementRoutes.update.form(editing.id).action
                : announcementRoutes.store().url,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
            },
        );

    const filter = (changes: TableFilters) =>
        router.get(
            announcementRoutes.index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...changes }).filter(
                    ([, value]) => value !== '' && value != null,
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const departmentLabel = (d: Department) =>
        d.branch ? `${d.name} (${d.branch.name})` : d.name;

    const target = (a: Announcement) =>
        a.is_company_wide ? (
            <Badge
                variant="outline"
                className="border-blue-200 bg-blue-50 text-blue-700"
            >
                {t('Company-wide')}
            </Badge>
        ) : (
            <div className="grid justify-items-start gap-1">
                {a.departments.length > 0 && (
                    <Badge
                        variant="outline"
                        className="border-emerald-200 bg-emerald-50 text-emerald-700"
                        title={a.departments.map((o) => o.name).join(', ')}
                    >
                        {t(':count Departments', {
                            count: a.departments.length,
                        })}
                    </Badge>
                )}
                {a.branches.length > 0 && (
                    <Badge
                        variant="outline"
                        className="border-amber-200 bg-amber-50 text-amber-700"
                        title={a.branches.map((o) => o.name).join(', ')}
                    >
                        {t(':count Branches', { count: a.branches.length })}
                    </Badge>
                )}
            </div>
        );

    const columns: Column<Announcement>[] = [
        {
            key: 'title',
            label: 'Title',
            sortable: true,
            className: 'max-w-sm',
            render: (a) => (
                <div className="grid gap-1">
                    <div className="font-medium">{a.title}</div>
                    <div className="flex flex-wrap gap-1">
                        <AnnouncementFlags a={a} />
                    </div>
                </div>
            ),
        },
        {
            key: 'category',
            label: 'Category',
            render: (a) => <Badge variant="outline">{t(a.category)}</Badge>,
        },
        {
            key: 'start_date',
            label: 'Date Range',
            sortable: true,
            render: (a) => (
                <div className="grid justify-items-start gap-1">
                    {[a.start_date, a.end_date].map(
                        (d, i) =>
                            d && (
                                <span
                                    key={i}
                                    className="flex items-center gap-2 whitespace-nowrap"
                                >
                                    <CalendarDays className="size-4 text-muted-foreground" />
                                    {date(d)}
                                </span>
                            ),
                    )}
                    <StatusBadge status={a.status} />
                </div>
            ),
        },
        { key: 'target', label: 'Audience', render: target },
        {
            key: 'attachments',
            label: 'Attachments',
            render: (a) => (
                <DocumentLink
                    href={announcementRoutes.document.url(a.id)}
                    fileName={a.file_name}
                />
            ),
        },
    ];

    const select = (
        key: string,
        label: string,
        options: [string | number, string][],
    ) => (
        <SelectField
            aria-label={t(label)}
            className="w-auto"
            value={filters[key] ?? ''}
            onChange={(e) => filter({ [key]: e.target.value })}
        >
            <option value="">{t(label)}</option>
            {options.map(([value, text]) => (
                <option key={value} value={value}>
                    {text}
                </option>
            ))}
        </SelectField>
    );

    const toggle = (
        key: 'is_featured' | 'is_high_priority' | 'is_company_wide',
        label: string,
    ) => (
        <div className="flex items-center gap-3">
            <Switch
                id={`announcement-${key}`}
                checked={form.data[key]}
                onCheckedChange={(checked) => form.setData(key, checked)}
            />
            <Label htmlFor={`announcement-${key}`}>{t(label)}</Label>
        </div>
    );

    const checklist = (
        key: 'department_ids' | 'branch_ids',
        label: string,
        options: { id: number; label: string }[],
    ) => (
        <fieldset className="grid gap-2">
            <legend className="mb-2 text-sm font-medium">{t(label)}</legend>
            <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3">
                {options.map((o) => (
                    <label
                        key={o.id}
                        className="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            checked={form.data[key].includes(o.id)}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    key,
                                    checked
                                        ? [...form.data[key], o.id]
                                        : form.data[key].filter(
                                              (id) => id !== o.id,
                                          ),
                                )
                            }
                        />
                        {o.label}
                    </label>
                ))}
            </div>
            <InputError message={form.errors[key]} />
        </fieldset>
    );

    return (
        <>
            <Head title={t('Announcements')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Announcements"
                    description="Create and manage announcements for your organization."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={announcementRoutes.dashboard()}>
                                    <LayoutDashboard /> {t('Dashboard View')}
                                </Link>
                            </Button>
                            {can('create-announcements') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Add Announcement')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={announcements}
                    columns={columns}
                    filters={filters}
                    url={announcementRoutes.index()}
                    toolbar={
                        <>
                            {select(
                                'department_id',
                                'All Department',
                                departments.map((d) => [
                                    d.id,
                                    departmentLabel(d),
                                ]),
                            )}
                            {select(
                                'branch_id',
                                'All Branch',
                                branches.map((b) => [b.id, b.name]),
                            )}
                            <Input
                                type="date"
                                aria-label={t('From Date')}
                                className="w-auto"
                                value={filters.date_from ?? ''}
                                onChange={(e) =>
                                    filter({ date_from: e.target.value })
                                }
                            />
                            <Input
                                type="date"
                                aria-label={t('To Date')}
                                className="w-auto"
                                value={filters.date_to ?? ''}
                                onChange={(e) =>
                                    filter({ date_to: e.target.value })
                                }
                            />
                        </>
                    }
                    moreFilters={
                        <>
                            {select(
                                'category',
                                'All Categories',
                                categories.map((c) => [c, t(c)]),
                            )}
                            {select('status', 'All Status', [
                                ['active', t('Active')],
                                ['upcoming', t('Upcoming')],
                                ['expired', t('Expired')],
                            ])}
                            {select('priority', 'All Priorities', [
                                ['high', t('High Priority')],
                                ['normal', t('Normal')],
                            ])}
                            {select('featured', 'All', [
                                ['yes', t('Featured')],
                                ['no', t('Not Featured')],
                            ])}
                        </>
                    }
                    actions={(a) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={announcementRoutes.show(a.id)}>
                                    <Eye />
                                </Link>
                            </Button>
                            {can('edit-announcements') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(a)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Statistics')}
                                title={t('Statistics')}
                                asChild
                            >
                                <Link
                                    href={announcementRoutes.statistics(a.id)}
                                >
                                    <ChartNoAxesColumnIncreasing />
                                </Link>
                            </Button>
                            {can('delete-announcements') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(a)}
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
                title={editing ? 'Edit Announcement' : 'Add New Announcement'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="announcement-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="announcement-title"
                            required
                            placeholder={t('e.g. Hari Raya Open House 2026')}
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="announcement-category">
                            {t('Category')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="announcement-category"
                            required
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                        >
                            <option value="">{t('Select Category')}</option>
                            {categories.map((c) => (
                                <option key={c} value={c}>
                                    {t(c)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.category} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="announcement-description">
                            {t('Short Description')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="announcement-description"
                            rows={3}
                            required
                            placeholder={t(
                                'e.g. Brief summary of the announcement...',
                            )}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="announcement-content">
                            {t('Content')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="announcement-content"
                            rows={8}
                            required
                            className={textareaClass}
                            value={form.data.content}
                            onChange={(e) =>
                                form.setData('content', e.target.value)
                            }
                        />
                        <InputError message={form.errors.content} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="announcement-start">
                                {t('Start Date')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="announcement-start"
                                type="date"
                                required
                                value={form.data.start_date}
                                onChange={(e) =>
                                    form.setData('start_date', e.target.value)
                                }
                            />
                            <InputError message={form.errors.start_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="announcement-end">
                                {t('End Date')}
                            </Label>
                            <Input
                                id="announcement-end"
                                type="date"
                                min={form.data.start_date || undefined}
                                value={form.data.end_date}
                                onChange={(e) =>
                                    form.setData('end_date', e.target.value)
                                }
                            />
                            <InputError message={form.errors.end_date} />
                        </div>
                    </div>
                    <DocumentInput
                        id="announcement-document"
                        label="Attachments"
                        currentName={editing?.file_name}
                        hasNewFile={form.data.document !== null}
                        error={form.errors.document}
                        onChange={(file) => form.setData('document', file)}
                    />
                    <div className="grid gap-3">
                        {toggle('is_featured', 'Featured Announcement')}
                        {toggle('is_high_priority', 'High Priority')}
                        {toggle('is_company_wide', 'Company-wide Announcement')}
                    </div>
                    {!form.data.is_company_wide && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {checklist(
                                'branch_ids',
                                'Target Branches',
                                branches.map((b) => ({
                                    id: b.id,
                                    label: b.name,
                                })),
                            )}
                            {checklist(
                                'department_ids',
                                'Target Departments',
                                departments.map((d) => ({
                                    id: d.id,
                                    label: departmentLabel(d),
                                })),
                            )}
                        </div>
                    )}
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This announcement will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(announcementRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Announcements.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: announcementRoutes.index() },
        { title: 'Announcements', href: announcementRoutes.index() },
    ],
};
