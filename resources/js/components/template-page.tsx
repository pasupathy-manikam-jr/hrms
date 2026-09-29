import { Head, Link, router, useForm, useHttp } from '@inertiajs/react';
import {
    CalendarDays,
    Code,
    Download,
    Eye,
    FileText,
    Lock,
    LockOpen,
    Plus,
    SquarePen,
    Star,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import {
    DetailPage,
    Fields,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ClampedText, DateCell } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import type { Paginated, TableFilters } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

type Option = { id: number; name: string };

export type Template = {
    id: number;
    name: string;
    description: string | null;
    template_content: string;
    is_default: boolean;
    status: string;
    created_at: string;
    category_id?: number | null;
    contract_type_id?: number | null;
    category?: (Option & { color?: string | null }) | null;
    contract_type?: (Option & { color?: string | null }) | null;
};

// Keys filled by App\Support\TemplateRenderer::employeeValues().
const PLACEHOLDERS = [
    'employee_name',
    'employee_email',
    'employee_id',
    'designation',
    'job_title',
    'department',
    'branch',
    'joining_date',
    'employment_type',
    'company_name',
    'date',
    'issue_date',
    'contract_date',
];

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

type Routes = {
    index: () => RouteDefinition<'get'>;
    store: () => RouteDefinition<'post'>;
    update: (id: number) => RouteDefinition<'put'>;
    destroy: (id: number) => RouteDefinition<'delete'>;
    toggleStatus: (id: number) => RouteDefinition<'put'>;
    download: (id: number) => RouteDefinition<'get'>;
    show: (id: number) => RouteDefinition<'get'>;
    preview: (
        id: number,
        options: { query: { employee_id: number | string } },
    ) => RouteDefinition<'get'>;
};

/**
 * Shared list + form + preview for document templates and contract templates:
 * both group templates (by category / contract type) and fill {placeholders} for an employee.
 */
export function TemplatePage({
    title,
    description,
    kind,
    module,
    routes,
    templates,
    group,
    groups,
    statusCounts,
    filters,
}: {
    title: string;
    description: string;
    /** Contracts list "Variables" and "Clauses" where documents list "Placeholders" and "Format", as in the demo. */
    kind: 'document' | 'contract';
    /** Permission suffix, e.g. "document-templates". */
    module: string;
    routes: Routes;
    templates: Paginated<Template>;
    group: {
        key: 'category_id' | 'contract_type_id';
        relation: 'category' | 'contract_type';
        label: string;
        allLabel: string;
    };
    groups: Option[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = routes.index();
    const blank = {
        name: '',
        description: '',
        group_id: '' as number | string,
        template_content: '',
        is_default: false,
        status: 'active',
    };
    const [editing, setEditing] = useState<Template | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Template | null>(null);
    const [previewing, setPreviewing] = useState<Template | null>(null);
    const form = useForm(blank);

    const openForm = (template: Template | null) => {
        setEditing(template);
        form.clearErrors();
        form.setData(
            template
                ? {
                      name: template.name,
                      description: template.description ?? '',
                      group_id: template[group.key] ?? '',
                      template_content: template.template_content,
                      is_default: template.is_default,
                      status: template.status,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Template>[] = [
        {
            key: 'name',
            label: 'Template Name',
            sortable: true,
            render: (row) => {
                const color = row[group.relation]?.color ?? 'var(--primary)';

                return (
                    <div className="flex items-start gap-3">
                        <span
                            className="flex size-10 shrink-0 items-center justify-center rounded-lg text-white"
                            style={{ backgroundColor: color }}
                        >
                            <FileText className="size-5" />
                        </span>
                        <div>
                            <div className="flex items-center gap-1.5 font-medium">
                                {row.name}
                                {row.is_default && (
                                    <Star
                                        className="size-4 fill-amber-400 text-amber-400"
                                        aria-label={t('Default')}
                                    />
                                )}
                            </div>
                            <div className="max-w-xs">
                                <ClampedText text={row.description} />
                            </div>
                        </div>
                    </div>
                );
            },
        },
        {
            key: group.relation,
            label: group.label,
            render: (row) => row[group.relation]?.name ?? '—',
        },
        {
            key: 'placeholders',
            label: kind === 'contract' ? 'Variables' : 'Placeholders',
            render: (row) => (
                <span className="flex items-center gap-1.5 whitespace-nowrap">
                    <Code className="size-4 text-muted-foreground" />
                    {t(
                        kind === 'contract'
                            ? ':count variables'
                            : ':count placeholders',
                        {
                            count: new Set(
                                row.template_content
                                    .match(/\{\{?\s*[a-z0-9_]+/gi)
                                    ?.map((m) =>
                                        m.replace(/[{\s]/g, '').toLowerCase(),
                                    ) ?? [],
                            ).size,
                        },
                    )}
                </span>
            ),
        },
        kind === 'contract'
            ? {
                  key: 'clauses',
                  label: 'Clauses',
                  // Clauses are the upper-case headings ending in a colon ("COMPENSATION:").
                  render: (row) =>
                      t(':count clauses', {
                          count:
                              row.template_content.match(
                                  /^[A-Z][A-Z &/-]+:\s*$/gm,
                              )?.length ?? 0,
                      }),
              }
            : {
                  key: 'format',
                  label: 'Format',
                  render: () => <Badge variant="outline">PDF</Badge>,
              },
        {
            key: 'length',
            label: 'Content Length',
            render: (row) =>
                t(':count characters', { count: row.template_content.length }),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (row) => <DateCell value={row.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t(title)} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={description}
                    action={
                        can(`create-${module}`) && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Template')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={templates}
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
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name={group.key}
                                label={group.allLabel}
                                options={groups}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="type"
                                label="All"
                                options={[
                                    { id: 'default', name: t('Default') },
                                    { id: 'custom', name: t('Custom') },
                                ]}
                            />
                        </>
                    }
                    actions={(template) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                asChild
                            >
                                <Link href={routes.show(template.id)}>
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
                                asChild
                            >
                                <a href={routes.download(template.id).url}>
                                    <Download />
                                </a>
                            </Button>
                            {can(`edit-${module}`) && (
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
                                                routes.toggleStatus(
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
                            {can(`delete-${module}`) && (
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

            <Dialog
                open={previewing !== null}
                onOpenChange={(open) => !open && setPreviewing(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{previewing?.name}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Template content; placeholders are filled in for an employee on the template page.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    {previewing && (
                        <div className="max-h-[60vh] overflow-y-auto rounded-lg border bg-muted/30 p-6 text-sm leading-relaxed whitespace-pre-line">
                            {previewing.template_content}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Template' : 'Add Template'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform(({ group_id, ...data }) => ({
                        ...data,
                        [group.key]: group_id,
                    }));
                    form.submit(
                        editing ? routes.update(editing.id) : routes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="template-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="template-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="template-group">{t(group.label)}</Label>
                        <SelectField
                            id="template-group"
                            value={form.data.group_id}
                            onChange={(e) =>
                                form.setData('group_id', e.target.value)
                            }
                        >
                            <option value="">{t('None')}</option>
                            {groups.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError
                            message={
                                (form.errors as Record<string, string>)[
                                    group.key
                                ]
                            }
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="template-description">
                            {t('Description')}
                        </Label>
                        <Input
                            id="template-description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="template-content">
                            {t('Content')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="template-content"
                            required
                            rows={12}
                            className={`${textareaClass} font-mono`}
                            value={form.data.template_content}
                            onChange={(e) =>
                                form.setData('template_content', e.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Plain text or simple HTML. Placeholders filled in for the chosen employee:',
                            )}{' '}
                            {PLACEHOLDERS.map((p) => `{${p}}`).join(', ')}
                        </p>
                        <InputError message={form.errors.template_content} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="template-status">{t('Status')}</Label>
                        <SelectField
                            id="template-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="flex items-center gap-3 self-end pb-2">
                        <Switch
                            id="template-default"
                            checked={form.data.is_default}
                            onCheckedChange={(checked) =>
                                form.setData('is_default', checked)
                            }
                        />
                        <Label htmlFor="template-default">
                            {t('Default template')}
                        </Label>
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This template will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(routes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

/**
 * Shared detail page for a document or contract template: its content, the placeholders
 * it uses, and a sandboxed preview filled in for a chosen employee.
 */
export function TemplateShow({
    template,
    placeholders,
    employees,
    routes,
    group,
    description,
}: {
    template: Template;
    placeholders: string[];
    employees: (Option & { employee_id: string })[];
    routes: Pick<Routes, 'index' | 'preview'>;
    group: { relation: 'category' | 'contract_type'; label: string };
    description: string;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const http = useHttp();
    const [employeeId, setEmployeeId] = useState('');
    const [preview, setPreview] = useState<string | null>(null);

    const loadPreview = async (id: string) => {
        setEmployeeId(id);
        setPreview(null);

        if (id) {
            const { content } = (await http.submit(
                routes.preview(template.id, { query: { employee_id: id } }),
            )) as { content: string };
            setPreview(content);
        }
    };

    return (
        <>
            <Head title={template.name} />
            <DetailPage
                title={template.name}
                description={description}
                back={routes.index()}
                summary={
                    <Summary
                        media={<SummaryIcon icon={FileText} />}
                        title={template.name}
                        subtitle={template[group.relation]?.name}
                        status={template.status}
                        facts={[
                            [
                                Code,
                                t(':count placeholders', {
                                    count: placeholders.length,
                                }),
                            ],
                            [CalendarDays, date(template.created_at)],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Template Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        ['Name', template.name],
                                        [
                                            group.label,
                                            template[group.relation]?.name,
                                        ],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="s"
                                                status={template.status}
                                            />,
                                        ],
                                        [
                                            'Default template',
                                            template.is_default
                                                ? t('Yes')
                                                : t('No'),
                                        ],
                                        [
                                            'Created',
                                            <DateCell
                                                key="c"
                                                value={template.created_at}
                                            />,
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Description"
                                    value={template.description}
                                />
                                <div>
                                    <div className="mb-2 text-sm text-muted-foreground">
                                        {t('Content')}
                                    </div>
                                    <pre className="max-h-[60dvh] overflow-auto rounded-md border bg-muted/30 p-4 font-mono text-sm whitespace-pre-wrap">
                                        {template.template_content}
                                    </pre>
                                </div>
                            </div>
                        ),
                    },
                    {
                        label: 'Placeholders',
                        content:
                            placeholders.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('This template has no placeholders.')}
                                </p>
                            ) : (
                                <ul className="flex flex-wrap gap-2">
                                    {placeholders.map((name) => (
                                        <li
                                            key={name}
                                            className="rounded-md border bg-muted/40 px-2 py-1 font-mono text-sm"
                                        >
                                            {`{{${name}}}`}
                                            {!PLACEHOLDERS.includes(name) && (
                                                <span className="ms-2 font-sans text-xs text-muted-foreground">
                                                    {t(
                                                        '(not filled automatically)',
                                                    )}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            ),
                    },
                    {
                        label: 'Preview',
                        content: (
                            <div className="grid gap-4">
                                <SelectField
                                    aria-label={t('Employee')}
                                    value={employeeId}
                                    onChange={(e) =>
                                        loadPreview(e.target.value)
                                    }
                                >
                                    <option value="">
                                        {t(
                                            'Select an employee to fill the template',
                                        )}
                                    </option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.name} (
                                            {employee.employee_id})
                                        </option>
                                    ))}
                                </SelectField>
                                {/* Sandboxed: template HTML can't run scripts or reach the app. */}
                                <iframe
                                    title={t('Preview')}
                                    sandbox=""
                                    className="h-[60dvh] w-full rounded-md border bg-white"
                                    srcDoc={`<body style="font-family:system-ui,sans-serif;font-size:14px;white-space:pre-wrap;margin:16px;color:#111">${
                                        preview ??
                                        template.template_content
                                            .replace(/&/g, '&amp;')
                                            .replace(/</g, '&lt;')
                                    }</body>`}
                                />
                            </div>
                        ),
                    },
                ]}
            />
        </>
    );
}
