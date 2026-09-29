import { Head, router, useForm } from '@inertiajs/react';
import {
    Bell,
    CalendarDays,
    Clock,
    Download,
    Eye,
    EllipsisVertical,
    Globe,
    Package,
    Plus,
    RefreshCw,
    SquarePen,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ActionMenu } from '@/components/action-menu';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell, IdBadge } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { StatCards } from '@/components/stat-cards';
import { StatusBadge } from '@/components/status-badge';
import { ViewDialog } from '@/components/view-dialog';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type HrDocument = {
    id: number;
    title: string;
    description: string | null;
    category_id: number | null;
    version: string;
    effective_date: string | null;
    expiry_date: string | null;
    requires_acknowledgment: boolean;
    status: string;
    file_name: string | null;
    file_size: number | null;
    download_count: number;
    category: (Option & { color: string }) | null;
    uploader: (Option & { email: string; avatar: string | null }) | null;
    created_at: string;
    updated_at: string;
};

const STATUSES = [
    'draft',
    'under_review',
    'approved',
    'published',
    'archived',
    'expired',
];

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const pretty = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const fileSize = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

const blank = {
    title: '',
    description: '',
    category_id: '' as number | string,
    version: '1.0',
    effective_date: '',
    expiry_date: '',
    requires_acknowledgment: false,
    status: 'draft',
    file: null as File | null,
};

export default function HrDocuments({
    hrDocuments,
    categories,
    statusCounts,
    stats,
    uploadTypes,
    uploadMaxKb,
    filters,
}: {
    hrDocuments: Paginated<HrDocument>;
    categories: Option[];
    statusCounts: Record<string, number>;
    stats: Record<string, number>;
    uploadTypes: string[];
    uploadMaxKb: number;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const url = hrDocumentRoutes.index();
    const [editing, setEditing] = useState<HrDocument | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [viewing, setViewing] = useState<HrDocument | null>(null);
    const [changing, setChanging] = useState<HrDocument | null>(null);
    const statusForm = useForm({ status: '' });
    const share = (n: number) =>
        `${stats.total ? Math.round((n / stats.total) * 100) : 0}% ${t('of total')}`;
    const [deleting, setDeleting] = useState<HrDocument | null>(null);
    const form = useForm(blank);

    const openForm = (document: HrDocument | null) => {
        setEditing(document);
        form.clearErrors();
        form.setData(
            document
                ? {
                      title: document.title,
                      description: document.description ?? '',
                      category_id: document.category_id ?? '',
                      version: document.version,
                      effective_date: document.effective_date ?? '',
                      expiry_date: document.expiry_date ?? '',
                      requires_acknowledgment: document.requires_acknowledgment,
                      status: document.status,
                      file: null,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
    const submit = () =>
        form.post(
            editing
                ? hrDocumentRoutes.update.form(editing.id).action
                : hrDocumentRoutes.store().url,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
            },
        );

    const columns: Column<HrDocument>[] = [
        {
            key: 'title',
            label: 'Title',
            sortable: true,
            render: (row) => (
                <div>
                    <div className="font-medium">{row.title}</div>
                    <div className="line-clamp-1 max-w-md text-muted-foreground">
                        {row.description}
                    </div>
                </div>
            ),
        },
        {
            key: 'category',
            label: 'Category',
            render: (row) =>
                row.category ? (
                    <span className="flex items-center gap-2">
                        <span
                            className="size-2.5 rounded-full"
                            style={{ backgroundColor: row.category.color }}
                        />
                        {row.category.name}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            key: 'version',
            label: 'Version',
            sortable: true,
            render: (row) => <IdBadge>v{row.version}</IdBadge>,
        },
        {
            key: 'effective_date',
            label: 'Effective Date',
            sortable: true,
            render: (row) => (
                <DateCell value={row.effective_date}>
                    {row.expiry_date && (
                        <span className="text-xs text-muted-foreground">
                            → {date(row.expiry_date)}
                        </span>
                    )}
                </DateCell>
            ),
        },
        {
            key: 'uploader',
            label: 'Uploaded By',
            render: (row) =>
                row.uploader ? (
                    <PersonCell
                        name={row.uploader.name}
                        detail={row.uploader.email}
                        src={row.uploader.avatar}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'file',
            label: 'File',
            render: (row) =>
                row.file_name ? (
                    <div className="text-xs">
                        <div className="max-w-40 truncate">{row.file_name}</div>
                        <div className="text-muted-foreground">
                            {row.file_size ? fileSize(row.file_size) : ''} ·{' '}
                            {row.download_count} {t('downloads')}
                        </div>
                    </div>
                ) : (
                    '—'
                ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col items-start gap-1">
                    <StatusBadge status={row.status} />
                    {row.requires_acknowledgment && (
                        <span className="text-xs text-muted-foreground">
                            {t('Acknowledgment required')}
                        </span>
                    )}
                </div>
            ),
        },
    ];

    const field = (
        name: keyof typeof blank,
        label: string,
        input: ReactNode,
        wide = false,
    ) => (
        <div className={wide ? 'grid gap-2 sm:col-span-2' : 'grid gap-2'}>
            <Label htmlFor={`document-${name}`}>{t(label)}</Label>
            {input}
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <>
            <Head title={t('HR Documents')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="HR Documents"
                    description="Store and manage official HR documents for employees."
                    action={
                        can('create-hr-documents') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Upload Document')}
                            </Button>
                        )
                    }
                />

                <StatCards
                    stats={[
                        {
                            label: 'Total Documents',
                            value: stats.total,
                            note: 'All time',
                            icon: Package,
                            tone: 'bg-muted text-muted-foreground',
                        },
                        {
                            label: 'Published',
                            value: stats.published,
                            note: share(stats.published),
                            icon: Globe,
                            tone: 'bg-blue-50 text-blue-600',
                        },
                        {
                            label: 'Expiring Soon',
                            value: stats.expiring_soon,
                            note: 'Within 30 days',
                            icon: Clock,
                            tone: 'bg-amber-50 text-amber-600',
                        },
                        {
                            label: 'Needs Acknowledgment',
                            value: stats.needs_acknowledgment,
                            note: share(stats.needs_acknowledgment),
                            icon: Bell,
                            tone: 'bg-red-50 text-red-600',
                        },
                    ]}
                />

                <DataTable
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    data={hrDocuments}
                    columns={columns}
                    cardsOnly
                    renderCard={(doc) => (
                        <div className="flex h-full flex-col rounded-xl border bg-card p-4 shadow-sm">
                            <div className="flex items-start justify-between gap-2">
                                <h3 className="font-semibold">{doc.title}</h3>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 shrink-0"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(doc)}
                                >
                                    <Eye />
                                </Button>
                            </div>
                            <div className="mt-1 text-xs text-muted-foreground">
                                {t('Last Update')}:
                                <span className="mt-0.5 flex items-center gap-1">
                                    <CalendarDays className="size-3.5" />
                                    {date(doc.effective_date ?? doc.updated_at)}
                                </span>
                            </div>
                            <div className="mt-3 flex items-center justify-between gap-2">
                                {doc.category && (
                                    <span
                                        className="rounded-md border px-2 py-0.5 text-xs font-medium"
                                        style={{
                                            color: doc.category.color,
                                            borderColor: `${doc.category.color}55`,
                                            backgroundColor: `${doc.category.color}14`,
                                        }}
                                    >
                                        {doc.category.name}
                                    </span>
                                )}
                                <span className="flex items-center gap-1 text-xs">
                                    v.{doc.version}
                                    {doc.requires_acknowledgment && (
                                        <Bell
                                            className="size-3.5 text-red-500"
                                            aria-label={t(
                                                'Needs acknowledgment',
                                            )}
                                        />
                                    )}
                                </span>
                            </div>
                            <div className="mt-3">
                                <StatusBadge status={doc.status} />
                            </div>
                            {doc.expiry_date && (
                                <div className="mt-2 text-xs text-red-600">
                                    {t('Expires')}: {date(doc.expiry_date)}
                                </div>
                            )}
                            <div className="mt-auto flex items-center justify-between gap-2 border-t pt-3">
                                {doc.uploader ? (
                                    <PersonCell
                                        name={doc.uploader.name}
                                        detail={doc.uploader.email}
                                        src={doc.uploader.avatar}
                                    />
                                ) : (
                                    <span />
                                )}
                                <div className="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                                    <Download className="size-3.5" />
                                    {doc.download_count}
                                    <ActionMenu
                                        trigger={EllipsisVertical}
                                        items={[
                                            {
                                                label: 'Edit',
                                                icon: SquarePen,
                                                onSelect: () => openForm(doc),
                                                hidden: !can(
                                                    'edit-hr-documents',
                                                ),
                                            },
                                            {
                                                label: 'Update Status',
                                                icon: RefreshCw,
                                                onSelect: () => {
                                                    statusForm.clearErrors();
                                                    statusForm.setData(
                                                        'status',
                                                        doc.status,
                                                    );
                                                    setChanging(doc);
                                                },
                                                hidden: !can(
                                                    'edit-hr-documents',
                                                ),
                                            },
                                            {
                                                label: 'Delete',
                                                icon: Trash2,
                                                destructive: true,
                                                onSelect: () =>
                                                    setDeleting(doc),
                                                hidden: !can(
                                                    'delete-hr-documents',
                                                ),
                                            },
                                        ]}
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                    filters={filters}
                    url={url}
                    toolbar={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="category_id"
                            label="All Categories"
                            options={categories}
                        />
                    }
                    actions={(document) => (
                        <>
                            {document.file_name && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Download')}
                                    asChild
                                >
                                    <a
                                        href={
                                            hrDocumentRoutes.download(
                                                document.id,
                                            ).url
                                        }
                                    >
                                        <Download />
                                    </a>
                                </Button>
                            )}
                            {can('edit-hr-documents') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(document)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-hr-documents') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(document)}
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
                title={editing ? 'Edit Document' : 'Upload Document'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {field(
                        'title',
                        'Title',
                        <Input
                            id="document-title"
                            required
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />,
                        true,
                    )}
                    {field(
                        'category_id',
                        'Category',
                        <SelectField
                            id="document-category_id"
                            required
                            value={form.data.category_id}
                            onChange={(e) =>
                                form.setData('category_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select category')}</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </SelectField>,
                    )}
                    {field(
                        'version',
                        'Version',
                        <Input
                            id="document-version"
                            required
                            value={form.data.version}
                            onChange={(e) =>
                                form.setData('version', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'effective_date',
                        'Effective Date',
                        <Input
                            id="document-effective_date"
                            type="date"
                            value={form.data.effective_date}
                            onChange={(e) =>
                                form.setData('effective_date', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'expiry_date',
                        'Expiry Date',
                        <Input
                            id="document-expiry_date"
                            type="date"
                            value={form.data.expiry_date}
                            onChange={(e) =>
                                form.setData('expiry_date', e.target.value)
                            }
                        />,
                    )}
                    {field(
                        'status',
                        'Status',
                        <SelectField
                            id="document-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {STATUSES.map((status) => (
                                <option key={status} value={status}>
                                    {t(pretty(status))}
                                </option>
                            ))}
                        </SelectField>,
                    )}
                    {field(
                        'file',
                        editing ? 'Replace File' : 'File',
                        <>
                            <Input
                                id="document-file"
                                type="file"
                                required={!editing}
                                accept={uploadTypes
                                    .map((type) => `.${type}`)
                                    .join(',')}
                                onChange={(e) =>
                                    form.setData(
                                        'file',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {uploadTypes.join(', ').toUpperCase()} ·{' '}
                                {t('Max')} {uploadMaxKb / 1024} MB
                                {editing?.file_name &&
                                    ` · ${t('Current')}: ${editing.file_name}`}
                            </p>
                        </>,
                    )}
                    {field(
                        'description',
                        'Description',
                        <textarea
                            id="document-description"
                            rows={3}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />,
                        true,
                    )}
                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Switch
                            id="document-requires_acknowledgment"
                            checked={form.data.requires_acknowledgment}
                            onCheckedChange={(checked) =>
                                form.setData('requires_acknowledgment', checked)
                            }
                        />
                        <Label htmlFor="document-requires_acknowledgment">
                            {t('Requires acknowledgment')}
                        </Label>
                    </div>
                    {form.progress && (
                        <progress
                            className="w-full sm:col-span-2"
                            value={form.progress.percentage}
                            max="100"
                        />
                    )}
                </div>
            </FormDialog>

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Document Details"
                wide
                fields={
                    viewing
                        ? [
                              ['Title', viewing.title, true],
                              ['Category', viewing.category?.name],
                              [
                                  'Status',
                                  <StatusBadge
                                      key="status"
                                      status={viewing.status}
                                  />,
                              ],
                              [
                                  'Effective Date',
                                  viewing.effective_date
                                      ? date(viewing.effective_date)
                                      : null,
                              ],
                              [
                                  'Expiry Date',
                                  viewing.expiry_date
                                      ? date(viewing.expiry_date)
                                      : null,
                              ],
                              [
                                  'Uploaded By',
                                  viewing.uploader
                                      ? `${viewing.uploader.name} · ${date(viewing.created_at)}`
                                      : null,
                              ],
                              ['Version', `v${viewing.version}`],
                              [
                                  'File Name',
                                  viewing.file_name && (
                                      <a
                                          key="file"
                                          href={
                                              hrDocumentRoutes.download(
                                                  viewing.id,
                                              ).url
                                          }
                                          className="inline-flex items-center gap-1.5 text-blue-600 hover:underline"
                                      >
                                          <Download className="size-4" />
                                          {viewing.file_name}
                                      </a>
                                  ),
                              ],
                              ['Downloads', viewing.download_count],
                              ['Description', viewing.description, true],
                          ]
                        : []
                }
            />

            <FormDialog
                open={changing !== null}
                onOpenChange={(open) => !open && setChanging(null)}
                title="Update Document Status"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (changing) {
                        statusForm.submit(
                            hrDocumentRoutes.changeStatus(changing.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setChanging(null),
                            },
                        );
                    }
                }}
                processing={statusForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="document-status-change">
                        {t('Status')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="document-status-change"
                        required
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData('status', e.target.value)
                        }
                    >
                        {STATUSES.map((status) => (
                            <option key={status} value={status}>
                                {t(pretty(status))}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={statusForm.errors.status} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This document, its file and its acknowledgments will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(hrDocumentRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

HrDocuments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'HR Documents', href: hrDocumentRoutes.index() },
    ],
};
