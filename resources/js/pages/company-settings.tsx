import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Banknote,
    Mail,
    Network,
    Palette,
    Save,
    Send,
    Settings as SettingsIcon,
    Trash2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Switch } from '@/components/ui/switch';
import { formatMoney } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import {
    brand,
    currency,
    email,
    system,
    workingDays as workingDaysRoute,
} from '@/routes/settings';
import { test as testEmail } from '@/routes/settings/email';
import emailTemplates from '@/routes/email-templates';
import ipRoutes from '@/routes/settings/ip-restrictions';
import { settings as settingsPage } from '@/routes';
import type { GlobalSettings } from '@/types/global';

type Settings = GlobalSettings & {
    defaultLanguage: string;
    defaultTimezone: string;
    landingPageEnabled: boolean;
    ipRestrictionEnabled: boolean;
    titleText: string;
    footerText: string | null;
    mailDriver: string;
    mailHost: string;
    mailPort: number;
    mailUsername: string;
    mailEncryption: 'tls' | 'ssl' | 'none';
    mailFromAddress: string;
    mailFromName: string;
};

type Props = {
    settings: Settings;
    mailPasswordSet: boolean;
    currencies: { code: string; name: string; symbol: string }[];
    ipRestrictions: { id: number; ip_address: string }[];
    timezones: string[];
    dateFormats: Record<string, string>;
    timeFormats: Record<string, string>;
};

const SECTIONS: { id: string; title: string; icon: LucideIcon }[] = [
    { id: 'system', title: 'System Settings', icon: SettingsIcon },
    { id: 'brand', title: 'Brand Settings', icon: Palette },
    { id: 'currency', title: 'Currency Settings', icon: Banknote },
    { id: 'email', title: 'Email Settings', icon: Mail },
    { id: 'working-days', title: 'Working Days Settings', icon: CalendarDays },
    { id: 'ip-restriction', title: 'IP Restriction Settings', icon: Network },
];

const THEME_COLORS: Record<string, string> = {
    indigo: '#4f46e5',
    blue: '#3b82f6',
    green: '#10b981',
    purple: '#8b5cf6',
    orange: '#f97316',
    red: '#ef4444',
};

const WEEKDAYS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

function Section({
    id,
    title,
    description,
    onSubmit,
    processing,
    children,
}: {
    id: string;
    title: string;
    description: string;
    onSubmit?: (e: FormEvent) => void;
    processing?: boolean;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <form
            noValidate
            id={id}
            onSubmit={onSubmit}
            className="scroll-mt-20 rounded-xl border bg-card p-6 shadow-sm"
        >
            <div className="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-lg font-semibold">{t(title)}</h2>
                    <p className="text-sm text-muted-foreground">
                        {t(description)}
                    </p>
                </div>
                {onSubmit && (
                    <Button type="submit" disabled={processing}>
                        <Save /> {t('Save Changes')}
                    </Button>
                )}
            </div>
            <div className="grid gap-5">{children}</div>
        </form>
    );
}

function Field({
    label,
    error,
    className,
    children,
}: {
    label: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div className={cn('grid gap-2', className)}>
            <Label>{t(label)}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Toggle({
    label,
    description,
    checked,
    onChange,
}: {
    label: string;
    description: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex items-center justify-between gap-4">
            <div>
                <div className="font-medium">{t(label)}</div>
                <div className="text-sm text-muted-foreground">
                    {t(description)}
                </div>
            </div>
            <Switch
                checked={checked}
                onCheckedChange={onChange}
                aria-label={t(label)}
            />
        </div>
    );
}

function SystemSection({
    settings,
    timezones,
    dateFormats,
    timeFormats,
}: Props) {
    const { locales } = usePage().props;
    const form = useForm({
        defaultLanguage: settings.defaultLanguage,
        dateFormat: settings.dateFormat,
        timeFormat: settings.timeFormat,
        calendarStartDay: settings.calendarStartDay,
        defaultTimezone: settings.defaultTimezone,
        landingPageEnabled: settings.landingPageEnabled,
        ipRestrictionEnabled: settings.ipRestrictionEnabled,
    });

    return (
        <Section
            id="system"
            title="System Settings"
            description="Configure system-wide settings for your application"
            onSubmit={(e) => {
                e.preventDefault();
                form.submit(system(), { preserveScroll: true });
            }}
            processing={form.processing}
        >
            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    label="Default Language"
                    error={form.errors.defaultLanguage}
                >
                    <SelectField
                        value={form.data.defaultLanguage}
                        onChange={(e) =>
                            form.setData('defaultLanguage', e.target.value)
                        }
                    >
                        {Object.entries(locales).map(([code, [name]]) => (
                            <option key={code} value={code}>
                                {name}
                            </option>
                        ))}
                    </SelectField>
                </Field>
                <Field label="Date Format" error={form.errors.dateFormat}>
                    <SelectField
                        value={form.data.dateFormat}
                        onChange={(e) =>
                            form.setData('dateFormat', e.target.value)
                        }
                    >
                        {Object.entries(dateFormats).map(
                            ([format, example]) => (
                                <option key={format} value={format}>
                                    {format} ({example})
                                </option>
                            ),
                        )}
                    </SelectField>
                </Field>
                <Field label="Time Format" error={form.errors.timeFormat}>
                    <SelectField
                        value={form.data.timeFormat}
                        onChange={(e) =>
                            form.setData('timeFormat', e.target.value)
                        }
                    >
                        {Object.entries(timeFormats).map(
                            ([format, example]) => (
                                <option key={format} value={format}>
                                    {format} ({example})
                                </option>
                            ),
                        )}
                    </SelectField>
                </Field>
                <Field
                    label="Calendar Start Day"
                    error={form.errors.calendarStartDay}
                >
                    <SelectField
                        value={form.data.calendarStartDay}
                        onChange={(e) =>
                            form.setData(
                                'calendarStartDay',
                                e.target.value as 'sunday' | 'monday',
                            )
                        }
                    >
                        <option value="sunday">Sunday</option>
                        <option value="monday">Monday</option>
                    </SelectField>
                </Field>
            </div>
            <Field label="Default Timezone" error={form.errors.defaultTimezone}>
                <SelectField
                    value={form.data.defaultTimezone}
                    onChange={(e) =>
                        form.setData('defaultTimezone', e.target.value)
                    }
                >
                    {timezones.map((zone) => (
                        <option key={zone} value={zone}>
                            {zone}
                        </option>
                    ))}
                </SelectField>
            </Field>
            <Toggle
                label="IP Restriction"
                description="Enable IP address restrictions for enhanced security"
                checked={form.data.ipRestrictionEnabled}
                onChange={(checked) =>
                    form.setData('ipRestrictionEnabled', checked)
                }
            />
            <Toggle
                label="Landing Page"
                description="Enable or disable the public landing page"
                checked={form.data.landingPageEnabled}
                onChange={(checked) =>
                    form.setData('landingPageEnabled', checked)
                }
            />
        </Section>
    );
}

function BrandSection({ settings }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        titleText: settings.titleText,
        footerText: settings.footerText ?? '',
        themeColor: settings.themeColor,
        customColor: settings.customColor,
    });

    return (
        <Section
            id="brand"
            title="Brand Settings"
            description="Customize your application's branding and appearance"
            onSubmit={(e) => {
                e.preventDefault();
                form.submit(brand(), { preserveScroll: true });
            }}
            processing={form.processing}
        >
            <div className="grid gap-5 md:grid-cols-2">
                <Field label="Title Text" error={form.errors.titleText}>
                    <Input
                        value={form.data.titleText}
                        onChange={(e) =>
                            form.setData('titleText', e.target.value)
                        }
                    />
                </Field>
                <Field label="Footer Text" error={form.errors.footerText}>
                    <Input
                        value={form.data.footerText}
                        onChange={(e) =>
                            form.setData('footerText', e.target.value)
                        }
                    />
                </Field>
            </div>
            <Field label="Theme Color" error={form.errors.themeColor}>
                <div className="flex flex-wrap items-center gap-3">
                    {Object.entries(THEME_COLORS).map(([name, hex]) => (
                        <button
                            key={name}
                            type="button"
                            onClick={() => form.setData('themeColor', name)}
                            aria-label={t(name)}
                            aria-pressed={form.data.themeColor === name}
                            className={cn(
                                'size-9 rounded-full ring-offset-2 ring-offset-background',
                                form.data.themeColor === name && 'ring-2',
                            )}
                            style={{ backgroundColor: hex, color: hex }}
                        />
                    ))}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="color"
                            value={form.data.customColor}
                            onChange={(e) => {
                                form.setData({
                                    ...form.data,
                                    themeColor: 'custom',
                                    customColor: e.target.value,
                                });
                            }}
                            className={cn(
                                'size-9 cursor-pointer rounded-full border',
                                form.data.themeColor === 'custom' &&
                                    'ring-2 ring-primary ring-offset-2',
                            )}
                        />
                        {t('Custom')}
                    </label>
                </div>
            </Field>
        </Section>
    );
}

function CurrencySection({ settings, currencies }: Props) {
    const form = useForm({
        defaultCurrency: settings.defaultCurrency,
        decimalFormat: settings.decimalFormat,
        decimalSeparator: settings.decimalSeparator,
        thousandsSeparator: settings.thousandsSeparator,
        currencySymbolPosition: settings.currencySymbolPosition,
        currencySymbolSpace: settings.currencySymbolSpace,
    });
    const { t } = useTranslation();
    const preview = formatMoney(1234567.891, {
        ...settings,
        ...form.data,
        currencySymbol:
            currencies.find((c) => c.code === form.data.defaultCurrency)
                ?.symbol ?? form.data.defaultCurrency,
    });

    return (
        <Section
            id="currency"
            title="Currency Settings"
            description="Configure how amounts are displayed"
            onSubmit={(e) => {
                e.preventDefault();
                form.submit(currency(), { preserveScroll: true });
            }}
            processing={form.processing}
        >
            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    label="Default Currency"
                    error={form.errors.defaultCurrency}
                >
                    <SelectField
                        value={form.data.defaultCurrency}
                        onChange={(e) =>
                            form.setData('defaultCurrency', e.target.value)
                        }
                    >
                        {currencies.map((c) => (
                            <option key={c.code} value={c.code}>
                                {c.name} ({c.code} {c.symbol})
                            </option>
                        ))}
                    </SelectField>
                </Field>
                <Field label="Decimal Places" error={form.errors.decimalFormat}>
                    <SelectField
                        value={form.data.decimalFormat}
                        onChange={(e) =>
                            form.setData('decimalFormat', +e.target.value)
                        }
                    >
                        {[0, 1, 2, 3, 4].map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </SelectField>
                </Field>
                <Field
                    label="Decimal Separator"
                    error={form.errors.decimalSeparator}
                >
                    <SelectField
                        value={form.data.decimalSeparator}
                        onChange={(e) =>
                            form.setData('decimalSeparator', e.target.value)
                        }
                    >
                        <option value=".">{t('Dot')} (.)</option>
                        <option value=",">{t('Comma')} (,)</option>
                    </SelectField>
                </Field>
                <Field
                    label="Thousands Separator"
                    error={form.errors.thousandsSeparator}
                >
                    <SelectField
                        value={form.data.thousandsSeparator}
                        onChange={(e) =>
                            form.setData('thousandsSeparator', e.target.value)
                        }
                    >
                        <option value=",">{t('Comma')} (,)</option>
                        <option value=".">{t('Dot')} (.)</option>
                        <option value=" ">{t('Space')}</option>
                        <option value="">{t('None')}</option>
                    </SelectField>
                </Field>
                <Field
                    label="Currency Symbol Position"
                    error={form.errors.currencySymbolPosition}
                >
                    <SelectField
                        value={form.data.currencySymbolPosition}
                        onChange={(e) =>
                            form.setData(
                                'currencySymbolPosition',
                                e.target.value as 'before' | 'after',
                            )
                        }
                    >
                        <option value="before">{t('Before')}</option>
                        <option value="after">{t('After')}</option>
                    </SelectField>
                </Field>
                <div className="flex items-end">
                    <Toggle
                        label="Space between symbol and amount"
                        description=""
                        checked={form.data.currencySymbolSpace}
                        onChange={(checked) =>
                            form.setData('currencySymbolSpace', checked)
                        }
                    />
                </div>
            </div>
            <div className="rounded-lg bg-muted px-4 py-3 text-sm">
                {t('Preview')}:{' '}
                <span className="font-semibold" dir="ltr">
                    {preview}
                </span>
            </div>
        </Section>
    );
}

function EmailSection({ settings, mailPasswordSet }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        mailHost: settings.mailHost,
        mailPort: settings.mailPort,
        mailUsername: settings.mailUsername,
        mailPassword: '',
        mailEncryption: settings.mailEncryption,
        mailFromAddress: settings.mailFromAddress,
        mailFromName: settings.mailFromName,
    });
    const test = useForm({ email: '' });

    return (
        <Section
            id="email"
            title="Email Settings"
            description="Configure the SMTP server used to send emails"
            onSubmit={(e) => {
                e.preventDefault();
                form.submit(email(), {
                    preserveScroll: true,
                    onSuccess: () => form.reset('mailPassword'),
                });
            }}
            processing={form.processing}
        >
            <div className="grid gap-5 md:grid-cols-2">
                <Field label="Mail Host" error={form.errors.mailHost}>
                    <Input
                        value={form.data.mailHost}
                        placeholder="smtp.example.com"
                        onChange={(e) =>
                            form.setData('mailHost', e.target.value)
                        }
                    />
                </Field>
                <Field label="Mail Port" error={form.errors.mailPort}>
                    <Input
                        type="number"
                        value={form.data.mailPort}
                        onChange={(e) =>
                            form.setData('mailPort', +e.target.value)
                        }
                    />
                </Field>
                <Field label="Mail Username" error={form.errors.mailUsername}>
                    <Input
                        value={form.data.mailUsername}
                        autoComplete="off"
                        onChange={(e) =>
                            form.setData('mailUsername', e.target.value)
                        }
                    />
                </Field>
                <Field label="Mail Password" error={form.errors.mailPassword}>
                    <Input
                        type="password"
                        value={form.data.mailPassword}
                        autoComplete="new-password"
                        placeholder={
                            mailPasswordSet
                                ? t('Leave blank to keep the current password')
                                : ''
                        }
                        onChange={(e) =>
                            form.setData('mailPassword', e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Mail Encryption"
                    error={form.errors.mailEncryption}
                >
                    <SelectField
                        value={form.data.mailEncryption}
                        onChange={(e) =>
                            form.setData(
                                'mailEncryption',
                                e.target.value as 'tls' | 'ssl' | 'none',
                            )
                        }
                    >
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none">{t('None')}</option>
                    </SelectField>
                </Field>
                <Field
                    label="Mail From Address"
                    error={form.errors.mailFromAddress}
                >
                    <Input
                        type="email"
                        value={form.data.mailFromAddress}
                        onChange={(e) =>
                            form.setData('mailFromAddress', e.target.value)
                        }
                    />
                </Field>
                <Field label="Mail From Name" error={form.errors.mailFromName}>
                    <Input
                        value={form.data.mailFromName}
                        onChange={(e) =>
                            form.setData('mailFromName', e.target.value)
                        }
                    />
                </Field>
            </div>
            <div className="grid gap-2 border-t pt-5">
                <Label htmlFor="test-email">{t('Send Test Email')}</Label>
                <div className="flex gap-2">
                    <Input
                        id="test-email"
                        type="email"
                        placeholder="you@example.com"
                        value={test.data.email}
                        onChange={(e) => test.setData('email', e.target.value)}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={test.processing || !settings.mailHost}
                        onClick={() =>
                            test.submit(testEmail(), { preserveScroll: true })
                        }
                    >
                        <Send /> {t('Send')}
                    </Button>
                </div>
                {!settings.mailHost && (
                    <p className="text-sm text-muted-foreground">
                        {t('Save your SMTP settings before sending a test.')}
                    </p>
                )}
                <InputError message={test.errors.email} />
                <Link
                    href={emailTemplates.index()}
                    className="text-sm text-primary underline-offset-4 hover:underline"
                >
                    {t('Edit the email templates')}
                </Link>
            </div>
        </Section>
    );
}

function WorkingDaysSection({ settings }: Props) {
    const { t } = useTranslation();
    const form = useForm({ workingDays: settings.workingDays });
    const toggle = (day: number) =>
        form.setData(
            'workingDays',
            form.data.workingDays.includes(day)
                ? form.data.workingDays.filter((d) => d !== day)
                : [...form.data.workingDays, day],
        );

    return (
        <Section
            id="working-days"
            title="Working Days Settings"
            description="Choose the days your company works"
            onSubmit={(e) => {
                e.preventDefault();
                form.submit(workingDaysRoute(), { preserveScroll: true });
            }}
            processing={form.processing}
        >
            <div className="flex flex-wrap gap-2">
                {WEEKDAYS.map((name, day) => (
                    <button
                        key={name}
                        type="button"
                        aria-pressed={form.data.workingDays.includes(day)}
                        onClick={() => toggle(day)}
                        className={cn(
                            'rounded-lg border px-4 py-2 text-sm font-medium transition-colors',
                            form.data.workingDays.includes(day)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-accent',
                        )}
                    >
                        {t(name)}
                    </button>
                ))}
            </div>
            <InputError message={form.errors.workingDays} />
        </Section>
    );
}

function IpRestrictionSection({ settings, ipRestrictions }: Props) {
    const { t } = useTranslation();
    const form = useForm({ ip_address: '' });

    return (
        <Section
            id="ip-restriction"
            title="IP Restriction Settings"
            description="Only these IP addresses can sign in while IP restriction is enabled (settings managers are always allowed)"
        >
            {!settings.ipRestrictionEnabled && (
                <p className="rounded-lg bg-muted px-4 py-3 text-sm">
                    {t(
                        'IP restriction is currently disabled. Turn it on in System Settings.',
                    )}
                </p>
            )}
            <div className="flex gap-2">
                <Input
                    placeholder="192.168.1.1"
                    aria-label={t('IP Address')}
                    value={form.data.ip_address}
                    onChange={(e) => form.setData('ip_address', e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            form.submit(ipRoutes.store(), {
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            });
                        }
                    }}
                />
                <Button
                    type="button"
                    disabled={form.processing}
                    onClick={() =>
                        form.submit(ipRoutes.store(), {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        })
                    }
                >
                    {t('Add')}
                </Button>
            </div>
            <InputError message={form.errors.ip_address} />
            <ul className="divide-y rounded-lg border">
                {ipRestrictions.map((ip) => (
                    <li
                        key={ip.id}
                        className="flex items-center justify-between px-4 py-2"
                    >
                        <span className="font-mono text-sm">
                            {ip.ip_address}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={t('Delete')}
                            onClick={() =>
                                router.delete(ipRoutes.destroy(ip.id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Trash2 />
                        </Button>
                    </li>
                ))}
                {ipRestrictions.length === 0 && (
                    <li className="px-4 py-6 text-center text-sm text-muted-foreground">
                        {t('No IP addresses added yet')}
                    </li>
                )}
            </ul>
        </Section>
    );
}

export default function CompanySettings(props: Props) {
    const { t } = useTranslation();
    const [active, setActive] = useState(SECTIONS[0].id);

    return (
        <>
            <Head title={t('Settings')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold">{t('Settings')}</h1>
                    <p className="text-sm text-muted-foreground">
                        {t('Manage system settings.')}
                    </p>
                </div>
                <div className="grid items-start gap-6 lg:grid-cols-[14rem_1fr]">
                    <nav className="sticky top-4 grid gap-1 rounded-xl border bg-card p-2 shadow-sm">
                        {SECTIONS.map(({ id, title, icon: Icon }) => (
                            <a
                                key={id}
                                href={`#${id}`}
                                onClick={() => setActive(id)}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent',
                                    active === id && 'bg-accent text-primary',
                                )}
                            >
                                <Icon className="size-4 shrink-0" />
                                {t(title)}
                            </a>
                        ))}
                    </nav>
                    <div className="grid gap-6">
                        <SystemSection {...props} />
                        <BrandSection {...props} />
                        <CurrencySection {...props} />
                        <EmailSection {...props} />
                        <WorkingDaysSection {...props} />
                        <IpRestrictionSection {...props} />
                    </div>
                </div>
            </div>
        </>
    );
}

CompanySettings.layout = {
    breadcrumbs: [{ title: 'Settings', href: settingsPage() }],
};
