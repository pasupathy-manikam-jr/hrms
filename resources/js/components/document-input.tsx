import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

/** Accepted types; mirrors StoresUploads::UPLOAD_EXTENSIONS on the server. */
const ACCEPT = '.pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.csv';

/**
 * File field for a record's single supporting document. Forms using it must post as multipart
 * (form.post(..., { forceFormData: true }), with updates spoofing PUT via _method).
 */
export function DocumentInput({
    id,
    label = 'Document',
    currentName,
    hasNewFile,
    error,
    onChange,
}: {
    id: string;
    label?: string;
    /** The file already attached, shown until a replacement is picked. */
    currentName?: string | null;
    hasNewFile: boolean;
    error?: string;
    onChange: (file: File | null) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-2 sm:col-span-2">
            <Label htmlFor={id}>{t(label)}</Label>
            <Input
                id={id}
                type="file"
                accept={ACCEPT}
                onChange={(e) => onChange(e.target.files?.[0] ?? null)}
            />
            {currentName && !hasNewFile && (
                <p className="text-xs text-muted-foreground">
                    {t('Current: :name (choose a file to replace it)', {
                        name: currentName,
                    })}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
