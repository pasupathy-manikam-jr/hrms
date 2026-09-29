import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

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
            <DialogContent className={wide ? 'sm:max-w-2xl' : undefined}>
                <DialogHeader>
                    <DialogTitle>
                        {typeof title === 'string' ? t(title) : title}
                    </DialogTitle>
                </DialogHeader>
                <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    {fields.map(([label, value, full]) => (
                        <div key={label} className={full ? 'col-span-2' : ''}>
                            <dt className="text-muted-foreground">
                                {t(label)}
                            </dt>
                            <dd className="font-medium whitespace-pre-line">
                                {value === null ||
                                value === undefined ||
                                value === ''
                                    ? '—'
                                    : value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </DialogContent>
        </Dialog>
    );
}
