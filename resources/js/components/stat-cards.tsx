import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type Stat = {
    label: string;
    value: number | string;
    note: string;
    icon: LucideIcon;
    /** Classes for the icon tile, e.g. 'bg-emerald-100 text-emerald-600'. */
    tone: string;
};

/** The row of summary cards the demo puts above some lists. */
export function StatCards({
    stats,
    className,
}: {
    stats: Stat[];
    /** Extra grid classes, e.g. 'xl:grid-cols-5' for five cards. */
    className?: string;
}) {
    const { t } = useTranslation();

    return (
        <div
            className={cn(
                'grid gap-4 sm:grid-cols-2 xl:grid-cols-4',
                className,
            )}
        >
            {stats.map(({ label, value, note, icon: Icon, tone }) => (
                <div
                    key={label}
                    className="flex items-start justify-between rounded-xl border bg-card p-5 shadow-sm"
                >
                    <div>
                        <div className="text-sm text-muted-foreground">
                            {t(label)}
                        </div>
                        <div className="text-3xl font-bold">{value}</div>
                        <div className="text-xs text-muted-foreground">
                            {t(note)}
                        </div>
                    </div>
                    <span
                        className={cn(
                            'flex size-11 items-center justify-center rounded-xl',
                            tone,
                        )}
                    >
                        <Icon className="size-5" />
                    </span>
                </div>
            ))}
        </div>
    );
}
