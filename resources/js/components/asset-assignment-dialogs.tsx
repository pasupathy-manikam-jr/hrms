import { useForm } from '@inertiajs/react';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useTranslation } from '@/hooks/use-translation';
import assetRoutes from '@/routes/hr/assets';

type Target = { id: number; name: string } | null;

const today = () => new Date().toISOString().slice(0, 10);

/**
 * Check an asset out to an employee. Render with `key={asset?.id}` so the form resets on open.
 */
export function AssignAssetDialog({
    asset,
    employees,
    onClose,
}: {
    asset: Target;
    employees: { id: number; name: string }[];
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({ employee_id: '', assigned_at: today(), notes: '' });

    return (
        <FormDialog
            open={asset !== null}
            onOpenChange={(open) => !open && onClose()}
            title="Assign Asset"
            description={asset?.name}
            submitLabel="Assign"
            onSubmit={(e) => {
                e.preventDefault();

                if (asset) {
                    form.submit(assetRoutes.assign(asset.id), {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }
            }}
            processing={form.processing}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="assign-employee">
                        {t('Employee')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="assign-employee"
                        required
                        value={form.data.employee_id}
                        onChange={(e) =>
                            form.setData('employee_id', e.target.value)
                        }
                    >
                        <option value="">{t('Select Employee')}</option>
                        {employees.map((employee) => (
                            <option key={employee.id} value={employee.id}>
                                {employee.name}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={form.errors.employee_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="assign-date">
                        {t('Assigned Date')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="assign-date"
                        type="date"
                        required
                        value={form.data.assigned_at}
                        onChange={(e) =>
                            form.setData('assigned_at', e.target.value)
                        }
                    />
                    <InputError message={form.errors.assigned_at} />
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="assign-notes">{t('Notes')}</Label>
                    <Input
                        id="assign-notes"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                    <InputError message={form.errors.notes} />
                </div>
            </div>
        </FormDialog>
    );
}

/**
 * Close an asset's open assignment. Render with `key={asset?.id}` so the form resets on open.
 */
export function ReturnAssetDialog({
    asset,
    onClose,
}: {
    asset: Target;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({ returned_at: today(), notes: '' });

    return (
        <FormDialog
            open={asset !== null}
            onOpenChange={(open) => !open && onClose()}
            title="Return Asset"
            description={asset?.name}
            submitLabel="Return"
            onSubmit={(e) => {
                e.preventDefault();

                if (asset) {
                    form.submit(assetRoutes.return(asset.id), {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }
            }}
            processing={form.processing}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="return-date">
                        {t('Return Date')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="return-date"
                        type="date"
                        required
                        value={form.data.returned_at}
                        onChange={(e) =>
                            form.setData('returned_at', e.target.value)
                        }
                    />
                    <InputError message={form.errors.returned_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="return-notes">{t('Notes')}</Label>
                    <Input
                        id="return-notes"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                    <InputError message={form.errors.notes} />
                </div>
            </div>
        </FormDialog>
    );
}
