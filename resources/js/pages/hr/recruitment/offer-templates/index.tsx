import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileText,
    Lock,
    LockOpen,
    Plus,
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
import { DateCell } from '@/components/table-cells';
import { StatusTabs } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import offerTemplateRoutes from '@/routes/hr/recruitment/offer-templates';
import type { Paginated, TableFilters } from '@/types';

type OfferTemplate = {
    id: number;
    name: string;
    template_content: string;
    variables: string[] | null;
    status: 'active' | 'inactive';
    created_at: string;
};

/** Filled from the offer when it is previewed (see OfferController::show). */
/** Stand-in values for the preview; unknown placeholders show as [name]. */
const SAMPLE_VALUES: Record<string, string> = {
    candidate_name: 'Aina Rahman',
    job_title: 'Software Engineer',
    company_name: 'HRM Sdn Bhd',
    department: 'Information Technology',
    salary: 'RM 6,500',
    bonus: 'RM 5,000',
    benefits: 'Medical cover, EPF and SOCSO contributions, annual leave',
    start_date: '1 November 2026',
    offer_date: '1 October 2026',
    offer_expiry_date: '15 October 2026',
};

const fillSample = (content: string) =>
    content.replace(
        /\{\{\s*(\w+)\s*\}\}/g,
        (_, key: string) => SAMPLE_VALUES[key] ?? `[${key}]`,
    );

/** Saves the template text as a .txt file (no server round trip needed). */
const download = (template: { name: string; template_content: string }) => {
    const url = URL.createObjectURL(
        new Blob([template.template_content], { type: 'text/plain' }),
    );
    const link = Object.assign(document.createElement('a'), {
        href: url,
        download: `${template.name.replace(/[^\w-]+/g, '-')}.txt`,
    });
    link.click();
    URL.revokeObjectURL(url);
};

const PLACEHOLDERS = [
    'candidate_name',
    'job_title',
    'company_name',
    'department',
    'salary',
    'bonus',
    'benefits',
    'start_date',
    'offer_date',
    'offer_expiry_date',
];

const blank = {
    name: '',
    template_content: '',
    status: 'active' as OfferTemplate['status'],
};

export default function OfferTemplates({
    offerTemplates,
    statusCounts,
    filters,
}: {
    offerTemplates: Paginated<OfferTemplate>;
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<OfferTemplate | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<OfferTemplate | null>(null);
    const [previewing, setPreviewing] = useState<OfferTemplate | null>(null);
    const form = useForm(blank);
    const url = offerTemplateRoutes.index();

    const openForm = (template: OfferTemplate | null) => {
        setEditing(template);
        form.clearErrors();
        form.setData(
            template
                ? {
                      name: template.name,
                      template_content: template.template_content,
                      status: template.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing
                ? offerTemplateRoutes.update(editing.id)
                : offerTemplateRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const columns: Column<OfferTemplate>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'template_content',
            label: 'Content Preview',
            render: (row) => (
                <span className="block max-w-xs truncate text-muted-foreground">
                    {row.template_content}
                </span>
            ),
        },
        {
            key: 'variables',
            label: 'Variables',
            className: 'min-w-44',
            render: (row) => (
                <div className="grid justify-items-start gap-1">
                    {(row.variables ?? []).slice(0, 3).map((v) => (
                        <Badge
                            key={v}
                            variant="outline"
                            className="border-blue-200 bg-blue-50 text-blue-700"
                        >
                            {v}
                        </Badge>
                    ))}
                    {(row.variables?.length ?? 0) > 3 && (
                        <span className="text-xs text-muted-foreground">
                            {t('+:count more', {
                                count: (row.variables?.length ?? 0) - 3,
                            })}
                        </span>
                    )}
                </div>
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
            <Head title={t('Offer Templates')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Offer Templates"
                    description="Manage reusable templates for job offer letters."
                    action={
                        can('create-offer-templates') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Template')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={offerTemplates}
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
                    actions={(template) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link
                                    href={offerTemplateRoutes.show(template.id)}
                                >
                                    <Eye />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Preview')}
                                title={t('Preview')}
                                onClick={() => setPreviewing(template)}
                            >
                                <FileText />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Download')}
                                title={t('Download')}
                                onClick={() => download(template)}
                            >
                                <Download />
                            </Button>
                            {can('edit-offer-templates') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(template)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            template.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        title={t(
                                            template.status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate',
                                        )}
                                        onClick={() =>
                                            router.put(
                                                offerTemplateRoutes.toggleStatus(
                                                    template.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {template.status === 'active' ? (
                                            <Lock />
                                        ) : (
                                            <LockOpen />
                                        )}
                                    </Button>
                                </>
                            )}
                            {can('delete-offer-templates') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(template)}
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
                title={editing ? 'Edit Offer Template' : 'Add Offer Template'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="offer-template-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="offer-template-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offer-template-content">
                            {t('Template Content')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="offer-template-content"
                            rows={12}
                            required
                            className="rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={form.data.template_content}
                            onChange={(e) =>
                                form.setData('template_content', e.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('Available placeholders')}:{' '}
                            {PLACEHOLDERS.map((p) => `{{${p}}}`).join(', ')}
                        </p>
                        <InputError message={form.errors.template_content} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offer-template-status">
                            {t('Status')}
                        </Label>
                        <SelectField
                            id="offer-template-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as OfferTemplate['status'],
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
                open={previewing !== null}
                onOpenChange={(open) => !open && setPreviewing(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{previewing?.name}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Preview with sample values; real offers fill in the candidate and job.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    {previewing && (
                        <div className="max-h-[60vh] overflow-y-auto rounded-lg border bg-muted/30 p-6 text-sm leading-relaxed whitespace-pre-line">
                            {fillSample(previewing.template_content)}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This offer template will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(offerTemplateRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

OfferTemplates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: offerTemplateRoutes.index() },
        { title: 'Offer Templates', href: offerTemplateRoutes.index() },
    ],
};
