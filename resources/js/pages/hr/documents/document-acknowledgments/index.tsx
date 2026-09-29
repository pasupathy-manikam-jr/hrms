import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CheckCheck,
    Eye,
    FileText,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DocumentLink } from '@/components/table-cells';
import { ViewDialog } from '@/components/view-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell } from '@/components/table-cells';
import { PersonCell, UserAvatar } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import acknowledgmentRoutes from '@/routes/hr/documents/document-acknowledgments';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

type Acknowledgment = {
    id: number;
    document_id: number;
    user_id: number;
    status: string;
    due_date: string | null;
    acknowledged_at: string | null;
    acknowledgment_note: string | null;
    ip_address: string | null;
    document: {
        id: number;
        title: string;
        version: string;
        file_name: string | null;
    };
    user: Option & { email: string; avatar: string | null };
    created_at: string;
    assigner: (Option & { email: string; avatar: string | null }) | null;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function DocumentAcknowledgments({
    documentAcknowledgments,
    statusCounts,
    documents,
    users,
    filters,
}: {
    documentAcknowledgments: Paginated<Acknowledgment>;
    statusCounts: Record<string, number>;
    documents: { id: number; title: string }[];
    users: Option[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const { auth } = usePage().props;
    const url = acknowledgmentRoutes.index();
    const [assignOpen, setAssignOpen] = useState(false);
    const [editing, setEditing] = useState<Acknowledgment | null>(null);
    const [acknowledging, setAcknowledging] = useState<Acknowledgment | null>(
        null,
    );
    const [deleting, setDeleting] = useState<Acknowledgment | null>(null);
    const assignForm = useForm({
        document_id: '' as number | string,
        user_id: '' as number | string,
        due_date: '',
    });
    const editForm = useForm({
        document_id: '',
        user_id: '',
        due_date: '',
        status: 'pending',
        acknowledgment_note: '',
    });
    const [viewing, setViewing] = useState<Acknowledgment | null>(null);
    const { date } = useFormat();
    const ackForm = useForm({ acknowledgment_note: '' });

    const columns: Column<Acknowledgment>[] = [
        {
            key: 'user',
            label: 'Employee',
            render: (row) => (
                <div className="flex min-w-0 items-center gap-3">
                    <UserAvatar name={row.user.name} src={row.user.avatar} />
                    <div className="min-w-0">
                        <div className="truncate font-medium">
                            {row.user.name}
                        </div>
                        <div className="flex items-center gap-1 text-xs text-muted-foreground">
                            <FileText className="size-3.5 shrink-0" />
                            <span className="truncate">
                                {row.document.title}
                            </span>
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'acknowledged_at',
            label: 'Acknowledged',
            sortable: true,
            render: (row) => (
                <div title={row.acknowledgment_note ?? undefined}>
                    <DateCell value={row.acknowledged_at} />
                    {row.ip_address && (
                        <div className="text-xs text-muted-foreground">
                            {t('IP')}: {row.ip_address}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'due_date',
            label: 'Due Date',
            sortable: true,
            render: (row) => <DateCell value={row.due_date} />,
        },
        {
            key: 'assigner',
            label: 'Assigned By',
            render: (row) =>
                row.assigner ? (
                    <PersonCell
                        name={row.assigner.name}
                        detail={row.assigner.email}
                        src={row.assigner.avatar}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'document_file',
            label: 'Documents',
            render: (row) => (
                <DocumentLink
                    href={hrDocumentRoutes.download(row.document_id).url}
                    fileName={row.document.file_name}
                />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Acknowledgments')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Acknowledgments"
                    description="Track employee acknowledgments for HR documents."
                    action={
                        can('create-document-acknowledgments') && (
                            <Button
                                onClick={() => {
                                    assignForm.reset();
                                    assignForm.clearErrors();
                                    setAssignOpen(true);
                                }}
                            >
                                <Plus /> {t('Assign Document')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    data={documentAcknowledgments}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        documents.length > 0 && (
                            <>
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="document_id"
                                    label="All Documents"
                                    options={documents.map((d) => ({
                                        id: d.id,
                                        name: d.title,
                                    }))}
                                />
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="user_id"
                                    label="All Users"
                                    options={users}
                                />
                            </>
                        )
                    }
                    actions={(row) => (
                        <>
                            {row.user_id === auth.user.id &&
                                ['pending', 'overdue'].includes(row.status) && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Acknowledge')}
                                        title={t('Acknowledge')}
                                        onClick={() => {
                                            ackForm.reset();
                                            setAcknowledging(row);
                                        }}
                                    >
                                        <CheckCheck />
                                    </Button>
                                )}
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(row)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-document-acknowledgments') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => {
                                        editForm.clearErrors();
                                        editForm.setData({
                                            document_id: String(
                                                row.document_id,
                                            ),
                                            user_id: String(row.user_id),
                                            due_date: row.due_date ?? '',
                                            status:
                                                row.status === 'overdue'
                                                    ? 'pending'
                                                    : row.status,
                                            acknowledgment_note:
                                                row.acknowledgment_note ?? '',
                                        });
                                        setEditing(row);
                                    }}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-document-acknowledgments') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(row)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={assignOpen}
                onOpenChange={setAssignOpen}
                title="Assign Document for Acknowledgment"
                description="Ask one employee, or all employees, to acknowledge a document."
                onSubmit={(e) => {
                    e.preventDefault();
                    assignForm.submit(acknowledgmentRoutes.store(), {
                        preserveScroll: true,
                        onSuccess: () => setAssignOpen(false),
                    });
                }}
                processing={assignForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="ack-document">{t('Document')}</Label>
                    <SelectField
                        id="ack-document"
                        required
                        value={assignForm.data.document_id}
                        onChange={(e) =>
                            assignForm.setData('document_id', e.target.value)
                        }
                    >
                        <option value="">{t('Select document')}</option>
                        {documents.map((document) => (
                            <option key={document.id} value={document.id}>
                                {document.title}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={assignForm.errors.document_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ack-user">{t('Employee')}</Label>
                    <SelectField
                        id="ack-user"
                        value={assignForm.data.user_id}
                        onChange={(e) =>
                            assignForm.setData('user_id', e.target.value)
                        }
                    >
                        <option value="">{t('All Employees')}</option>
                        {users.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={assignForm.errors.user_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ack-due">{t('Due Date')}</Label>
                    <Input
                        id="ack-due"
                        type="date"
                        value={assignForm.data.due_date}
                        onChange={(e) =>
                            assignForm.setData('due_date', e.target.value)
                        }
                    />
                    <InputError message={assignForm.errors.due_date} />
                </div>
            </FormDialog>

            <FormDialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
                title="Edit Document Acknowledgment"
                onSubmit={(e) => {
                    e.preventDefault();

                    if (editing) {
                        editForm.submit(
                            acknowledgmentRoutes.update(editing.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setEditing(null),
                            },
                        );
                    }
                }}
                processing={editForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="ack-edit-document">
                        {t('Document')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="ack-edit-document"
                        required
                        value={editForm.data.document_id}
                        onChange={(e) =>
                            editForm.setData('document_id', e.target.value)
                        }
                    >
                        <option value="">{t('Select Document')}</option>
                        {documents.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.title}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={editForm.errors.document_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ack-edit-user">
                        {t('User')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="ack-edit-user"
                        required
                        value={editForm.data.user_id}
                        onChange={(e) =>
                            editForm.setData('user_id', e.target.value)
                        }
                    >
                        <option value="">{t('Select User')}</option>
                        {users.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={editForm.errors.user_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ack-edit-due">{t('Due Date')}</Label>
                    <Input
                        id="ack-edit-due"
                        type="date"
                        value={editForm.data.due_date}
                        onChange={(e) =>
                            editForm.setData('due_date', e.target.value)
                        }
                    />
                    <InputError message={editForm.errors.due_date} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ack-edit-status">
                        {t('Status')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="ack-edit-status"
                        value={editForm.data.status}
                        onChange={(e) =>
                            editForm.setData('status', e.target.value)
                        }
                    >
                        <option value="pending">{t('Pending')}</option>
                        <option value="acknowledged">
                            {t('Acknowledged')}
                        </option>
                        <option value="exempted">{t('Exempted')}</option>
                    </SelectField>
                    <InputError message={editForm.errors.status} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ack-edit-note">{t('Note')}</Label>
                    <textarea
                        id="ack-edit-note"
                        rows={3}
                        className={textareaClass}
                        value={editForm.data.acknowledgment_note}
                        onChange={(e) =>
                            editForm.setData(
                                'acknowledgment_note',
                                e.target.value,
                            )
                        }
                    />
                    <InputError message={editForm.errors.acknowledgment_note} />
                </div>
            </FormDialog>

            <FormDialog
                open={acknowledging !== null}
                onOpenChange={(open) => !open && setAcknowledging(null)}
                title="Acknowledge Document"
                description="I confirm that I have read and understood this document."
                submitLabel="Acknowledge"
                onSubmit={(e) => {
                    e.preventDefault();

                    if (acknowledging) {
                        ackForm.submit(
                            acknowledgmentRoutes.acknowledge(acknowledging.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setAcknowledging(null),
                            },
                        );
                    }
                }}
                processing={ackForm.processing}
            >
                <div className="font-medium">
                    {acknowledging?.document.title}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ack-note">{t('Comment (optional)')}</Label>
                    <textarea
                        id="ack-note"
                        rows={3}
                        className={textareaClass}
                        value={ackForm.data.acknowledgment_note}
                        onChange={(e) =>
                            ackForm.setData(
                                'acknowledgment_note',
                                e.target.value,
                            )
                        }
                    />
                    <InputError message={ackForm.errors.acknowledgment_note} />
                </div>
            </FormDialog>

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Acknowledgment Details"
                fields={
                    viewing
                        ? [
                              ['Document', viewing.document.title],
                              ['Employee', viewing.user.name],
                              [
                                  'Status',
                                  <StatusBadge
                                      key="status"
                                      status={viewing.status}
                                  />,
                              ],
                              [
                                  'Due Date',
                                  viewing.due_date
                                      ? date(viewing.due_date)
                                      : null,
                              ],
                              [
                                  'Acknowledged At',
                                  viewing.acknowledged_at
                                      ? date(viewing.acknowledged_at)
                                      : null,
                              ],
                              ['Assigned At', date(viewing.created_at)],
                              ['Assigned By', viewing.assigner?.name, true],
                              [
                                  'Acknowledgment Note',
                                  viewing.acknowledgment_note,
                                  true,
                              ],
                          ]
                        : []
                }
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This acknowledgment record will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(acknowledgmentRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

DocumentAcknowledgments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'Acknowledgments', href: acknowledgmentRoutes.index() },
    ],
};
