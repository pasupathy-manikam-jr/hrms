import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ViewDialog } from '@/components/view-dialog';
import { DateCell } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { PersonCell } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import itemRoutes from '@/routes/meetings/action-items';
import type { Paginated, TableFilters } from '@/types';

const STATUSES = ['Not Started', 'In Progress', 'Completed', 'Overdue'];
const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

const priorityStyles: Record<string, string> = {
    Low: 'border-gray-200 bg-gray-50 text-gray-600',
    Medium: 'border-blue-200 bg-blue-50 text-blue-700',
    High: 'border-orange-200 bg-orange-50 text-orange-700',
    Critical: 'border-red-200 bg-red-50 text-red-700',
};

type Option = { id: number; name: string };

type ActionItem = {
    id: number;
    meeting_id: number;
    title: string;
    description: string | null;
    assigned_to: number | null;
    due_date: string;
    priority: string;
    status: string;
    progress_percentage: number;
    notes: string | null;
    completed_date: string | null;
    meeting: { id: number; title: string; meeting_date: string } | null;
    assignee: (Option & { email: string; avatar: string | null }) | null;
};

const blank = {
    meeting_id: '',
    title: '',
    description: '',
    assigned_to: '',
    due_date: '',
    priority: 'Medium',
    status: 'Not Started',
    progress_percentage: '0',
    notes: '',
    completed_date: '',
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function ActionItems({
    actionItems,
    filters,
    statusCounts,
    meetings,
    users,
}: {
    actionItems: Paginated<ActionItem>;
    filters: TableFilters;
    statusCounts: Record<string, number>;
    meetings: { id: number; title: string; meeting_date: string }[];
    users: Option[];
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<ActionItem | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<ActionItem | null>(null);
    const [viewing, setViewing] = useState<ActionItem | null>(null);
    const [progressing, setProgressing] = useState<ActionItem | null>(null);
    const progressForm = useForm({ progress_percentage: 0, notes: '' });
    const form = useForm(blank);
    const meetingOptions = meetings.map((m) => ({
        id: m.id,
        name: `${m.title} (${date(m.meeting_date)})`,
    }));

    const openForm = (item: ActionItem | null) => {
        setEditing(item);
        form.clearErrors();
        form.setData(
            item
                ? {
                      meeting_id: String(item.meeting_id),
                      title: item.title,
                      description: item.description ?? '',
                      assigned_to: String(item.assigned_to ?? ''),
                      due_date: item.due_date,
                      priority: item.priority,
                      status: item.status,
                      progress_percentage: String(item.progress_percentage),
                      notes: item.notes ?? '',
                      completed_date: item.completed_date ?? '',
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? itemRoutes.update(editing.id) : itemRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const today = new Date(new Date().toDateString()).getTime();
    const dueNote = (i: ActionItem) => {
        if (!i.due_date || i.status === 'Completed') {
            return null;
        }

        const days = Math.round(
            (new Date(`${i.due_date.slice(0, 10)}T00:00:00`).getTime() -
                today) /
                86_400_000,
        );

        return days < 0 ? (
            <div className="text-xs text-red-600">
                {t(':days days overdue', { days: -days })}
            </div>
        ) : (
            <div className="text-xs text-muted-foreground">
                {t(':days days remaining', { days })}
            </div>
        );
    };

    const columns: Column<ActionItem>[] = [
        {
            key: 'title',
            label: 'Action Item',
            sortable: true,
            render: (i) => (
                <div className="min-w-40">
                    <div className="font-medium">{i.title}</div>
                    <div className="text-xs text-muted-foreground">
                        {i.meeting?.title}
                    </div>
                </div>
            ),
        },
        {
            key: 'assignee',
            label: 'Assigned To',
            render: (i) =>
                i.assignee && (
                    <PersonCell
                        name={i.assignee.name}
                        detail={i.assignee.email}
                        src={i.assignee.avatar}
                    />
                ),
        },
        {
            key: 'due_date',
            label: 'Due Date',
            sortable: true,
            render: (i) => (
                <div>
                    <DateCell value={i.due_date} />
                    {dueNote(i)}
                </div>
            ),
        },
        {
            key: 'priority',
            label: 'Priority',
            render: (i) => (
                <Badge variant="outline" className={priorityStyles[i.priority]}>
                    {t(i.priority)}
                </Badge>
            ),
        },
        {
            key: 'progress_percentage',
            label: 'Progress',
            sortable: true,
            render: (i) => (
                <div className="grid w-32 gap-1.5">
                    <div className="flex items-center justify-between gap-2 text-xs">
                        <span className="text-muted-foreground">
                            {i.progress_percentage}%
                        </span>
                        <StatusBadge status={i.status} />
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            className={cn(
                                'h-full',
                                i.progress_percentage === 100
                                    ? 'bg-emerald-500'
                                    : i.status === 'Overdue'
                                      ? 'bg-red-500'
                                      : 'bg-blue-500',
                            )}
                            style={{ width: `${i.progress_percentage}%` }}
                        />
                    </div>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Action Items')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Action Items"
                    description="Track follow-up tasks from meetings."
                    action={
                        can('create-action-items') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Action Item')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={actionItems}
                    columns={columns}
                    filters={filters}
                    url={itemRoutes.index()}
                    tabs={
                        <StatusTabs
                            url={itemRoutes.index()}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    toolbar={
                        <>
                            <FilterSelect
                                url={itemRoutes.index()}
                                filters={filters}
                                name="priority"
                                label="All Priorities"
                                options={PRIORITIES.map((p) => ({
                                    id: p,
                                    name: t(p),
                                }))}
                            />
                            <FilterSelect
                                url={itemRoutes.index()}
                                filters={filters}
                                name="assigned_to"
                                label="All Assignees"
                                options={users}
                            />
                            <FilterSelect
                                url={itemRoutes.index()}
                                filters={filters}
                                name="meeting_id"
                                label="All Meetings"
                                options={meetingOptions}
                            />
                        </>
                    }
                    actions={(item) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('View')}
                                onClick={() => setViewing(item)}
                            >
                                <Eye />
                            </Button>
                            {can('edit-action-items') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(item)}
                                    >
                                        <SquarePen />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Update Progress')}
                                        title={t('Update Progress')}
                                        onClick={() => {
                                            progressForm.clearErrors();
                                            progressForm.setData({
                                                progress_percentage:
                                                    item.progress_percentage,
                                                notes: item.notes ?? '',
                                            });
                                            setProgressing(item);
                                        }}
                                    >
                                        <TrendingUp />
                                    </Button>
                                </>
                            )}
                            {can('delete-action-items') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(item)}
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
                title={editing ? 'Edit Action Item' : 'Add Action Item'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="item-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="item-title"
                            required
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-meeting">
                            {t('Meeting')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="item-meeting"
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
                        <Label htmlFor="item-assignee">
                            {t('Assigned To')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="item-assignee"
                            required
                            value={form.data.assigned_to}
                            onChange={(e) =>
                                form.setData('assigned_to', e.target.value)
                            }
                        >
                            <option value="">{t('Select Assignee')}</option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.assigned_to} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-due">
                            {t('Due Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="item-due"
                            type="date"
                            required
                            value={form.data.due_date}
                            onChange={(e) =>
                                form.setData('due_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.due_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-priority">{t('Priority')}</Label>
                        <SelectField
                            id="item-priority"
                            value={form.data.priority}
                            onChange={(e) =>
                                form.setData('priority', e.target.value)
                            }
                        >
                            {PRIORITIES.map((p) => (
                                <option key={p} value={p}>
                                    {t(p)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.priority} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-status">{t('Status')}</Label>
                        <SelectField
                            id="item-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {STATUSES.map((s) => (
                                <option key={s} value={s}>
                                    {t(s)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="item-progress">
                            {t('Progress (%)')}
                        </Label>
                        <Input
                            id="item-progress"
                            type="number"
                            min={0}
                            max={100}
                            required
                            value={form.data.progress_percentage}
                            onChange={(e) =>
                                form.setData(
                                    'progress_percentage',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.progress_percentage} />
                    </div>
                    {form.data.status === 'Completed' && (
                        <div className="grid gap-2">
                            <Label htmlFor="item-completed">
                                {t('Completed Date')}
                            </Label>
                            <Input
                                id="item-completed"
                                type="date"
                                value={form.data.completed_date}
                                onChange={(e) =>
                                    form.setData(
                                        'completed_date',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.completed_date} />
                        </div>
                    )}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="item-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="item-description"
                            rows={2}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="item-notes">{t('Notes')}</Label>
                        <textarea
                            id="item-notes"
                            rows={2}
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>
            </FormDialog>

            <ViewDialog
                open={viewing !== null}
                onClose={() => setViewing(null)}
                title="Action Item Details"
                fields={
                    viewing
                        ? [
                              [
                                  'Title',
                                  <span key="title">
                                      <span className="block">
                                          {viewing.title}
                                      </span>
                                      <span className="block text-xs text-muted-foreground">
                                          {viewing.meeting?.title}
                                      </span>
                                  </span>,
                              ],
                              ['Assigned To', viewing.assignee?.name],
                              ['Due Date', date(viewing.due_date)],
                              [
                                  'Priority',
                                  <StatusBadge
                                      key="priority"
                                      status={viewing.priority.toLowerCase()}
                                      label={viewing.priority}
                                  />,
                              ],
                              [
                                  'Status',
                                  <StatusBadge
                                      key="status"
                                      status={viewing.status}
                                  />,
                              ],
                              [
                                  'Progress',
                                  <span
                                      key="progress"
                                      className="flex items-center gap-2"
                                  >
                                      <span className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                          <span
                                              className="block h-full rounded-full bg-emerald-500"
                                              style={{
                                                  width: `${viewing.progress_percentage}%`,
                                              }}
                                          />
                                      </span>
                                      <span className="text-xs">
                                          {viewing.progress_percentage}%
                                      </span>
                                  </span>,
                              ],
                              ['Description', viewing.description, true],
                              ['Notes', viewing.notes, true],
                          ]
                        : []
                }
            />

            <FormDialog
                open={progressing !== null}
                onOpenChange={(open) => !open && setProgressing(null)}
                title="Update Progress"
                submitLabel="Update Progress"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (progressing) {
                        progressForm.submit(
                            itemRoutes.progress(progressing.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setProgressing(null),
                            },
                        );
                    }
                }}
                processing={progressForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="item-progress">
                        {t('Progress Percentage')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="item-progress"
                        type="number"
                        min={0}
                        max={100}
                        required
                        value={progressForm.data.progress_percentage}
                        onChange={(e) =>
                            progressForm.setData(
                                'progress_percentage',
                                Number(e.target.value),
                            )
                        }
                    />
                    <InputError
                        message={progressForm.errors.progress_percentage}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="item-progress-notes">
                        {t('Progress Notes')}
                    </Label>
                    <textarea
                        id="item-progress-notes"
                        rows={3}
                        className={textareaClass}
                        value={progressForm.data.notes}
                        onChange={(e) =>
                            progressForm.setData('notes', e.target.value)
                        }
                    />
                    <InputError message={progressForm.errors.notes} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This action item will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(itemRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

ActionItems.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Meetings', href: itemRoutes.index() },
        { title: 'Action Items', href: itemRoutes.index() },
    ],
};
