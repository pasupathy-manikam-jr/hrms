import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Filter,
    Plus,
    Search,
    SquarePen,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormDialog } from '@/components/form-dialog';
import { ExportButton, ImportButton } from '@/components/import-export';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import {
    applyFilters,
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
import { PersonCell } from '@/components/user-avatar';
import { formatPhpDate, useFormat } from '@/hooks/use-format';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import timeEntryRoutes from '@/routes/hr/time-entries';
import type { TableFilters } from '@/types';
import { addDays, pad, parseYmd, ymd } from '@/lib/dates';

type EmployeeOption = { id: number; name: string; employee_id: string };
type Status = 'pending' | 'approved' | 'rejected';

type TimeEntry = {
    id: number;
    employee_id: number;
    date: string;
    project: string | null;
    description: string;
    hours: number;
    start_time: string | null;
    end_time: string | null;
    is_billable: boolean;
    status: Status;
    manager_comments: string | null;
};

type Row = {
    employee: {
        id: number;
        employee_id: string;
        name: string;
        avatar: string | null;
        gender: 'male' | 'female' | null;
        designation: string | null;
    };
    days: Record<string, { hours: number; entries: number; status: Status }>;
    total: number;
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const blank = {
    employee_id: '' as number | string,
    date: '',
    project: '',
    description: '',
    hours: '' as number | string,
    start_time: '',
    end_time: '',
    is_billable: false,
};

const hours = (value: number) => `${Number(value.toFixed(2))}h`;

export default function TimeEntries({
    weekStart,
    rows,
    entries,
    employees,
    projects,
    statusCounts,
    workingDays,
    filters,
}: {
    weekStart: string;
    rows: Row[];
    entries: TimeEntry[];
    employees: EmployeeOption[];
    projects: string[];
    statusCounts: Record<string, number>;
    workingDays: number[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const can = useCan();
    const url = timeEntryRoutes.index();
    const isStaff = can('manage-any-time-entries');
    const [editing, setEditing] = useState<TimeEntry | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<TimeEntry | null>(null);
    const [deciding, setDeciding] = useState<{
        entry: TimeEntry;
        decision: 'approve' | 'reject';
    } | null>(null);
    const [openCell, setOpenCell] = useState<{
        row: Row;
        day: string;
    } | null>(null);
    const [showFilters, setShowFilters] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);
    const form = useForm(blank);
    const decisionForm = useForm({ manager_comments: '' });

    // Debounced search, kept in the query string like every list.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(
            () => applyFilters(url, filters, { search }),
            300,
        );

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const monday = parseYmd(weekStart);
    const days = Array.from({ length: 7 }, (_, i) => addDays(monday, i));
    const today = ymd(new Date());
    const goToWeek = (offset: number) =>
        applyFilters(url, filters, {
            week_start: ymd(addDays(monday, offset * 7)),
        });

    const cellEntries = openCell
        ? entries.filter(
              (e) =>
                  e.employee_id === openCell.row.employee.id &&
                  e.date === openCell.day,
          )
        : [];

    const openForm = (
        entry: TimeEntry | null,
        preset?: Partial<typeof blank>,
    ) => {
        setEditing(entry);
        form.clearErrors();
        form.setData(
            entry
                ? {
                      employee_id: entry.employee_id,
                      date: entry.date,
                      project: entry.project ?? '',
                      description: entry.description,
                      hours: entry.hours,
                      start_time: entry.start_time?.slice(0, 5) ?? '',
                      end_time: entry.end_time?.slice(0, 5) ?? '',
                      is_billable: entry.is_billable,
                  }
                : { ...blank, ...preset },
        );
        setFormOpen(true);
    };

    const openDecision = (entry: TimeEntry, decision: 'approve' | 'reject') => {
        decisionForm.reset();
        decisionForm.clearErrors();
        setDeciding({ entry, decision });
    };

    return (
        <>
            <Head title={t('Timesheet')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Timesheet"
                    description="Track and manage time sheets and work hours."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {can('export-time-entry') && (
                                <ExportButton
                                    href={timeEntryRoutes.export({
                                        query: filters,
                                    })}
                                />
                            )}
                            {can('import-time-entry') && (
                                <ImportButton
                                    title="Import Timesheet from CSV/Excel"
                                    action={timeEntryRoutes.import()}
                                    templateHref={timeEntryRoutes.download.template()}
                                    notes={t(
                                        'Use the Employee ID from the Employees list and dates as YYYY-MM-DD. Billable is Yes or No. Imported entries are pending approval. An employee cannot log more than 24 hours on one day.',
                                    )}
                                />
                            )}
                            {can('create-time-entries') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Add Timesheet')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="rounded-xl border bg-card shadow-sm">
                    <div className="flex flex-wrap items-center gap-3 p-3">
                        <div className="relative w-full max-w-xs">
                            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('Search...')}
                                aria-label={t('Search')}
                                className="ps-9"
                            />
                        </div>
                        {employees.length > 1 && (
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="employee_id"
                                label="All Employees"
                                options={employees}
                            />
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            className="ms-auto"
                            aria-expanded={showFilters}
                            onClick={() => setShowFilters((open) => !open)}
                        >
                            <Filter /> {t('Filters')}
                        </Button>
                    </div>
                    {showFilters && (
                        <div className="flex flex-wrap items-center gap-3 border-t p-3">
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="project"
                                label="All Projects"
                                options={projects.map((p) => ({
                                    id: p,
                                    name: p,
                                }))}
                            />
                            <DateRangeFilter url={url} filters={filters} />
                        </div>
                    )}
                    <div className="border-t px-3">
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    </div>
                </div>

                <div className="flex items-center justify-between rounded-xl border bg-card p-3 shadow-sm">
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label={t('Previous week')}
                        onClick={() => goToWeek(-1)}
                    >
                        <ChevronLeft className="rtl:rotate-180" />
                    </Button>
                    <h2 className="font-semibold">
                        {t(formatPhpDate(days[3], 'F'))} {days[3].getFullYear()}
                    </h2>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label={t('Next week')}
                        onClick={() => goToWeek(1)}
                    >
                        <ChevronRight className="rtl:rotate-180" />
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border bg-card shadow-sm">
                    <table className="w-full min-w-[56rem] table-fixed text-sm">
                        <thead className="bg-muted/60">
                            <tr>
                                <th className="w-56 px-4 py-3 text-start font-medium">
                                    {t('Employee')}
                                </th>
                                {days.map((day) => {
                                    const key = ymd(day);
                                    const isToday = key === today;
                                    const offDay = !workingDays.includes(
                                        day.getDay(),
                                    );

                                    return (
                                        <th
                                            key={key}
                                            className={cn(
                                                'border-s px-2 py-2 text-center font-medium',
                                                isToday &&
                                                    'bg-primary/10 text-primary',
                                                offDay &&
                                                    !isToday &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            <div className="text-xs uppercase">
                                                {t(formatPhpDate(day, 'D'))}
                                            </div>
                                            <div className="text-xl font-bold">
                                                {pad(day.getDate())}
                                            </div>
                                            <div className="text-xs font-normal text-muted-foreground">
                                                {t(formatPhpDate(day, 'M'))}
                                            </div>
                                        </th>
                                    );
                                })}
                                <th className="w-24 border-s px-4 py-3 text-start font-medium">
                                    {t('Total')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.map((row) => (
                                <tr key={row.employee.id}>
                                    <td className="px-4 py-3">
                                        <PersonCell
                                            name={row.employee.name}
                                            detail={row.employee.designation}
                                            src={row.employee.avatar}
                                            gender={row.employee.gender}
                                        />
                                    </td>
                                    {days.map((day) => {
                                        const key = ymd(day);
                                        const cell = row.days[key];

                                        return (
                                            <td
                                                key={key}
                                                className={cn(
                                                    'border-s p-1 text-center',
                                                    key === today &&
                                                        'bg-primary/5',
                                                )}
                                            >
                                                {cell ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setOpenCell({
                                                                row,
                                                                day: key,
                                                            })
                                                        }
                                                        className="flex w-full flex-col items-center gap-0.5 rounded-md px-1 py-1.5 hover:bg-muted"
                                                    >
                                                        <span className="font-bold text-blue-600 dark:text-blue-400">
                                                            {hours(cell.hours)}
                                                        </span>
                                                        {cell.entries > 1 && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {t(
                                                                    ':count entries',
                                                                    {
                                                                        count: cell.entries,
                                                                    },
                                                                )}
                                                            </span>
                                                        )}
                                                        <StatusBadge
                                                            status={cell.status}
                                                        />
                                                    </button>
                                                ) : can(
                                                      'create-time-entries',
                                                  ) ? (
                                                    <button
                                                        type="button"
                                                        aria-label={t(
                                                            'Add Timesheet',
                                                        )}
                                                        onClick={() =>
                                                            openForm(null, {
                                                                employee_id:
                                                                    row.employee
                                                                        .id,
                                                                date: key,
                                                            })
                                                        }
                                                        className="w-full rounded-md py-4 text-muted-foreground/50 hover:bg-muted hover:text-muted-foreground"
                                                    >
                                                        —
                                                    </button>
                                                ) : (
                                                    <span className="text-muted-foreground/50">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                        );
                                    })}
                                    <td className="border-s px-4 py-3 font-bold">
                                        {hours(row.total)}
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        {t('No records found')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={openCell !== null}
                onOpenChange={(open) => !open && setOpenCell(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {openCell &&
                                `${openCell.row.employee.name} · ${date(openCell.day)}`}
                        </DialogTitle>
                    </DialogHeader>
                    <ul className="grid gap-2">
                        {cellEntries.map((entry) => {
                            const changeable =
                                isStaff || entry.status !== 'approved';
                            const decidable = entry.status === 'pending';

                            return (
                                <li
                                    key={entry.id}
                                    className="grid gap-1 rounded-lg border p-3 text-sm"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {entry.project ?? t('No project')}
                                            {' · '}
                                            {hours(entry.hours)}
                                            {entry.start_time &&
                                                ` (${time(entry.start_time)}${entry.end_time ? ` – ${time(entry.end_time)}` : ''})`}
                                        </span>
                                        <StatusBadge status={entry.status} />
                                    </div>
                                    <p className="text-muted-foreground">
                                        {entry.description}
                                    </p>
                                    {entry.manager_comments && (
                                        <p className="text-xs text-muted-foreground italic">
                                            {entry.manager_comments}
                                        </p>
                                    )}
                                    <div className="flex justify-end gap-1">
                                        {decidable &&
                                            can('approve-time-entries') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('Approve')}
                                                    onClick={() =>
                                                        openDecision(
                                                            entry,
                                                            'approve',
                                                        )
                                                    }
                                                >
                                                    <Check className="text-emerald-600" />
                                                </Button>
                                            )}
                                        {decidable &&
                                            can('reject-time-entries') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('Reject')}
                                                    onClick={() =>
                                                        openDecision(
                                                            entry,
                                                            'reject',
                                                        )
                                                    }
                                                >
                                                    <X className="text-destructive" />
                                                </Button>
                                            )}
                                        {changeable &&
                                            can('edit-time-entries') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('Edit')}
                                                    onClick={() =>
                                                        openForm(entry)
                                                    }
                                                >
                                                    <SquarePen />
                                                </Button>
                                            )}
                                        {changeable &&
                                            can('delete-time-entries') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('Delete')}
                                                    onClick={() =>
                                                        setDeleting(entry)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </DialogContent>
            </Dialog>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Time Entry' : 'Add Time Entry'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? timeEntryRoutes.update(editing.id)
                            : timeEntryRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => {
                                setFormOpen(false);
                                setOpenCell(null);
                            },
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {isStaff && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="entry-employee">
                                {t('Employee')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="entry-employee"
                                required
                                value={form.data.employee_id}
                                onChange={(e) =>
                                    form.setData('employee_id', e.target.value)
                                }
                            >
                                <option value="">{t('Select Employee')}</option>
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
                        <Label htmlFor="entry-date">
                            {t('Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="entry-date"
                            type="date"
                            required
                            value={form.data.date}
                            onChange={(e) =>
                                form.setData('date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="entry-project">{t('Project')}</Label>
                        <Input
                            id="entry-project"
                            list="entry-projects"
                            value={form.data.project}
                            onChange={(e) =>
                                form.setData('project', e.target.value)
                            }
                        />
                        <datalist id="entry-projects">
                            {projects.map((p) => (
                                <option key={p} value={p} />
                            ))}
                        </datalist>
                        <InputError message={form.errors.project} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="entry-start">{t('Start Time')}</Label>
                        <Input
                            id="entry-start"
                            type="time"
                            value={form.data.start_time}
                            onChange={(e) =>
                                form.setData('start_time', e.target.value)
                            }
                        />
                        <InputError message={form.errors.start_time} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="entry-end">{t('End Time')}</Label>
                        <Input
                            id="entry-end"
                            type="time"
                            value={form.data.end_time}
                            onChange={(e) =>
                                form.setData('end_time', e.target.value)
                            }
                        />
                        <InputError message={form.errors.end_time} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="entry-hours">
                            {t('Hours')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="entry-hours"
                            type="number"
                            min="0.25"
                            max="24"
                            step="0.25"
                            required
                            value={form.data.hours}
                            onChange={(e) =>
                                form.setData('hours', e.target.value)
                            }
                        />
                        <InputError message={form.errors.hours} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="entry-description">
                            {t('Task Description')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="entry-description"
                            rows={3}
                            required
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Switch
                            id="entry-billable"
                            checked={form.data.is_billable}
                            onCheckedChange={(checked) =>
                                form.setData('is_billable', checked)
                            }
                        />
                        <Label htmlFor="entry-billable">{t('Billable')}</Label>
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={deciding !== null}
                onOpenChange={(open) => !open && setDeciding(null)}
                title={
                    deciding?.decision === 'reject'
                        ? 'Reject Time Entry'
                        : 'Approve Time Entry'
                }
                description={openCell?.row.employee.name}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (deciding) {
                        decisionForm.submit(
                            timeEntryRoutes[deciding.decision](
                                deciding.entry.id,
                            ),
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setDeciding(null);
                                    setOpenCell(null);
                                },
                            },
                        );
                    }
                }}
                processing={decisionForm.processing}
                submitLabel={
                    deciding?.decision === 'reject' ? 'Reject' : 'Approve'
                }
            >
                <div className="grid gap-2">
                    <Label htmlFor="entry-comments">
                        {t('Manager Comments')}
                    </Label>
                    <textarea
                        id="entry-comments"
                        rows={3}
                        className={textareaClass}
                        value={decisionForm.data.manager_comments}
                        onChange={(e) =>
                            decisionForm.setData(
                                'manager_comments',
                                e.target.value,
                            )
                        }
                    />
                    <InputError
                        message={
                            decisionForm.errors.manager_comments ??
                            (decisionForm.errors as Record<string, string>)
                                .status
                        }
                    />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This time entry will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(timeEntryRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setDeleting(null);
                            setOpenCell(null);
                        },
                    })
                }
            />
        </>
    );
}

TimeEntries.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: timeEntryRoutes.index() },
        { title: 'Timesheet', href: timeEntryRoutes.index() },
    ],
};
