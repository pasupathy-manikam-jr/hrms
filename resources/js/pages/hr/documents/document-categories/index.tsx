import { Head, router, useForm } from '@inertiajs/react';
import {
    Award,
    Banknote,
    Briefcase,
    Building2,
    FileText,
    Folder,
    GraduationCap,
    Heart,
    Lock,
    LockOpen,
    Scale,
    Shield,
    SquarePen,
    Trash2,
    TrendingUp,
    TriangleAlert,
    User,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { SideForm, SideFormLayout } from '@/components/side-form';
import { StatusBadge } from '@/components/status-badge';
import { ClampedText } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import categoryRoutes from '@/routes/hr/documents/document-categories';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';
import type { Paginated, TableFilters } from '@/types';

type DocumentCategory = {
    id: number;
    name: string;
    description: string | null;
    color: string;
    icon: string;
    is_mandatory: boolean;
    status: 'active' | 'inactive';
    documents_count: number;
    created_at: string;
};

/** Mirrors DocumentCategory::ICONS. */
const ICONS: Record<string, LucideIcon> = {
    Folder,
    FileText,
    Shield,
    User,
    TrendingUp,
    Award,
    Scale,
    Heart,
    Banknote,
    Briefcase,
    GraduationCap,
    Building2,
};

const blank = {
    name: '',
    description: '',
    color: '#3B82F6',
    icon: 'Folder',
    is_mandatory: false,
    status: 'active' as DocumentCategory['status'],
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function DocumentCategories({
    documentCategories,
    filters,
}: {
    documentCategories: Paginated<DocumentCategory>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = categoryRoutes.index();
    const [editing, setEditing] = useState<DocumentCategory | null>(null);
    const [deleting, setDeleting] = useState<DocumentCategory | null>(null);
    const form = useForm(blank);
    const showForm = editing
        ? can('edit-document-categories')
        : can('create-document-categories');

    const edit = (category: DocumentCategory | null) => {
        setEditing(category);
        form.clearErrors();
        form.setData(
            category
                ? {
                      name: category.name,
                      description: category.description ?? '',
                      color: category.color,
                      icon: category.icon,
                      is_mandatory: category.is_mandatory,
                      status: category.status,
                  }
                : blank,
        );
    };

    return (
        <>
            <Head title={t('Document Categories')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Document Categories"
                    description="Organize HR documents into categories."
                />

                <SideFormLayout
                    form={
                        showForm && (
                            <SideForm
                                title={
                                    editing
                                        ? 'Edit Category'
                                        : 'Add New Category'
                                }
                                description={
                                    editing
                                        ? 'Update the details of this document category'
                                        : 'Fill in the details to create a new document category'
                                }
                                submitLabel={
                                    editing ? 'Update Category' : 'Add Category'
                                }
                                processing={form.processing}
                                onSubmit={() =>
                                    form.submit(
                                        editing
                                            ? categoryRoutes.update(editing.id)
                                            : categoryRoutes.store(),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => edit(null),
                                        },
                                    )
                                }
                                onCancel={
                                    editing ? () => edit(null) : undefined
                                }
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="category-name">
                                        {t('Category Name')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="category-name"
                                        required
                                        placeholder={t(
                                            'e.g., HR Policies, Contracts',
                                        )}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category-description">
                                        {t('Description')}
                                    </Label>
                                    <textarea
                                        id="category-description"
                                        rows={3}
                                        placeholder={t(
                                            'Brief description of the category',
                                        )}
                                        className={textareaClass}
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.description}
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="category-color">
                                            {t('Color')}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <div className="flex gap-2">
                                            <Input
                                                id="category-color"
                                                type="color"
                                                className="w-12 shrink-0 p-1"
                                                value={form.data.color}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'color',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                aria-label={t('Color')}
                                                className="font-mono"
                                                value={form.data.color}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'color',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <InputError
                                            message={form.errors.color}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="category-icon">
                                            {t('Icon')}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <SelectField
                                            id="category-icon"
                                            value={form.data.icon}
                                            onChange={(e) =>
                                                form.setData(
                                                    'icon',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {Object.entries(ICONS).map(
                                                ([name, Icon]) => (
                                                    <option
                                                        key={name}
                                                        value={name}
                                                    >
                                                        <span className="flex items-center gap-2">
                                                            <Icon className="size-4" />
                                                            {name}
                                                        </span>
                                                    </option>
                                                ),
                                            )}
                                        </SelectField>
                                        <InputError
                                            message={form.errors.icon}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category-mandatory">
                                        {t('Mandatory Category')}
                                    </Label>
                                    <SelectField
                                        id="category-mandatory"
                                        value={
                                            form.data.is_mandatory ? '1' : '0'
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'is_mandatory',
                                                e.target.value === '1',
                                            )
                                        }
                                    >
                                        <option value="0">
                                            {t('No, Optional')}
                                        </option>
                                        <option value="1">
                                            {t('Yes, Mandatory')}
                                        </option>
                                    </SelectField>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Documents in mandatory categories require acknowledgment',
                                        )}
                                    </p>
                                    <InputError
                                        message={form.errors.is_mandatory}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category-status">
                                        {t('Status')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="category-status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target
                                                    .value as DocumentCategory['status'],
                                            )
                                        }
                                    >
                                        <option value="active">
                                            {t('Active')}
                                        </option>
                                        <option value="inactive">
                                            {t('Inactive')}
                                        </option>
                                    </SelectField>
                                    <InputError message={form.errors.status} />
                                </div>
                            </SideForm>
                        )
                    }
                >
                    <DataTable
                        data={documentCategories}
                        filters={filters}
                        url={url}
                        columns={[
                            {
                                key: 'name',
                                label: 'Category',
                                sortable: true,
                                render: (row) => {
                                    const Icon = ICONS[row.icon] ?? Folder;

                                    return (
                                        <div className="flex items-start gap-3">
                                            <span
                                                className="flex size-10 shrink-0 items-center justify-center rounded-lg text-white"
                                                style={{
                                                    backgroundColor: row.color,
                                                }}
                                            >
                                                <Icon className="size-5" />
                                            </span>
                                            <div>
                                                <div className="flex items-center gap-1.5 font-medium">
                                                    {row.name}
                                                    {row.is_mandatory && (
                                                        <TriangleAlert
                                                            className="size-4 text-red-500"
                                                            aria-label={t(
                                                                'Mandatory',
                                                            )}
                                                        />
                                                    )}
                                                </div>
                                                <div className="max-w-xs">
                                                    <ClampedText
                                                        text={row.description}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    );
                                },
                            },
                            {
                                key: 'documents_count',
                                label: 'Documents',
                                render: (row) => (
                                    <Badge variant="outline" className="gap-1">
                                        <FileText className="size-3" />
                                        {row.documents_count}
                                    </Badge>
                                ),
                            },
                            {
                                key: 'is_mandatory',
                                label: 'Type',
                                render: (row) =>
                                    row.is_mandatory ? (
                                        <Badge
                                            variant="outline"
                                            className="border-red-200 bg-red-50 text-red-700"
                                        >
                                            <TriangleAlert />
                                            {t('Mandatory')}
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="outline"
                                            className="border-blue-200 bg-blue-50 text-blue-700"
                                        >
                                            {t('Optional')}
                                        </Badge>
                                    ),
                            },
                            {
                                key: 'status',
                                label: 'Status',
                                render: (row) => (
                                    <StatusBadge status={row.status} />
                                ),
                            },
                        ]}
                        toolbar={
                            <>
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="status"
                                    label="All Statuses"
                                    options={[
                                        { id: 'active', name: t('Active') },
                                        { id: 'inactive', name: t('Inactive') },
                                    ]}
                                />
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="is_mandatory"
                                    label="All Types"
                                    options={[
                                        { id: 'yes', name: t('Mandatory') },
                                        { id: 'no', name: t('Optional') },
                                    ]}
                                />
                            </>
                        }
                        actions={(category) => (
                            <>
                                {can('edit-document-categories') && (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edit Category')}
                                            onClick={() => edit(category)}
                                        >
                                            <SquarePen />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t(
                                                category.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                            title={t(
                                                category.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                            onClick={() =>
                                                router.put(
                                                    categoryRoutes.toggleStatus(
                                                        category.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {category.status === 'active' ? (
                                                <Lock />
                                            ) : (
                                                <LockOpen />
                                            )}
                                        </Button>
                                    </>
                                )}
                                {can('delete-document-categories') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete Category')}
                                        onClick={() => setDeleting(category)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </>
                        )}
                    />
                </SideFormLayout>
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This category will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(categoryRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

DocumentCategories.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'Document Categories', href: categoryRoutes.index() },
    ],
};
