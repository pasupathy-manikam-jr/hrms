import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Create/edit dialog shell: title, fields (children), Cancel / Save.
 */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    onSubmit,
    processing,
    submitLabel = 'Save',
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    onSubmit: (e: FormEvent) => void;
    processing: boolean;
    submitLabel?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <form noValidate onSubmit={onSubmit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>{t(title)}</DialogTitle>
                        {description && (
                            <DialogDescription>
                                {t(description)}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    <div className="grid gap-4">{children}</div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t(submitLabel)}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
