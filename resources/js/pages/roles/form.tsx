import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import roleRoutes from '@/routes/roles';

type Permission = {
    id: number;
    name: string;
    label: string | null;
    description: string | null;
};

type Role = {
    id: number;
    name: string;
    label: string | null;
    description: string | null;
    permissions: string[];
};

export default function RoleForm({
    role,
    permissions,
}: {
    role: Role | null;
    permissions: Record<string, Permission[]>;
}) {
    const { t } = useTranslation();
    const form = useForm({
        label: role?.label ?? '',
        description: role?.description ?? '',
        permissions: role?.permissions ?? [],
    });
    const all = Object.values(permissions).flatMap((group) =>
        group.map((p) => p.name),
    );
    const selected = new Set(form.data.permissions);

    const toggle = (names: string[], on: boolean) => {
        const next = new Set(selected);
        names.forEach((name) => (on ? next.add(name) : next.delete(name)));
        form.setData('permissions', [...next]);
    };

    const state = (names: string[]) => {
        const count = names.filter((name) => selected.has(name)).length;

        return count === 0
            ? false
            : count === names.length
              ? true
              : ('indeterminate' as const);
    };

    const title = role ? 'Edit Role' : 'Create Role';

    return (
        <>
            <Head title={t(title)} />
            <form
                noValidate
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        role ? roleRoutes.update(role.id) : roleRoutes.store(),
                    );
                }}
            >
                <PageHeader
                    title={title}
                    description="Choose what users with this role can do."
                    action={
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={roleRoutes.index()}>
                                    {t('Cancel')}
                                </Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {t('Save')}
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="role-label">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="role-label"
                            required
                            value={form.data.label}
                            onChange={(e) =>
                                form.setData('label', e.target.value)
                            }
                        />
                        <InputError message={form.errors.label} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="role-description">
                            {t('Description')}
                        </Label>
                        <Input
                            id="role-description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold">
                        {t('Permissions')}{' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            ({selected.size}/{all.length})
                        </span>
                    </h2>
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <Checkbox
                            checked={state(all)}
                            onCheckedChange={(on) => toggle(all, on === true)}
                        />
                        {t('Select all permissions')}
                    </label>
                </div>
                <InputError message={form.errors.permissions} />

                <div className="grid gap-4 lg:grid-cols-2">
                    {Object.entries(permissions).map(([module, group]) => {
                        const names = group.map((p) => p.name);

                        return (
                            <Card key={module} className="gap-3 py-4">
                                <CardHeader className="flex flex-row items-center justify-between px-4">
                                    <CardTitle className="text-base">
                                        {t(module)}
                                    </CardTitle>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            aria-label={`${t('Select all')} ${t(module)}`}
                                            checked={state(names)}
                                            onCheckedChange={(on) =>
                                                toggle(names, on === true)
                                            }
                                        />
                                        {t('Select all')}
                                    </label>
                                </CardHeader>
                                <CardContent className="grid gap-2 px-4 sm:grid-cols-2">
                                    {group.map((p) => (
                                        <label
                                            key={p.name}
                                            className="flex items-start gap-2 text-sm"
                                            title={p.description ?? ''}
                                        >
                                            <Checkbox
                                                className="mt-0.5"
                                                checked={selected.has(p.name)}
                                                onCheckedChange={(on) =>
                                                    toggle(
                                                        [p.name],
                                                        on === true,
                                                    )
                                                }
                                            />
                                            <span>
                                                {t(p.label ?? p.name)}
                                                {p.description && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {t(p.description)}
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                    ))}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </form>
        </>
    );
}

RoleForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles', href: roleRoutes.index() },
    ],
};
