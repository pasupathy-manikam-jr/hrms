import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * The demo's "form beside the list" layout (Award Types, Document Types): an
 * add/edit card on the left, the list on the right. Without `form` (no
 * permission) the list takes the full width.
 */
export function SideFormLayout({
    form,
    children,
}: {
    form: ReactNode;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'grid items-start gap-6',
                form && 'lg:grid-cols-[22rem_1fr]',
            )}
        >
            {form}
            <div className="min-w-0">{children}</div>
        </div>
    );
}

export function SideForm({
    title,
    description,
    submitLabel,
    processing,
    onSubmit,
    onCancel,
    children,
}: {
    title: string;
    description: string;
    submitLabel: string;
    processing: boolean;
    onSubmit: () => void;
    /** Shown while editing, to go back to adding. */
    onCancel?: () => void;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <form
            noValidate
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit();
            }}
            className="rounded-xl border bg-card shadow-sm lg:sticky lg:top-4"
        >
            <div className="border-b p-6">
                <h2 className="text-lg font-semibold">{t(title)}</h2>
                <p className="text-sm text-muted-foreground">
                    {t(description)}
                </p>
            </div>
            <div className="grid gap-4 p-6">
                {children}
                <div className="grid gap-2 border-t pt-4">
                    <Button type="submit" disabled={processing}>
                        {t(submitLabel)}
                    </Button>
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                        >
                            {t('Cancel')}
                        </Button>
                    )}
                </div>
            </div>
        </form>
    );
}
