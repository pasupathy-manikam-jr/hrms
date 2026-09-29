import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Mail, RefreshCw, Reply, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { PersonCell } from '@/components/user-avatar';
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
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import contactRoutes from '@/routes/contacts';
import type { Paginated, TableFilters } from '@/types';

type Contact = {
    id: number;
    name: string;
    email: string;
    subject: string;
    message: string;
    status: string;
    created_at: string;
};

const pretty = (status: string) =>
    status.charAt(0).toUpperCase() + status.slice(1);

export default function Contacts({
    contacts,
    filters,
    statuses,
}: {
    contacts: Paginated<Contact>;
    filters: TableFilters;
    statuses: string[];
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [viewing, setViewing] = useState<Contact | null>(null);
    const [deleting, setDeleting] = useState<Contact | null>(null);
    const [statusFor, setStatusFor] = useState<Contact | null>(null);
    const [newStatus, setNewStatus] = useState('');
    const [replying, setReplying] = useState<Contact | null>(null);
    const replyForm = useForm({ subject: '', message: '' });

    const setStatus = (contact: Contact, status: string) =>
        router.put(
            contactRoutes.updateStatus(contact.id),
            { status },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setViewing((v) =>
                        v?.id === contact.id ? { ...v, status } : v,
                    ),
            },
        );

    const columns: Column<Contact>[] = [
        {
            key: 'name',
            label: 'User',
            sortable: true,
            render: (c) => <PersonCell name={c.name} detail={c.email} />,
        },
        {
            key: 'subject',
            label: 'Subject',
            sortable: true,
            render: (c) => (
                <span className="line-clamp-1 max-w-xs">{c.subject}</span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (c) => <StatusBadge status={c.status} />,
        },
        {
            key: 'created_at',
            label: 'Date',
            sortable: true,
            render: (c) => <DateCell value={c.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Contact Inquiries')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Contact Inquiries"
                    description="Messages sent from the landing page contact form."
                />

                <DataTable
                    data={contacts}
                    columns={columns}
                    filters={filters}
                    url={contactRoutes.index()}
                    toolbar={
                        <FilterSelect
                            url={contactRoutes.index()}
                            filters={filters}
                            name="status"
                            label="All Statuses"
                            options={statuses.map((s) => ({
                                id: s,
                                name: t(pretty(s)),
                            }))}
                        />
                    }
                    actions={(contact) => (
                        <>
                            {can('view-contacts') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(contact)}
                                >
                                    <Eye />
                                </Button>
                            )}
                            {can('update-contact-status') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Update Status')}
                                    title={t('Update Status')}
                                    onClick={() => {
                                        setNewStatus(contact.status);
                                        setStatusFor(contact);
                                    }}
                                >
                                    <RefreshCw />
                                </Button>
                            )}
                            {can('send-reply-contacts') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Send Reply')}
                                    title={t('Send Reply')}
                                    onClick={() => {
                                        replyForm.setData({
                                            subject: `Re: ${contact.subject}`,
                                            message: '',
                                        });
                                        replyForm.clearErrors();
                                        setReplying(contact);
                                    }}
                                >
                                    <Reply />
                                </Button>
                            )}
                            {can('delete-contacts') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(contact)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{viewing?.subject}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <div className="grid gap-4 text-sm">
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Name')}
                                    </dt>
                                    <dd className="font-medium">
                                        {viewing.name}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Email')}
                                    </dt>
                                    <dd>
                                        <a
                                            href={`mailto:${viewing.email}`}
                                            className="inline-flex items-center gap-1 font-medium hover:text-primary"
                                        >
                                            <Mail className="size-3.5" />
                                            {viewing.email}
                                        </a>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Received At')}
                                    </dt>
                                    <dd className="font-medium">
                                        {date(viewing.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Status')}
                                    </dt>
                                    <dd>
                                        {can('update-contact-status') ? (
                                            <SelectField
                                                aria-label={t('Status')}
                                                value={viewing.status}
                                                onChange={(e) =>
                                                    setStatus(
                                                        viewing,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {statuses.map((s) => (
                                                    <option key={s} value={s}>
                                                        {t(pretty(s))}
                                                    </option>
                                                ))}
                                            </SelectField>
                                        ) : (
                                            <StatusBadge
                                                status={viewing.status}
                                            />
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            <div>
                                <div className="mb-1 text-muted-foreground">
                                    {t('Message')}
                                </div>
                                <p className="rounded-lg border bg-muted/40 p-3 whitespace-pre-line">
                                    {viewing.message}
                                </p>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <FormDialog
                open={statusFor !== null}
                onOpenChange={(open) => !open && setStatusFor(null)}
                title="Update Contact Status"
                description={statusFor?.name}
                processing={false}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (statusFor) {
                        setStatus(statusFor, newStatus);
                        setStatusFor(null);
                    }
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="contact-new-status">{t('Status')}</Label>
                    <SelectField
                        id="contact-new-status"
                        value={newStatus}
                        onChange={(e) => setNewStatus(e.target.value)}
                    >
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {t(pretty(s))}
                            </option>
                        ))}
                    </SelectField>
                </div>
            </FormDialog>

            <FormDialog
                open={replying !== null}
                onOpenChange={(open) => !open && setReplying(null)}
                title="Send Reply"
                submitLabel="Send"
                processing={replyForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (replying) {
                        replyForm.post(contactRoutes.reply.url(replying.id), {
                            preserveScroll: true,
                            onSuccess: () => setReplying(null),
                        });
                    }
                }}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="contact-reply-email">
                            {t('Email')}
                        </Label>
                        <Input
                            id="contact-reply-email"
                            value={replying?.email ?? ''}
                            disabled
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="contact-reply-subject">
                            {t('Subject')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="contact-reply-subject"
                            required
                            value={replyForm.data.subject}
                            onChange={(e) =>
                                replyForm.setData('subject', e.target.value)
                            }
                        />
                        <InputError message={replyForm.errors.subject} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="contact-reply-message">
                            {t('Message')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="contact-reply-message"
                            rows={6}
                            required
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={replyForm.data.message}
                            onChange={(e) =>
                                replyForm.setData('message', e.target.value)
                            }
                        />
                        <InputError message={replyForm.errors.message} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This inquiry will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(contactRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Contacts.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Contact Inquiries', href: contactRoutes.index() },
    ],
};
