import { Head, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormDialog } from '@/components/form-dialog';
import { ExportButton, ImportButton } from '@/components/import-export';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { PersonCell } from '@/components/user-avatar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import recordRoutes from '@/routes/hr/attendance-records';
import type { Paginated, TableFilters } from '@/types';

type Status = 'present' | 'absent' | 'half_day' | 'on_leave' | 'holiday';

type Cell = {
    id?: number;
    date: string;
    status: Status | 'day_off' | 'future';
    is_weekend: boolean;
    clock_in?: string | null;
    clock_out?: string | null;
    total_hours?: number;
    is_late?: boolean;
    is_early_departure?: boolean;
    overtime_hours?: number;
    notes?: string | null;
};

type Row = {
    id: number;
    name: string;
    avatar: string | null;
    gender: 'male' | 'female' | null;
    employee_id: string;
    designation: string | null;
    shift: string | null;
    days: Cell[];
    present_days: number;
    total_working_days: number;
};

type Option = { value: string; label: string };

// Cell code, colours and label per status (the demo's legend).
const STATUS: Record<Cell['status'], [string, string, string]> = {
    present: ['P', 'bg-emerald-100 text-emerald-700', 'Present'],
    absent: ['A', 'bg-red-100 text-red-700', 'Absent'],
    half_day: ['HD', 'bg-amber-100 text-amber-700', 'Half Day'],
    on_leave: ['L', 'bg-blue-100 text-blue-700', 'On Leave'],
    holiday: ['H', 'bg-violet-100 text-violet-700', 'Holiday'],
    day_off: ['—', 'bg-muted text-muted-foreground', 'Day Off'],
    future: ['', '', 'Upcoming'],
};

const blank = {
    employee_id: '' as number | '',
    date: '',
    status: 'present' as Status,
    clock_in: '',
    clock_out: '',
    notes: '',
};

export default function AttendanceRecords({
    employeeRows,
    dayHeaders,
    employees,
    monthOptions,
    yearOptions,
    statuses,
    currentMonth,
    currentYear,
    filters,
}: {
    employeeRows: Paginated<Row>;
    dayHeaders: {
        day: number;
        day_name: string;
        is_weekend: boolean;
        is_future: boolean;
    }[];
    employees: { id: number; name: string; employee_id: string }[];
    monthOptions: Option[];
    yearOptions: Option[];
    statuses: Status[];
    currentMonth: number;
    currentYear: number;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Cell | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<number | null>(null);
    const form = useForm(blank);
    const canEdit =
        can('create-attendance-records') || can('edit-attendance-records');

    const visit = (changes: TableFilters) =>
        router.get(
            recordRoutes.index.url(),
            Object.fromEntries(
                Object.entries({
                    month: currentMonth,
                    year: currentYear,
                    ...filters,
                    ...changes,
                }).filter(([, value]) => value !== '' && value != null),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const openForm = (employeeId: number | '', cell: Cell | null) => {
        const record = cell?.id ? cell : null;

        if (
            !can(
                record
                    ? 'edit-attendance-records'
                    : 'create-attendance-records',
            )
        ) {
            return;
        }

        setEditing(record);
        form.clearErrors();
        form.setData({
            employee_id: employeeId,
            date: cell?.date ?? '',
            status: record ? (record.status as Status) : 'present',
            clock_in: record?.clock_in ?? '',
            clock_out: record?.clock_out ?? '',
            notes: record?.notes ?? '',
        });
        setFormOpen(true);
    };

    const tooltip = (cell: Cell) =>
        [
            date(cell.date),
            t(STATUS[cell.status][2]),
            cell.clock_in &&
                `${time(cell.clock_in)} - ${cell.clock_out ? time(cell.clock_out) : '--:--'}`,
            cell.is_late && t('Late'),
            cell.is_early_departure && t('Early Departure'),
            cell.notes,
        ]
            .filter(Boolean)
            .join(' · ');

    return (
        <>
            <Head title={t('Attendance Records')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Attendance Records"
                    description="Monthly attendance for every employee."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {can('export-attendance-record') && (
                                <ExportButton
                                    href={recordRoutes.export({
                                        query: {
                                            ...filters,
                                            month: currentMonth,
                                            year: currentYear,
                                        },
                                    })}
                                />
                            )}
                            {can('import-attendance-record') && (
                                <ImportButton
                                    title="Import Attendance Records from CSV/Excel"
                                    action={recordRoutes.import()}
                                    templateHref={recordRoutes.download.template()}
                                    notes={t(
                                        'Use the Employee ID from the Employees list, dates as YYYY-MM-DD and times as HH:MM. Status is one of present, absent, half_day, on_leave or holiday. Late, hours and overtime are calculated from the employee’s shift.',
                                    )}
                                />
                            )}
                            {can('create-attendance-records') && (
                                <Button
                                    onClick={() =>
                                        openForm(
                                            employees.length === 1
                                                ? employees[0].id
                                                : '',
                                            null,
                                        )
                                    }
                                >
                                    <Plus /> {t('Add Record')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-3 shadow-sm">
                    {employees.length > 1 && (
                        <SelectField
                            aria-label={t('Employee')}
                            className="w-auto"
                            value={filters.employee ?? ''}
                            onChange={(e) =>
                                visit({
                                    employee: e.target.value,
                                    page: undefined,
                                })
                            }
                        >
                            <option value="">{t('All Employees')}</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name}
                                </option>
                            ))}
                        </SelectField>
                    )}
                    <SelectField
                        aria-label={t('Month')}
                        className="w-auto"
                        value={String(currentMonth)}
                        onChange={(e) =>
                            visit({ month: e.target.value, page: undefined })
                        }
                    >
                        {monthOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {t(option.label)}
                            </option>
                        ))}
                    </SelectField>
                    <SelectField
                        aria-label={t('Year')}
                        className="w-auto"
                        value={String(currentYear)}
                        onChange={(e) =>
                            visit({ year: e.target.value, page: undefined })
                        }
                    >
                        {yearOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </SelectField>
                    <div className="ms-auto flex flex-wrap gap-3 text-xs">
                        {(
                            [
                                'present',
                                'absent',
                                'half_day',
                                'on_leave',
                                'holiday',
                                'day_off',
                            ] as const
                        ).map((status) => (
                            <span
                                key={status}
                                className="flex items-center gap-1"
                            >
                                <span
                                    className={cn(
                                        'inline-flex h-5 min-w-5 items-center justify-center rounded px-1 font-semibold',
                                        STATUS[status][1],
                                    )}
                                >
                                    {STATUS[status][0]}
                                </span>
                                {t(STATUS[status][2])}
                            </span>
                        ))}
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead className="bg-muted/60 text-muted-foreground">
                                <tr>
                                    <th className="sticky start-0 z-10 min-w-56 bg-muted px-4 py-3 text-start text-sm font-medium">
                                        {t('Employee')}
                                    </th>
                                    {dayHeaders.map((day) => (
                                        <th
                                            key={day.day}
                                            className={cn(
                                                'min-w-9 px-1 py-2 text-center font-medium',
                                                day.is_weekend && 'bg-muted',
                                            )}
                                        >
                                            <div>{day.day}</div>
                                            <div className="font-normal">
                                                {t(day.day_name)}
                                            </div>
                                        </th>
                                    ))}
                                    <th className="sticky end-0 z-10 bg-muted px-4 py-3 text-center text-sm font-medium">
                                        {t('Present')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {employeeRows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="sticky start-0 z-10 bg-card px-4 py-2 text-sm">
                                            <PersonCell
                                                name={row.name}
                                                detail={[
                                                    row.designation,
                                                    row.shift,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                                src={row.avatar}
                                                gender={row.gender}
                                            />
                                        </td>
                                        {row.days.map((cell) => (
                                            <td
                                                key={cell.date}
                                                className="px-0.5 py-2 text-center"
                                            >
                                                <button
                                                    type="button"
                                                    title={tooltip(cell)}
                                                    aria-label={tooltip(cell)}
                                                    disabled={!canEdit}
                                                    onClick={() =>
                                                        openForm(row.id, cell)
                                                    }
                                                    className={cn(
                                                        'relative inline-flex h-7 w-8 items-center justify-center rounded font-semibold enabled:hover:ring-2 enabled:hover:ring-ring/40',
                                                        STATUS[cell.status][1],
                                                        cell.status ===
                                                            'future' &&
                                                            'border border-dashed',
                                                    )}
                                                >
                                                    {STATUS[cell.status][0]}
                                                    {cell.is_late && (
                                                        <span className="absolute end-0.5 top-0.5 size-1.5 rounded-full bg-orange-500" />
                                                    )}
                                                </button>
                                            </td>
                                        ))}
                                        <td className="sticky end-0 z-10 bg-card px-4 py-2 text-center text-sm font-medium">
                                            {row.present_days}/
                                            {row.total_working_days}
                                        </td>
                                    </tr>
                                ))}
                                {employeeRows.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={dayHeaders.length + 2}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            {t('No records found')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm text-muted-foreground">
                        <span>
                            {t('Showing :from to :to of :total results', {
                                from: employeeRows.from ?? 0,
                                to: employeeRows.to ?? 0,
                                total: employeeRows.total,
                            })}
                        </span>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8"
                                disabled={employeeRows.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: employeeRows.current_page - 1,
                                    })
                                }
                                aria-label={t('Previous')}
                            >
                                <ChevronLeft className="rtl:rotate-180" />
                            </Button>
                            <span className="px-2">
                                {employeeRows.current_page} /{' '}
                                {employeeRows.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8"
                                disabled={
                                    employeeRows.current_page >=
                                    employeeRows.last_page
                                }
                                onClick={() =>
                                    visit({
                                        page: employeeRows.current_page + 1,
                                    })
                                }
                                aria-label={t('Next')}
                            >
                                <ChevronRight className="rtl:rotate-180" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Attendance' : 'Add Attendance'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing?.id
                            ? recordRoutes.update(editing.id)
                            : recordRoutes.store(),
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
                        <Label htmlFor="attendance-employee">
                            {t('Employee')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="attendance-employee"
                            required
                            value={form.data.employee_id}
                            onChange={(e) =>
                                form.setData(
                                    'employee_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">
                                {t('Select :field', {
                                    field: t('Employee'),
                                })}
                            </option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name} ({employee.employee_id})
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.employee_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="attendance-date">
                            {t('Date')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="attendance-date"
                            type="date"
                            required
                            value={form.data.date}
                            onChange={(e) =>
                                form.setData('date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.date} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="attendance-status">{t('Status')}</Label>
                        <SelectField
                            id="attendance-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value as Status)
                            }
                        >
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {t(STATUS[status][2])}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.status} />
                    </div>
                    {(['clock_in', 'clock_out'] as const).map((key) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={`attendance-${key}`}>
                                {t(
                                    key === 'clock_in'
                                        ? 'Clock In'
                                        : 'Clock Out',
                                )}
                            </Label>
                            <Input
                                id={`attendance-${key}`}
                                type="time"
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="attendance-notes">{t('Notes')}</Label>
                        <textarea
                            id="attendance-notes"
                            rows={2}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    {editing?.id && (
                        <p className="text-sm text-muted-foreground sm:col-span-2">
                            {t('Total Hours')}: {editing.total_hours ?? 0} ·{' '}
                            {t('Overtime')}: {editing.overtime_hours ?? 0}
                        </p>
                    )}
                    {editing?.id && can('delete-attendance-records') && (
                        <div className="sm:col-span-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="text-destructive"
                                onClick={() => setDeleting(editing.id ?? null)}
                            >
                                <Trash2 /> {t('Delete Record')}
                            </Button>
                        </div>
                    )}
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This attendance record will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(recordRoutes.destroy(deleting), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setDeleting(null);
                            setFormOpen(false);
                        },
                    })
                }
            />
        </>
    );
}

AttendanceRecords.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: recordRoutes.index() },
        { title: 'Attendance Records', href: recordRoutes.index() },
    ],
};
