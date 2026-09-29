import { Head, router, useForm } from '@inertiajs/react';
import { FileText, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SideForm, SideFormLayout } from '@/components/side-form';
import { StatusBadge } from '@/components/status-badge';
import { ClampedText } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import branchRoutes from '@/routes/hr/branches';
import documentTypeRoutes from '@/routes/hr/document-types';
import type { Paginated, TableFilters } from '@/types';

type DocumentType = {
    id: number;
    name: string;
    description: string | null;
    is_required: boolean;
    created_at: string;
};

const blank = {
    name: '',
    description: '',
    is_required: false,
};

export default function DocumentTypes({
    documentTypes,
    filters,
}: {
    documentTypes: Paginated<DocumentType>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<DocumentType | null>(null);
    const [deleting, setDeleting] = useState<DocumentType | null>(null);
    const form = useForm(blank);
    const url = documentTypeRoutes.index();
    const showForm = editing
        ? can('edit-document-types')
        : can('create-document-types');

    const edit = (documentType: DocumentType | null) => {
        setEditing(documentType);
        form.clearErrors();
        form.setData(
            documentType
                ? {
                      name: documentType.name,
                      description: documentType.description ?? '',
                      is_required: documentType.is_required,
                  }
                : blank,
        );
    };

    const submit = () =>
        form.submit(
            editing
                ? documentTypeRoutes.update(editing.id)
                : documentTypeRoutes.store(),
            { preserveScroll: true, onSuccess: () => edit(null) },
        );

    const columns: Column<DocumentType>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => (
                <div className="flex items-start gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-300">
                        <FileText className="size-5" />
                    </span>
                    <div>
                        <div className="font-medium">{row.name}</div>
                        <div className="max-w-md">
                            <ClampedText text={row.description} />
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'is_required',
            label: 'Required',
            sortable: true,
            render: (row) => (
                <StatusBadge
                    status={row.is_required ? 'required' : 'optional'}
                />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Document Types')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Document Types"
                    description="Manage document types used for employee records."
                />

                <SideFormLayout
                    form={
                        showForm && (
                            <SideForm
                                title={
                                    editing
                                        ? 'Edit Document Type'
                                        : 'Add New Document Type'
                                }
                                description={
                                    editing
                                        ? 'Update the details of this document type'
                                        : 'Fill in the details to create a new document type'
                                }
                                submitLabel={
                                    editing
                                        ? 'Update Document Type'
                                        : 'Add Document Type'
                                }
                                processing={form.processing}
                                onSubmit={submit}
                                onCancel={
                                    editing ? () => edit(null) : undefined
                                }
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="document-type-name">
                                        {t('Document Type Name')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="document-type-name"
                                        required
                                        placeholder={t(
                                            'e.g., MyKad, Passport, Contract',
                                        )}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="document-type-description">
                                        {t('Description')}
                                    </Label>
                                    <textarea
                                        id="document-type-description"
                                        rows={3}
                                        placeholder={t(
                                            'Brief description of the document type',
                                        )}
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
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
                                <div className="grid gap-2">
                                    <Label htmlFor="document-type-required">
                                        {t('Required')}
                                    </Label>
                                    <SelectField
                                        id="document-type-required"
                                        value={
                                            form.data.is_required ? '1' : '0'
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'is_required',
                                                e.target.value === '1',
                                            )
                                        }
                                    >
                                        <option value="0">
                                            {t('Optional')}
                                        </option>
                                        <option value="1">
                                            {t('Required')}
                                        </option>
                                    </SelectField>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Is this document required for all employees?',
                                        )}
                                    </p>
                                </div>
                            </SideForm>
                        )
                    }
                >
                    <DataTable
                        data={documentTypes}
                        columns={columns}
                        filters={filters}
                        url={url}
                        toolbar={
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="required"
                                label="All"
                                options={[
                                    { id: 'yes', name: t('Required') },
                                    { id: 'no', name: t('Optional') },
                                ]}
                            />
                        }
                        actions={(documentType) => (
                            <>
                                {can('edit-document-types') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => edit(documentType)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {can('delete-document-types') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() =>
                                            setDeleting(documentType)
                                        }
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
                description="This document type will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(documentTypeRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

DocumentTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: branchRoutes.index() },
        { title: 'Document Types', href: documentTypeRoutes.index() },
    ],
};
