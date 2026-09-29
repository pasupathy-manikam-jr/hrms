import { router } from '@inertiajs/react';
import { LogIn, LogOut } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import attendance from '@/routes/attendance';

export type TodayAttendance = {
    clock_in: string | null;
    clock_out: string | null;
} | null;

export type ClockShift = {
    name: string;
    start_time: string;
    end_time: string;
} | null;

/**
 * Today's clock in / clock out panels and buttons for the signed-in employee
 * (POST attendance/clock-in and attendance/clock-out).
 */
export function AttendanceClockCard({
    todayAttendance,
    shift,
}: {
    todayAttendance: TodayAttendance;
    shift: ClockShift;
}) {
    const { t } = useTranslation();
    const { time } = useFormat();
    const can = useCan();
    const [processing, setProcessing] = useState(false);
    const clockIn = todayAttendance?.clock_in ?? null;
    const clockOut = todayAttendance?.clock_out ?? null;

    const post = (route: typeof attendance.clockIn) =>
        router.post(
            route(),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) =>
                    Object.values(errors).forEach((message) =>
                        toast.error(message),
                    ),
            },
        );

    return (
        <div className="grid gap-4 rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-semibold">{t('Attendance')}</h3>
                {shift && (
                    <span className="text-sm text-muted-foreground">
                        {shift.name}: {time(shift.start_time)} -{' '}
                        {time(shift.end_time)}
                    </span>
                )}
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div className="rounded-lg bg-emerald-50 p-3 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    <div className="text-sm">{t('Clock In')}</div>
                    <div className="text-xl font-bold">
                        {clockIn ? time(clockIn) : '--:--'}
                    </div>
                </div>
                <div className="rounded-lg bg-red-50 p-3 text-red-700 dark:bg-red-950 dark:text-red-300">
                    <div className="text-sm">{t('Clock Out')}</div>
                    <div className="text-xl font-bold">
                        {clockOut ? time(clockOut) : '--:--'}
                    </div>
                </div>
            </div>

            {can('clock-in-out') && (
                <div className="grid grid-cols-2 gap-3">
                    <Button
                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                        disabled={processing || todayAttendance !== null}
                        onClick={() => post(attendance.clockIn)}
                    >
                        <LogIn /> {t('Clock In')}
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={processing || !clockIn || clockOut !== null}
                        onClick={() => post(attendance.clockOut)}
                    >
                        <LogOut /> {t('Clock Out')}
                    </Button>
                </div>
            )}
        </div>
    );
}
