import { Head, Link, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Fingerprint,
    SquarePen,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { applyFilters, FilterSelect } from '@/components/table-filters';
import { PersonCell } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { formatPhpDate, useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import biometricRoutes from '@/routes/hr/biometric-attendance';
import type { Paginated, TableFilters } from '@/types';
import { addDays, pad, ymd } from '@/lib/dates';

type PunchDay = {
    id: number;
    employee_id: number;
    name: string | null;
    avatar: string | null;
    gender: 'male' | 'female' | null;
    designation: string | null;
    employee_code: string | null;
    date: string;
    clock_in: string;
    clock_out: string | null;
    total_entries: number;
    punches: string[];
};

type MappedEmployee = {
    id: number;
    name: string;
    employee_id: string;
    biometric_emp_id: string | null;
    avatar: string | null;
    gender: 'male' | 'female' | null;
    department: string | null;
    designation: string | null;
};

type ImportResult = {
    imported: number;
    days: number;
    skipped_total: number;
    skipped: { line: number; reason: string; value: string }[];
};

/** The demo's timeline: green first punch, dots for the punches between, red last punch, and a count. */
function PunchTimeline({
    punches,
    time,
}: {
    punches: string[];
    time: (value: string) => string;
}) {
    const first = punches[0];
    const last = punches.length > 1 ? punches[punches.length - 1] : null;
    const between = punches.slice(1, -1);

    return (
        <div className="flex items-center gap-2 whitespace-nowrap">
            <span className="size-2 rounded-full bg-emerald-500" />
            <span className="font-medium tabular-nums">{time(first)}</span>
            {last && (
                <>
                    <span className="flex min-w-16 flex-1 items-center gap-1">
                        <span className="h-px flex-1 bg-border" />
                        {between.map((p, i) => (
                            <span
                                key={`${p}-${i}`}
                                title={time(p)}
                                className={`size-1.5 rounded-full ${i % 2 === 0 ? 'bg-red-500' : 'bg-emerald-500'}`}
                            />
                        ))}
                        <span className="h-px flex-1 bg-border" />
                    </span>
                    <span className="size-2 rounded-full bg-red-500" />
                    <span className="font-medium tabular-nums">
                        {time(last)}
                    </span>
                </>
            )}
            <span className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                {punches.length}x
            </span>
        </div>
    );
}

/** Month header plus a Mon–Sun strip; arrows move a week, a day click shows that day. */
function DayPicker({
    url,
    filters,
    selected,
    total,
}: {
    url: ReturnType<typeof biometricRoutes.index>;
    filters: TableFilters;
    selected: Date;
    total: number;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const monday = addDays(selected, -((selected.getDay() + 6) % 7));
    const days = Array.from({ length: 7 }, (_, i) => addDays(monday, i));
    const today = ymd(new Date());
    const go = (day: Date) =>
        applyFilters(url, filters, { date: ymd(day), page: undefined });

    return (
        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div className="flex items-center justify-between gap-2 border-b p-3">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label={t('Previous week')}
                    onClick={() => go(addDays(selected, -7))}
                >
                    <ChevronLeft className="rtl:rotate-180" />
                </Button>
                <div className="flex flex-wrap items-center justify-center gap-2 text-sm">
                    <span className="text-base font-semibold">
                        {t(formatPhpDate(selected, 'F'))}{' '}
                        {selected.getFullYear()}
                    </span>
                    <span className="text-muted-foreground">
                        {t('Showing')}:
                    </span>
                    <span className="font-medium">{date(ymd(selected))}</span>
                    <span className="rounded-md bg-primary/10 px-1.5 py-0.5 text-xs font-medium text-primary">
                        {t(':count records', { count: total })}
                    </span>
                </div>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label={t('Next week')}
                    onClick={() => go(addDays(selected, 7))}
                >
                    <ChevronRight className="rtl:rotate-180" />
                </Button>
            </div>
            <div className="grid grid-cols-7">
                {days.map((day) => {
                    const key = ymd(day);
                    const isSelected = key === ymd(selected);

                    return (
                        <button
                            key={key}
                            type="button"
                            aria-pressed={isSelected}
                            onClick={() => go(day)}
                            className={cn(
                                'flex flex-col items-center gap-1 border-e py-3 last:border-e-0',
                                isSelected
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted',
                            )}
                        >
                            <span className="text-xs font-medium uppercase">
                                {t(formatPhpDate(day, 'D'))}
                            </span>
                            <span
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-full font-semibold',
                                    isSelected && 'bg-white/20',
                                )}
                            >
                                {pad(day.getDate())}
                            </span>
                            {key === today && (
                                <span
                                    className={cn(
                                        'size-1 rounded-full',
                                        isSelected
                                            ? 'bg-primary-foreground'
                                            : 'bg-primary',
                                    )}
                                />
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

export default function BiometricAttendance({
    date: selectedDate,
    punchDays,
    employees,
    filters,
}: {
    date: string;
    punchDays: Paginated<PunchDay>;
    employees: MappedEmployee[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { time } = useFormat();
    const can = useCan();
    const url = biometricRoutes.index();
    const [year, month, day] = selectedDate.split('-').map(Number);
    const selected = new Date(year, month - 1, day);
    const [importOpen, setImportOpen] = useState(false);
    const [result, setResult] = useState<ImportResult | null>(null);
    const [mapping, setMapping] = useState<MappedEmployee | null>(null);
    const importForm = useForm({ file: null as File | null });
    const mappingForm = useForm({ biometric_emp_id: '' });

    const columns: Column<PunchDay>[] = [
        {
            key: 'employee',
            label: 'Employee',
            render: (d) => (
                <PersonCell
                    name={d.name ?? '-'}
                    detail={`${t('Employee Code')}: ${d.employee_code ?? '-'}`}
                    src={d.avatar}
                    gender={d.gender}
                />
            ),
        },
        {
            key: 'designation',
            label: 'Designation',
            render: (d) => d.designation,
        },
        {
            key: 'timeline',
            label: 'Clock In & Out',
            render: (d) => <PunchTimeline punches={d.punches} time={time} />,
        },
    ];

    return (
        <>
            <Head title={t('Biometric Attendance')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Biometric Attendance"
                    description="View and manage biometric attendance logs."
                    action={
                        can('sync-biometric-attendance') && (
                            <Button
                                onClick={() => {
                                    importForm.reset();
                                    importForm.clearErrors();
                                    setImportOpen(true);
                                }}
                            >
                                <Upload /> {t('Import Punches')}
                            </Button>
                        )
                    }
                />

                <DayPicker
                    url={url}
                    filters={filters}
                    selected={selected}
                    total={punchDays.total}
                />

                {result && (
                    <div className="grid gap-2 rounded-xl border p-4 text-sm">
                        <div className="font-medium">
                            {t(
                                'Last import: :imported punches, :days attendance days, :skipped rows skipped.',
                                {
                                    imported: result.imported,
                                    days: result.days,
                                    skipped: result.skipped_total,
                                },
                            )}
                        </div>
                        {result.skipped.length > 0 && (
                            <ul className="max-h-48 list-disc overflow-y-auto ps-5 text-muted-foreground">
                                {result.skipped.map((row) => (
                                    <li key={row.line}>
                                        {t('Line :line', { line: row.line })}:{' '}
                                        {t(row.reason)}
                                        {row.value && (
                                            <span className="ms-1 font-mono text-xs">
                                                ({row.value})
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                <DataTable
                    data={punchDays}
                    columns={columns}
                    filters={filters}
                    url={url}
                    emptyMessage="No punches on this day."
                    toolbar={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="employee_id"
                            label="All Employees"
                            options={employees}
                        />
                    }
                    actions={(d) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link
                                href={biometricRoutes.show([
                                    d.employee_id,
                                    d.date,
                                ])}
                            >
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />

                <div className="grid gap-3">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <Fingerprint className="size-5" />
                        {t('Biometric ID Mapping')}
                    </h2>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-start">
                                <tr>
                                    <th className="p-3 text-start font-medium">
                                        {t('Employee')}
                                    </th>
                                    <th className="p-3 text-start font-medium">
                                        {t('Department')}
                                    </th>
                                    <th className="p-3 text-start font-medium">
                                        {t('Biometric ID')}
                                    </th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {employees.map((employee) => (
                                    <tr key={employee.id} className="border-t">
                                        <td className="p-3">
                                            <PersonCell
                                                name={employee.name}
                                                detail={[
                                                    employee.employee_id,
                                                    employee.designation,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                                src={employee.avatar}
                                                gender={employee.gender}
                                            />
                                        </td>
                                        <td className="p-3">
                                            {employee.department ?? '-'}
                                        </td>
                                        <td className="p-3 font-mono">
                                            {employee.biometric_emp_id ?? (
                                                <span className="text-muted-foreground">
                                                    {t('Not mapped')}
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3 text-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Edit')}
                                                onClick={() => {
                                                    mappingForm.clearErrors();
                                                    mappingForm.setData(
                                                        'biometric_emp_id',
                                                        employee.biometric_emp_id ??
                                                            '',
                                                    );
                                                    setMapping(employee);
                                                }}
                                            >
                                                <SquarePen />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <FormDialog
                open={importOpen}
                onOpenChange={setImportOpen}
                title="Import Punches"
                description="Live sync with a ZKTeco device isn't connected, so export the device's punch log as CSV (biometric_emp_id, timestamp as YYYY-MM-DD HH:MM:SS; header optional). Each employee's first and last punch of a day become that day's attendance."
                onSubmit={(e) => {
                    e.preventDefault();
                    importForm.post(biometricRoutes.import().url, {
                        forceFormData: true,
                        preserveScroll: true,
                        onFlash: (flash) =>
                            setResult(
                                (flash as { biometricImport?: ImportResult })
                                    .biometricImport ?? null,
                            ),
                        onSuccess: () => setImportOpen(false),
                    });
                }}
                processing={importForm.processing}
                submitLabel="Import"
            >
                <div className="grid gap-2">
                    <Label htmlFor="biometric-file">
                        {t('CSV File')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="biometric-file"
                        type="file"
                        accept=".csv,text/csv"
                        required
                        onChange={(e) =>
                            importForm.setData(
                                'file',
                                e.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <InputError message={importForm.errors.file} />
                </div>
            </FormDialog>

            <FormDialog
                open={mapping !== null}
                onOpenChange={(open) => !open && setMapping(null)}
                title="Edit Biometric ID"
                description={mapping?.name}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (mapping) {
                        mappingForm.submit(
                            biometricRoutes.updateMapping(mapping.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setMapping(null),
                            },
                        );
                    }
                }}
                processing={mappingForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="biometric-id">{t('Biometric ID')}</Label>
                    <Input
                        id="biometric-id"
                        maxLength={50}
                        value={mappingForm.data.biometric_emp_id}
                        onChange={(e) =>
                            mappingForm.setData(
                                'biometric_emp_id',
                                e.target.value,
                            )
                        }
                    />
                    <InputError message={mappingForm.errors.biometric_emp_id} />
                </div>
            </FormDialog>
        </>
    );
}

BiometricAttendance.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: biometricRoutes.index() },
        { title: 'Biometric Attendance', href: biometricRoutes.index() },
    ],
};
