import { Head } from '@inertiajs/react';
import { Building2, CalendarDays, Fingerprint, Mail } from 'lucide-react';
import { DetailPage, Fields, Summary } from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { UserAvatar } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import biometricRoutes from '@/routes/hr/biometric-attendance';

type Props = {
    employee: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        gender: 'male' | 'female' | null;
        employee_id: string;
        employee_code: string | null;
        department: string | null;
        designation: string | null;
        shift: { name: string; start_time: string; end_time: string } | null;
    };
    date: string;
    punches: string[];
    attendance: {
        status: string;
        clock_in: string | null;
        clock_out: string | null;
        total_hours: number | string | null;
        is_late: boolean;
        is_early_departure: boolean;
        overtime_hours: number | string | null;
    } | null;
};

export default function BiometricAttendanceShow({
    employee,
    date: day,
    punches,
    attendance,
}: Props) {
    const { t } = useTranslation();
    const { date, time } = useFormat();

    return (
        <>
            <Head title={`${employee.name} · ${date(day)}`} />
            <DetailPage
                title={employee.name}
                description="Biometric punches and the attendance they produced for this day."
                back={biometricRoutes.index({ query: { date: day } })}
                summary={
                    <Summary
                        media={
                            <UserAvatar
                                name={employee.name}
                                src={employee.avatar}
                                gender={employee.gender}
                                className="size-24"
                            />
                        }
                        title={employee.name}
                        subtitle={employee.designation}
                        status={attendance?.status}
                        facts={[
                            [CalendarDays, date(day)],
                            [
                                Fingerprint,
                                `${t('Employee Code')}: ${employee.employee_code ?? '-'}`,
                            ],
                            [Mail, employee.email],
                            [Building2, employee.department],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Punches',
                        heading: 'Punch Log',
                        content: (
                            <ol className="grid gap-2">
                                {punches.map((punch, i) => {
                                    // Punches alternate in/out; the first is always in.
                                    const isIn = i % 2 === 0;

                                    return (
                                        <li
                                            key={`${punch}-${i}`}
                                            className="flex items-center gap-3 rounded-lg border p-3"
                                        >
                                            <span
                                                className={`size-2.5 rounded-full ${isIn ? 'bg-emerald-500' : 'bg-red-500'}`}
                                            />
                                            <span className="font-medium tabular-nums">
                                                {time(punch)}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {t(isIn ? 'In' : 'Out')}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ol>
                        ),
                    },
                    {
                        label: 'Attendance',
                        heading: 'Attendance Record',
                        content: attendance ? (
                            <Fields
                                items={[
                                    [
                                        'Status',
                                        <StatusBadge
                                            key="s"
                                            status={attendance.status}
                                        />,
                                    ],
                                    [
                                        'Clock In',
                                        attendance.clock_in &&
                                            time(attendance.clock_in),
                                    ],
                                    [
                                        'Clock Out',
                                        attendance.clock_out &&
                                            time(attendance.clock_out),
                                    ],
                                    ['Total Hours', attendance.total_hours],
                                    [
                                        'Overtime Hours',
                                        attendance.overtime_hours,
                                    ],
                                    [
                                        'Late',
                                        t(attendance.is_late ? 'Yes' : 'No'),
                                    ],
                                    [
                                        'Early Departure',
                                        t(
                                            attendance.is_early_departure
                                                ? 'Yes'
                                                : 'No',
                                        ),
                                    ],
                                    [
                                        'Shift',
                                        employee.shift &&
                                            `${employee.shift.name} (${time(employee.shift.start_time)} - ${time(employee.shift.end_time)})`,
                                    ],
                                ]}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('No attendance record for this day.')}
                            </p>
                        ),
                    },
                ]}
            />
        </>
    );
}

BiometricAttendanceShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: biometricRoutes.index() },
        { title: 'Biometric Attendance', href: biometricRoutes.index() },
    ],
};
