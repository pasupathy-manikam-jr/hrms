import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, Plus, SquarePen, Trash2 } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import minuteRoutes from '@/routes/meetings/meeting-minutes';
import type { Paginated, TableFilters } from '@/types';

const TYPES = ['Note', 'Discussion', 'Decision', 'Action Item'];

type Option = { id: number; name: string };

type Minute = {
    id: number;
    meeting_id: number;
    topic: string;
    content: string;
    type: string;
    recorded_by: number | null;
    recorded_at: string | null;
    created_at: string;
    meeting: { id: number; title: string; meeting_date: string } | null;
    recorder: (Option & { email: string; avatar: string | null }) | null;
};

const blank = { meeting_id: '', topic: '', content: '', type: 'Note' };

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function MeetingMinutes({
    meetingMinutes,
    filters,
    meetings,
    users,
}: {
    meetingMinutes: Paginated<Minute>;
    filters: TableFilters;
    meetings: { id: number; title: string; meeting_date: string }[];
    users: Option[];
}) {
    const { t } = useTranslation();
    const { date, dateTime } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Minute | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Minute | null>(null);
    const form = useForm(blank);
    const meetingOptions = meetings.map((m) => ({
        id: m.id,
        name: `${m.title} (${date(m.meeting_date)})`,
    }));

    const openForm = (minute: Minute | null) => {
        setEditing(minute);
        form.clearErrors();
        form.setData(
            minute
                ? {
                      meeting_id: String(minute.meeting_id),
                      topic: minute.topic,
                      content: minute.content,
                      type: minute.type,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Minute>[] = [
        {
            key: 'topic',
            label: 'Topic',
            sortable: true,
            render: (m) => (
                <div className="max-w-md">
                    <div className="font-medium">{m.topic}</div>
                    <div className="line-clamp-2 text-muted-foreground">
                        {m.content}
                    </div>
                </div>
            ),
        },
        {
            key: 'meeting',
            label: 'Meeting',
            render: (m) =>
                m.meeting && (
                    <div className="grid gap-1">
                        <div className="font-medium">{m.meeting.title}</div>
                        <span className="text-xs text-muted-foreground">
                            <DateCell value={m.meeting.meeting_date} />
                        </span>
                    </div>
                ),
        },
        {
            key: 'type',
            label: 'Type',
            sortable: true,
            render: (m) => <StatusBadge status={m.type} />,
        },
        {
            key: 'recorder',
            label: 'Recorded By',
            render: (m) =>
                m.recorder ? (
                    <PersonCell
                        name={m.recorder.name}
                        detail={m.recorder.email}
                        src={m.recorder.avatar}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'recorded_at',
            label: 'Recorded At',
            sortable: true,
            render: (m) =>
                m.recorded_at ? (
                    <span className="flex items-center gap-2 whitespace-nowrap">
                        <CalendarDays className="size-4 text-muted-foreground" />
                        {dateTime(m.recorded_at)}
                    </span>
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <>
            <Head title={t('Meeting Minutes')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Meeting Minutes"
                    description="Record notes, discussions and decisions from meetings."
                    action={
                        can('create-meeting-minutes') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Minutes')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={meetingMinutes}
                    columns={columns}
                    filters={filters}
                    url={minuteRoutes.index()}
                    toolbar={
                        <>
                            <FilterSelect
                                url={minuteRoutes.index()}
                                filters={filters}
                                name="type"
                                label="All Types"
                                options={TYPES.map((type) => ({
                                    id: type,
                                    name: t(type),
                                }))}
                            />
                            <FilterSelect
                                url={minuteRoutes.index()}
                                filters={filters}
                                name="meeting_id"
                                label="All Meetings"
                                options={meetingOptions}
                            />
                            <FilterSelect
                                url={minuteRoutes.index()}
                                filters={filters}
                                name="recorded_by"
                                label="All Recorders"
                                options={users}
                            />
                        </>
                    }
                    actions={
                        can('edit-meeting-minutes') ||
                        can('delete-meeting-minutes')
                            ? (minute) => (
                                  <>
                                      {can('edit-meeting-minutes') && (
                                          <Button
                                              variant="ghost"
                                              size="icon"
                                              aria-label={t('Edit')}
                                              onClick={() => openForm(minute)}
                                          >
                                              <SquarePen />
                                          </Button>
                                      )}
                                      {can('delete-meeting-minutes') && (
                                          <Button
                                              variant="ghost"
                                              size="icon"
                                              aria-label={t('Delete')}
                                              onClick={() =>
                                                  setDeleting(minute)
                                              }
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
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Minutes' : 'Add Minutes'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? minuteRoutes.update(editing.id)
                            : minuteRoutes.store(),
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
                        <Label htmlFor="minute-meeting">
                            {t('Meeting')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="minute-meeting"
                            required
                            value={form.data.meeting_id}
                            onChange={(e) =>
                                form.setData('meeting_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select Meeting')}</option>
                            {meetingOptions.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.meeting_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="minute-type">{t('Type')}</Label>
                        <SelectField
                            id="minute-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            {TYPES.map((type) => (
                                <option key={type} value={type}>
                                    {t(type)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.type} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="minute-topic">
                            {t('Topic')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="minute-topic"
                            required
                            value={form.data.topic}
                            onChange={(e) =>
                                form.setData('topic', e.target.value)
                            }
                        />
                        <InputError message={form.errors.topic} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="minute-content">
                            {t('Content')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="minute-content"
                            rows={5}
                            required
                            className={textareaClass}
                            value={form.data.content}
                            onChange={(e) =>
                                form.setData('content', e.target.value)
                            }
                        />
                        <InputError message={form.errors.content} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="These minutes will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(minuteRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

MeetingMinutes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Meetings', href: minuteRoutes.index() },
        { title: 'Meeting Minutes', href: minuteRoutes.index() },
    ],
};
