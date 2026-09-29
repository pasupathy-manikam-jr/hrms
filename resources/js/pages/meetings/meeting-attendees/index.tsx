import { Head, router, useForm } from '@inertiajs/react';
import { Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { PersonCell } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import attendeeRoutes from '@/routes/meetings/meeting-attendees';
import type { Paginated, TableFilters } from '@/types';

const TYPES = ['Required', 'Optional'];
const RSVP_STATUSES = ['Pending', 'Accepted', 'Declined', 'Tentative'];
const ATTENDANCE_STATUSES = ['Not Attended', 'Present', 'Late'];

type Option = { id: number; name: string };
type MeetingOption = { id: number; title: string; meeting_date: string };

type Attendee = {
    id: number;
    meeting_id: number;
    user_id: number;
    type: string;
    rsvp_status: string;
    attendance_status: string;
    rsvp_date: string | null;
    decline_reason: string | null;
    meeting: (MeetingOption & { start_time: string }) | null;
    user: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
    } | null;
};

const toOptions = (values: string[], t: (s: string) => string) =>
    values.map((v) => ({ id: v, name: t(v) }));

export default function MeetingAttendees({
    meetingAttendees,
    filters,
    meetings,
    users,
}: {
    meetingAttendees: Paginated<Attendee>;
    filters: TableFilters;
    meetings: MeetingOption[];
    users: Option[];
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const manageAny = can('manage-any-meeting-attendees');
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<Attendee | null>(null);
    const [deleting, setDeleting] = useState<Attendee | null>(null);
    const addForm = useForm({
        meeting_id: '',
        user_ids: [] as number[],
        type: 'Required',
    });
    const editForm = useForm({
        type: 'Required',
        rsvp_status: 'Pending',
        attendance_status: 'Not Attended',
        decline_reason: '',
    });
    const meetingOptions = meetings.map((m) => ({
        id: m.id,
        name: `${m.title} (${date(m.meeting_date)})`,
    }));

    const openEdit = (a: Attendee) => {
        setEditing(a);
        editForm.clearErrors();
        editForm.setData({
            type: a.type,
            rsvp_status: a.rsvp_status,
            attendance_status: a.attendance_status,
            decline_reason: a.decline_reason ?? '',
        });
    };

    const toggleUser = (id: number, checked: boolean) =>
        addForm.setData(
            'user_ids',
            checked
                ? [...addForm.data.user_ids, id]
                : addForm.data.user_ids.filter((u) => u !== id),
        );

    const columns: Column<Attendee>[] = [
        {
            key: 'user',
            label: 'Attendee',
            render: (a) =>
                a.user && (
                    <PersonCell
                        name={a.user.name}
                        detail={a.user.email}
                        src={a.user.avatar}
                    />
                ),
        },
        {
            key: 'meeting',
            label: 'Meeting',
            render: (a) =>
                a.meeting && (
                    <div className="grid gap-1">
                        <div className="font-medium">{a.meeting.title}</div>
                        <span className="text-xs text-muted-foreground">
                            <DateCell value={a.meeting.meeting_date} />
                        </span>
                    </div>
                ),
        },
        {
            key: 'type',
            label: 'Type',
            sortable: true,
            render: (a) => <StatusBadge status={a.type} />,
        },
        {
            key: 'rsvp_status',
            label: 'RSVP',
            sortable: true,
            render: (a) => <StatusBadge status={a.rsvp_status} />,
        },
        {
            key: 'attendance_status',
            label: 'Attendance',
            sortable: true,
            render: (a) => <StatusBadge status={a.attendance_status} />,
        },
        {
            key: 'rsvp_date',
            label: 'RSVP Date',
            render: (a) => <DateCell value={a.rsvp_date} />,
        },
        {
            key: 'decline_reason',
            label: 'Decline Reason',
            render: (a) => a.decline_reason ?? '—',
        },
    ];

    return (
        <>
            <Head title={t('Meeting Attendees')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Meeting Attendees"
                    description="Track invitations, RSVPs and attendance for meetings."
                    action={
                        can('create-meeting-attendees') && (
                            <Button
                                onClick={() => {
                                    addForm.reset();
                                    addForm.clearErrors();
                                    setAdding(true);
                                }}
                            >
                                <Plus /> {t('Add Attendees')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={meetingAttendees}
                    columns={columns}
                    filters={filters}
                    url={attendeeRoutes.index()}
                    toolbar={
                        <>
                            <FilterSelect
                                url={attendeeRoutes.index()}
                                filters={filters}
                                name="rsvp_status"
                                label="All RSVP"
                                options={toOptions(RSVP_STATUSES, t)}
                            />
                            <FilterSelect
                                url={attendeeRoutes.index()}
                                filters={filters}
                                name="attendance_status"
                                label="All Attendance"
                                options={toOptions(ATTENDANCE_STATUSES, t)}
                            />
                            <FilterSelect
                                url={attendeeRoutes.index()}
                                filters={filters}
                                name="meeting_id"
                                label="All Meetings"
                                options={meetingOptions}
                            />
                        </>
                    }
                    actions={
                        can('edit-meeting-attendees') ||
                        can('delete-meeting-attendees')
                            ? (a) => (
                                  <>
                                      {can('edit-meeting-attendees') && (
                                          <Button
                                              variant="ghost"
                                              size="icon"
                                              aria-label={t(
                                                  manageAny ? 'Edit' : 'RSVP',
                                              )}
                                              onClick={() => openEdit(a)}
                                          >
                                              <SquarePen />
                                          </Button>
                                      )}
                                      {can('delete-meeting-attendees') && (
                                          <Button
                                              variant="ghost"
                                              size="icon"
                                              aria-label={t('Remove')}
                                              onClick={() => setDeleting(a)}
                                          >
                                              <Trash2 />
                                          </Button>
                                      )}
                                  </>
                              )
                            : undefined
                    }
                />
            </div>

            <FormDialog
                open={adding}
                onOpenChange={setAdding}
                title="Add Attendees"
                onSubmit={(e) => {
                    e.preventDefault();
                    addForm.post(attendeeRoutes.store.url(), {
                        preserveScroll: true,
                        onSuccess: () => setAdding(false),
                    });
                }}
                processing={addForm.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="attendee-meeting">
                            {t('Meeting')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="attendee-meeting"
                            required
                            value={addForm.data.meeting_id}
                            onChange={(e) =>
                                addForm.setData('meeting_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Meeting')}</option>
                            {meetingOptions.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={addForm.errors.meeting_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="attendee-type">{t('Type')}</Label>
                        <SelectField
                            id="attendee-type"
                            value={addForm.data.type}
                            onChange={(e) =>
                                addForm.setData('type', e.target.value)
                            }
                        >
                            {TYPES.map((type) => (
                                <option key={type} value={type}>
                                    {t(type)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={addForm.errors.type} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label>
                            {t('Attendees')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                            {users.map((user) => (
                                <label
                                    key={user.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={addForm.data.user_ids.includes(
                                            user.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleUser(
                                                user.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {user.name}
                                </label>
                            ))}
                        </div>
                        <InputError message={addForm.errors.user_ids} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
                title={manageAny ? 'Edit Attendee' : 'Update RSVP'}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (editing) {
                        editForm.put(attendeeRoutes.update.url(editing.id), {
                            preserveScroll: true,
                            onSuccess: () => setEditing(null),
                        });
                    }
                }}
                processing={editForm.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {manageAny && (
                        <div className="grid gap-2">
                            <Label htmlFor="edit-type">{t('Type')}</Label>
                            <SelectField
                                id="edit-type"
                                value={editForm.data.type}
                                onChange={(e) =>
                                    editForm.setData('type', e.target.value)
                                }
                            >
                                {TYPES.map((type) => (
                                    <option key={type} value={type}>
                                        {t(type)}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={editForm.errors.type} />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="edit-rsvp">{t('RSVP Status')}</Label>
                        <SelectField
                            id="edit-rsvp"
                            value={editForm.data.rsvp_status}
                            onChange={(e) =>
                                editForm.setData('rsvp_status', e.target.value)
                            }
                        >
                            {RSVP_STATUSES.map((s) => (
                                <option key={s} value={s}>
                                    {t(s)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={editForm.errors.rsvp_status} />
                    </div>
                    {manageAny && (
                        <div className="grid gap-2">
                            <Label htmlFor="edit-attendance">
                                {t('Attendance')}
                            </Label>
                            <SelectField
                                id="edit-attendance"
                                value={editForm.data.attendance_status}
                                onChange={(e) =>
                                    editForm.setData(
                                        'attendance_status',
                                        e.target.value,
                                    )
                                }
                            >
                                {ATTENDANCE_STATUSES.map((s) => (
                                    <option key={s} value={s}>
                                        {t(s)}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError
                                message={editForm.errors.attendance_status}
                            />
                        </div>
                    )}
                    {editForm.data.rsvp_status === 'Declined' && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="edit-reason">
                                {t('Decline Reason')}
                            </Label>
                            <Input
                                id="edit-reason"
                                value={editForm.data.decline_reason}
                                onChange={(e) =>
                                    editForm.setData(
                                        'decline_reason',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={editForm.errors.decline_reason}
                            />
                        </div>
                    )}
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This attendee will be removed from the meeting."
                onConfirm={() =>
                    deleting &&
                    router.delete(attendeeRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

MeetingAttendees.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Meetings', href: attendeeRoutes.index() },
        { title: 'Meeting Attendees', href: attendeeRoutes.index() },
    ],
};
