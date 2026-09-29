import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CircleCheckBig,
    Eye,
    Plus,
    RefreshCw,
    SquarePen,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DocumentInput } from '@/components/document-input';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell, DocumentLink } from '@/components/table-cells';
import { PersonCell } from '@/components/user-avatar';
import { StatusBadge } from '@/components/status-badge';
import {
    DateRangeFilter,
    FilterSelect,
    StatusTabs,
} from '@/components/table-filters';
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
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import complaintRoutes from '@/routes/hr/complaints';
import type { Paginated, TableFilters } from '@/types';

type Person = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
};
type EmployeeOption = { id: number; name: string; employee_id: string };
type EmployeeRef = {
    id: number;
    employee_id: string;
    gender: 'male' | 'female' | 'other' | null;
    user: Person;
};

type Complaint = {
    id: number;
    employee_id: number;
    against_employee_id: number | null;
    complaint_type: string;
    subject: string;
    complaint_date: string;
    description: string;
    status: 'submitted' | 'under investigation' | 'resolved' | 'dismissed';
    investigation_notes: string | null;
    resolution_action: string | null;
    resolution_date: string | null;
    is_anonymous: boolean;
    assigned_to: number | null;
    resolution_deadline: string | null;
    follow_up_action: string | null;
    follow_up_date: string | null;
    feedback: string | null;
    file_name: string | null;
    assignee: { id: number; name: string } | null;
    employee: EmployeeRef;
    against_employee: EmployeeRef | null;
};

const RESOLUTIONS = ['resolved', 'dismissed'];
const STATUSES = ['submitted', 'under investigation', 'resolved', 'dismissed'];

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const titleCase = (value: string) =>
    value.replace(/\b\w/g, (c) => c.toUpperCase());

const blank = {
    employee_id: '' as number | string,
    against_employee_id: '' as number | string,
    complaint_type: '',
    subject: '',
    complaint_date: '',
    description: '',
    is_anonymous: false,
    document: null as File | null,
};

export default function Complaints({
    complaints,
    employees,
    complaintTypes,
    assignees,
    statusCounts,
    filters,
}: {
    complaints: Paginated<Complaint>;
    employees: EmployeeOption[];
    complaintTypes: string[];
    assignees: { id: number; name: string }[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const url = complaintRoutes.index();
    const isStaff = can('manage-any-complaints');
    const [editing, setEditing] = useState<Complaint | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [viewing, setViewing] = useState<Complaint | null>(null);
    const [deleting, setDeleting] = useState<Complaint | null>(null);
    const [resolving, setResolving] = useState<Complaint | null>(null);
    const form = useForm(blank);
    const resolveForm = useForm({
        status: '',
        investigation_notes: '',
        resolution_action: '',
        resolution_date: '',
        follow_up_action: '',
        follow_up_date: '',
    });
    const [statusFor, setStatusFor] = useState<Complaint | null>(null);
    const statusForm = useForm({ status: '' });
    const [assigning, setAssigning] = useState<Complaint | null>(null);
    const assignForm = useForm({
        assigned_to: '' as number | string,
        resolution_deadline: '',
    });
    const [followingUp, setFollowingUp] = useState<Complaint | null>(null);
    const followUpForm = useForm({
        follow_up_action: '',
        follow_up_date: '',
        feedback: '',
    });

    const openForm = (complaint: Complaint | null) => {
        setEditing(complaint);
        form.clearErrors();
        form.setData(
            complaint
                ? {
                      employee_id: complaint.employee_id,
                      against_employee_id: complaint.against_employee_id ?? '',
                      complaint_type: complaint.complaint_type,
                      subject: complaint.subject,
                      complaint_date: complaint.complaint_date,
                      description: complaint.description,
                      is_anonymous: complaint.is_anonymous,
                      document: null,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const openResolve = (complaint: Complaint) => {
        resolveForm.clearErrors();
        resolveForm.setData({
            status: complaint.status === 'dismissed' ? 'dismissed' : 'resolved',
            investigation_notes: complaint.investigation_notes ?? '',
            resolution_action: complaint.resolution_action ?? '',
            resolution_date: complaint.resolution_date ?? '',
            follow_up_action: complaint.follow_up_action ?? '',
            follow_up_date: complaint.follow_up_date ?? '',
        });
        setResolving(complaint);
    };

    const person = (employee: EmployeeRef | null) =>
        employee ? (
            <PersonCell
                name={employee.user.name}
                detail={employee.user.email}
                src={employee.user.avatar}
                gender={employee.gender}
            />
        ) : (
            '—'
        );

    const columns: Column<Complaint>[] = [
        {
            key: 'employee',
            label: 'Complainant',
            render: (c) => person(c.employee),
        },
        {
            key: 'against_employee',
            label: 'Against',
            render: (c) => person(c.against_employee),
        },
        {
            key: 'complaint_type',
            label: 'Type',
            render: (c) => (
                <div>
                    <div className="font-medium">{t(c.complaint_type)}</div>
                    <div className="text-xs text-muted-foreground">
                        {c.subject}
                    </div>
                </div>
            ),
        },
        {
            key: 'complaint_date',
            label: 'Date',
            sortable: true,
            render: (c) => <DateCell value={c.complaint_date} />,
        },
        {
            key: 'document',
            label: 'Documents',
            render: (c) => (
                <DocumentLink
                    href={complaintRoutes.document.url(c.id)}
                    fileName={c.file_name}
                />
            ),
        },
    ];

    const closing = ['resolved', 'dismissed'].includes(resolveForm.data.status);

    const detail = (label: string, value: React.ReactNode, wide = false) => (
        <div className={wide ? 'col-span-2' : undefined}>
            <dt className="text-muted-foreground">{t(label)}</dt>
            <dd className="font-medium whitespace-pre-line">{value || '—'}</dd>
        </div>
    );

    return (
        <>
            <Head title={t('Complaints')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Complaints"
                    description="File workplace complaints and track their investigation."
                    action={
                        can('create-complaints') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Complaint')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={complaints}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="complaint_type"
                                label="All Types"
                                options={complaintTypes.map((type) => ({
                                    id: type,
                                    name: t(type),
                                }))}
                            />
                        </>
                    }
                    moreFilters={
                        <>
                            {isStaff && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="employee_id"
                                    label="All Complainants"
                                    options={employees}
                                />
                            )}
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                    actions={(complaint) => {
                        const changeable =
                            isStaff || complaint.status === 'submitted';

                        return (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('View')}
                                    onClick={() => setViewing(complaint)}
                                >
                                    <Eye />
                                </Button>
                                {changeable && can('edit-complaints') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(complaint)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {can('resolve-complaints') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Change Status')}
                                        title={t('Change Status')}
                                        onClick={() => {
                                            statusForm.setData(
                                                'status',
                                                complaint.status,
                                            );
                                            statusForm.clearErrors();
                                            setStatusFor(complaint);
                                        }}
                                    >
                                        <RefreshCw />
                                    </Button>
                                )}
                                {can('assign-complaints') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Assign')}
                                        title={t('Assign')}
                                        onClick={() => {
                                            assignForm.setData({
                                                assigned_to:
                                                    complaint.assigned_to ?? '',
                                                resolution_deadline:
                                                    complaint.resolution_deadline ??
                                                    '',
                                            });
                                            assignForm.clearErrors();
                                            setAssigning(complaint);
                                        }}
                                    >
                                        <UserPlus />
                                    </Button>
                                )}
                                {can('resolve-complaints') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Resolve')}
                                        title={t('Resolve')}
                                        onClick={() => openResolve(complaint)}
                                    >
                                        <CircleCheckBig />
                                    </Button>
                                )}
                                {['resolved', 'dismissed'].includes(
                                    complaint.status,
                                ) &&
                                    can('resolve-complaints') && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Follow-up')}
                                            title={t('Follow-up')}
                                            onClick={() => {
                                                followUpForm.setData({
                                                    follow_up_action:
                                                        complaint.follow_up_action ??
                                                        '',
                                                    follow_up_date:
                                                        complaint.follow_up_date ??
                                                        '',
                                                    feedback:
                                                        complaint.feedback ??
                                                        '',
                                                });
                                                followUpForm.clearErrors();
                                                setFollowingUp(complaint);
                                            }}
                                        >
                                            <CalendarDays />
                                        </Button>
                                    )}
                                {changeable && can('delete-complaints') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(complaint)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </>
                        );
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Complaint' : 'Add Complaint'}
                onSubmit={(e) => {
                    e.preventDefault();
                    // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
                    form.post(
                        editing
                            ? complaintRoutes.update.form(editing.id).action
                            : complaintRoutes.store().url,
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
                submitLabel={editing ? 'Save' : 'Submit'}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {isStaff && (
                        <div className="grid gap-2">
                            <Label htmlFor="complaint-employee">
                                {t('Complainant')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="complaint-employee"
                                required
                                value={form.data.employee_id}
                                onChange={(e) =>
                                    form.setData('employee_id', e.target.value)
                                }
                            >
                                <option value="">
                                    {t('Select Complainant')}
                                </option>
                                {employees.map((employee) => (
                                    <option
                                        key={employee.id}
                                        value={employee.id}
                                    >
                                        {employee.name} ({employee.employee_id})
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={form.errors.employee_id} />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-against">
                            {t('Against')}
                        </Label>
                        <SelectField
                            id="complaint-against"
                            value={form.data.against_employee_id}
                            onChange={(e) =>
                                form.setData(
                                    'against_employee_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('Select Employee')}</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name} ({employee.employee_id})
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.against_employee_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-type">
                            {t('Complaint Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="complaint-type"
                            required
                            value={form.data.complaint_type}
                            onChange={(e) =>
                                form.setData('complaint_type', e.target.value)
                            }
                        >
                            <option value="">
                                {t('Select Complaint Type')}
                            </option>
                            {complaintTypes.map((type) => (
                                <option key={type} value={type}>
                                    {t(type)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.complaint_type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-date">
                            {t('Complaint Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="complaint-date"
                            type="date"
                            required
                            value={form.data.complaint_date}
                            onChange={(e) =>
                                form.setData('complaint_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.complaint_date} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="complaint-subject">
                            {t('Subject')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="complaint-subject"
                            required
                            value={form.data.subject}
                            onChange={(e) =>
                                form.setData('subject', e.target.value)
                            }
                        />
                        <InputError message={form.errors.subject} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="complaint-description">
                            {t('Description')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="complaint-description"
                            rows={4}
                            required
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <DocumentInput
                        id="complaint-document"
                        currentName={editing?.file_name}
                        hasNewFile={form.data.document !== null}
                        error={form.errors.document}
                        onChange={(file) => form.setData('document', file)}
                    />
                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Switch
                            id="complaint-anonymous"
                            checked={form.data.is_anonymous}
                            onCheckedChange={(checked) =>
                                form.setData('is_anonymous', checked)
                            }
                        />
                        <Label htmlFor="complaint-anonymous">
                            {t('Submit Anonymously')}
                        </Label>
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={resolving !== null}
                onOpenChange={(open) => !open && setResolving(null)}
                title="Resolve Complaint"
                description={resolving?.subject}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (resolving) {
                        resolveForm.submit(
                            complaintRoutes.resolve(resolving.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setResolving(null),
                            },
                        );
                    }
                }}
                processing={resolveForm.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-status">
                            {t('Resolution Type')}
                        </Label>
                        <SelectField
                            id="complaint-status"
                            required
                            value={resolveForm.data.status}
                            onChange={(e) =>
                                resolveForm.setData('status', e.target.value)
                            }
                        >
                            {RESOLUTIONS.map((status) => (
                                <option key={status} value={status}>
                                    {t(titleCase(status))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={resolveForm.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-resolution-date">
                            {t('Resolution Date')}
                            {closing && (
                                <span className="text-destructive">*</span>
                            )}
                        </Label>
                        <Input
                            id="complaint-resolution-date"
                            type="date"
                            required={closing}
                            value={resolveForm.data.resolution_date}
                            onChange={(e) =>
                                resolveForm.setData(
                                    'resolution_date',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={resolveForm.errors.resolution_date}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="complaint-notes">
                            {t('Investigation Notes')}
                        </Label>
                        <textarea
                            id="complaint-notes"
                            rows={3}
                            className={textareaClass}
                            value={resolveForm.data.investigation_notes}
                            onChange={(e) =>
                                resolveForm.setData(
                                    'investigation_notes',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={resolveForm.errors.investigation_notes}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="complaint-action">
                            {t('Resolution Action')}
                            {closing && (
                                <span className="text-destructive">*</span>
                            )}
                        </Label>
                        <textarea
                            id="complaint-action"
                            rows={3}
                            required={closing}
                            className={textareaClass}
                            value={resolveForm.data.resolution_action}
                            onChange={(e) =>
                                resolveForm.setData(
                                    'resolution_action',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={resolveForm.errors.resolution_action}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-follow-action">
                            {t('Follow-up Action')}
                        </Label>
                        <Input
                            id="complaint-follow-action"
                            value={resolveForm.data.follow_up_action}
                            onChange={(e) =>
                                resolveForm.setData(
                                    'follow_up_action',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-follow-date">
                            {t('Follow-up Date')}
                        </Label>
                        <Input
                            id="complaint-follow-date"
                            type="date"
                            value={resolveForm.data.follow_up_date}
                            onChange={(e) =>
                                resolveForm.setData(
                                    'follow_up_date',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={statusFor !== null}
                onOpenChange={(open) => !open && setStatusFor(null)}
                title="Change Complaint Status"
                description={statusFor?.subject}
                processing={statusForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (statusFor) {
                        statusForm.submit(
                            complaintRoutes.changeStatus(statusFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setStatusFor(null),
                            },
                        );
                    }
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="complaint-new-status">{t('Status')}</Label>
                    <SelectField
                        id="complaint-new-status"
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData('status', e.target.value)
                        }
                    >
                        {STATUSES.map((status) => (
                            <option key={status} value={status}>
                                {t(titleCase(status))}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={statusForm.errors.status} />
                </div>
            </FormDialog>

            <FormDialog
                open={assigning !== null}
                onOpenChange={(open) => !open && setAssigning(null)}
                title="Assign Complaint"
                description={assigning?.subject}
                processing={assignForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (assigning) {
                        assignForm.submit(
                            complaintRoutes.assign(assigning.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setAssigning(null),
                            },
                        );
                    }
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-assignee">
                            {t('Assign To')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="complaint-assignee"
                            required
                            value={assignForm.data.assigned_to}
                            onChange={(e) =>
                                assignForm.setData(
                                    'assigned_to',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('Select')}</option>
                            {assignees.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={assignForm.errors.assigned_to} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-deadline">
                            {t('Resolution Deadline')}
                        </Label>
                        <Input
                            id="complaint-deadline"
                            type="date"
                            value={assignForm.data.resolution_deadline}
                            onChange={(e) =>
                                assignForm.setData(
                                    'resolution_deadline',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={assignForm.errors.resolution_deadline}
                        />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={followingUp !== null}
                onOpenChange={(open) => !open && setFollowingUp(null)}
                title="Update Follow-up Information"
                description={followingUp?.subject}
                processing={followUpForm.processing}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (followingUp) {
                        followUpForm.submit(
                            complaintRoutes.followUp(followingUp.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setFollowingUp(null),
                            },
                        );
                    }
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-fu-action">
                            {t('Follow-up Action')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="complaint-fu-action"
                            required
                            value={followUpForm.data.follow_up_action}
                            onChange={(e) =>
                                followUpForm.setData(
                                    'follow_up_action',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={followUpForm.errors.follow_up_action}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="complaint-fu-date">
                            {t('Follow-up Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="complaint-fu-date"
                            type="date"
                            required
                            value={followUpForm.data.follow_up_date}
                            onChange={(e) =>
                                followUpForm.setData(
                                    'follow_up_date',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={followUpForm.errors.follow_up_date}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="complaint-feedback">
                            {t('Feedback')}
                        </Label>
                        <textarea
                            id="complaint-feedback"
                            rows={3}
                            className={textareaClass}
                            value={followUpForm.data.feedback}
                            onChange={(e) =>
                                followUpForm.setData('feedback', e.target.value)
                            }
                        />
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Complaint Details')}</DialogTitle>
                    </DialogHeader>
                    {viewing && (
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            {detail('Complainant', viewing.employee.user.name)}
                            {detail(
                                'Against',
                                viewing.against_employee?.user.name,
                            )}
                            {detail(
                                'Complaint Type',
                                t(viewing.complaint_type),
                            )}
                            {detail('Date', date(viewing.complaint_date))}
                            {detail('Subject', viewing.subject, true)}
                            {detail('Description', viewing.description, true)}
                            {detail(
                                'Status',
                                <StatusBadge status={viewing.status} />,
                            )}
                            {detail('Assigned To', viewing.assignee?.name)}
                            {detail(
                                'Documents',
                                <DocumentLink
                                    href={complaintRoutes.document.url(
                                        viewing.id,
                                    )}
                                    fileName={viewing.file_name}
                                />,
                            )}
                            {detail(
                                'Resolution Date',
                                viewing.resolution_date &&
                                    date(viewing.resolution_date),
                            )}
                            {detail(
                                'Investigation Notes',
                                viewing.investigation_notes,
                                true,
                            )}
                            {detail(
                                'Resolution Action',
                                viewing.resolution_action,
                                true,
                            )}
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This complaint will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(complaintRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Complaints.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employee Lifecycle', href: complaintRoutes.index() },
        { title: 'Complaints', href: complaintRoutes.index() },
    ],
};
