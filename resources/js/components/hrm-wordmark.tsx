import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * The brand wordmark: Settings → Brand "Title Text" (shared as `name`), first letter in the brand colour.
 */
export default function HrmWordmark({ className }: { className?: string }) {
    const name = (usePage().props.name as string | undefined)?.trim() || 'HRM';

    return (
        <span
            className={cn(
                'text-4xl font-extrabold tracking-tight text-slate-800 dark:text-white',
                className,
            )}
        >
            <span className="text-primary">{name.charAt(0)}</span>
            {name.slice(1)}
        </span>
    );
}

/** The first letter alone, for the collapsed sidebar. */
export function HrmMark({ className }: { className?: string }) {
    const name = (usePage().props.name as string | undefined)?.trim() || 'HRM';

    return <span className={className}>{name.charAt(0)}</span>;
}
