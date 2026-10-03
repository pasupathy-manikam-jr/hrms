import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/** [label, value, wide?] — wide rows span both columns (descriptions, notes). */
export type ViewField = [string, ReactNode, boolean?];

/**
 * The demo's read-only "details" popup: a title and a two-column list of labelled values.
 * Empty values show as a dash.
 */
export function ViewDialog({
    open,
    onClose,
    title,
    fields,
    wide = false,
}: {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    fields: ViewField[];
    /** Wider dialog for long content. */
    wide?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent
                className={cn(
                    'flex max-h-[90dvh] flex-col',
                    wide && 'sm:max-w-2xl',
                )}
            >
                <DialogHeader>
                    <DialogTitle>
                        {typeof title === 'string' ? t(title) : title}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        {t('Details')}
                    </DialogDescription>
                </DialogHeader>
                <dl className="-mx-6 grid min-h-0 flex-1 grid-cols-1 gap-x-6 gap-y-4 overflow-y-auto px-6 py-1 text-sm sm:grid-cols-2">
                    {fields.map(([label, value, full]) => (
                        <div
                            key={label}
                            className={cn(
                                'grid gap-1',
                                full && 'sm:col-span-2',
                            )}
                        >
                            <dt className="text-xs font-medium text-muted-foreground">
                                {t(label)}
                            </dt>
                            <dd className="font-medium break-words whitespace-pre-line">
                                {value === null ||
                                value === undefined ||
                                value === ''
                                    ? '—'
                                    : value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {t('Close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
