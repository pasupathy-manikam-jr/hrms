import { Head, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import type { LandingContent, SectionKey } from '@/components/landing-shell';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';
import { settings as settingsRoute } from '@/routes/landing-page';
import { update } from '@/routes/landing-page/settings';

type Item = Record<string, string>;
type SectionData = Record<string, string | Item[]>;

const TITLES: Record<SectionKey, string> = {
    hero: 'Hero',
    features: 'Features',
    screenshots: 'Screenshots',
    why_choose_us: 'Why Choose Us',
    about: 'About',
    team: 'Team',
    testimonials: 'Testimonials',
    faq: 'FAQ',
    newsletter: 'Newsletter',
    contact: 'Contact',
    footer: 'Footer',
};

/** Item fields of each repeatable list (mirrors LandingPageSetting::SCHEMA). */
const LISTS: Record<string, { title: string; keys: string[] }> = {
    stats: { title: 'Stats', keys: ['value', 'label'] },
    trust_stats: { title: 'Trust Stats', keys: ['value', 'label'] },
    features_list: {
        title: 'Features',
        keys: ['icon', 'title', 'description'],
    },
    screenshots_list: {
        title: 'Screenshots',
        keys: ['icon', 'title', 'description'],
    },
    reasons: { title: 'Reasons', keys: ['icon', 'title', 'description'] },
    values: { title: 'Values', keys: ['icon', 'title', 'description'] },
    benefits: { title: 'Benefits', keys: ['icon', 'title', 'description'] },
    members: { title: 'Team Members', keys: ['name', 'role', 'bio', 'email'] },
    testimonials: {
        title: 'Testimonials',
        keys: ['name', 'role', 'company', 'content'],
    },
    faqs: { title: 'FAQs', keys: ['question', 'answer'] },
};

const LONG = /description|subtitle|content|bio|answer|story_title/;

// "cta_button_text" -> "CTA Button Text"
const label = (key: string) =>
    key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase())
        .replace(/\bCta\b/, 'CTA');

const textareaClass =
    'min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

function TextField({
    id,
    name,
    value,
    error,
    onChange,
}: {
    id: string;
    name: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{t(label(name))}</Label>
            {LONG.test(name) ? (
                <textarea
                    id={id}
                    rows={3}
                    className={textareaClass}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
            ) : (
                <Input
                    id={id}
                    type={name === 'email' ? 'email' : 'text'}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
            )}
            <InputError message={error} />
        </div>
    );
}

/** A repeatable list (features, FAQs, members, stats, ...) with add/remove. */
function ListEditor({
    title,
    keys,
    items,
    icons,
    prefix,
    errors,
    onChange,
}: {
    title: string;
    keys: string[];
    items: Item[];
    /** Icon names for a select, or null for free text (emoji). */
    icons: string[] | null;
    prefix: string;
    errors: Record<string, string | undefined>;
    onChange: (items: Item[]) => void;
}) {
    const { t } = useTranslation();
    const blank = Object.fromEntries(
        keys.map((key) => [key, key === 'icon' ? (icons?.[0] ?? '⭐') : '']),
    );
    const set = (index: number, key: string, value: string) =>
        onChange(
            items.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        );

    return (
        <fieldset className="grid gap-3">
            <div className="flex items-center justify-between">
                <legend className="font-semibold">{t(title)}</legend>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onChange([...items, blank])}
                >
                    <Plus /> {t('Add')}
                </Button>
            </div>
            <InputError message={errors[prefix]} />
            {items.map((item, index) => (
                <div
                    key={index}
                    className="relative grid gap-3 rounded-lg border p-4 pe-12 md:grid-cols-2"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="absolute end-2 top-2"
                        aria-label={t('Remove')}
                        onClick={() =>
                            onChange(items.filter((_, i) => i !== index))
                        }
                    >
                        <Trash2 />
                    </Button>
                    {keys.map((key) => {
                        const id = `${prefix}.${index}.${key}`;

                        return key === 'icon' && icons ? (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={id}>{t('Icon')}</Label>
                                <SelectField
                                    id={id}
                                    value={item.icon}
                                    onChange={(e) =>
                                        set(index, key, e.target.value)
                                    }
                                >
                                    {icons.map((icon) => (
                                        <option key={icon} value={icon}>
                                            {label(icon.replace(/-/g, '_'))}
                                        </option>
                                    ))}
                                </SelectField>
                                <InputError message={errors[id]} />
                            </div>
                        ) : (
                            <TextField
                                key={key}
                                id={id}
                                name={key}
                                value={item[key] ?? ''}
                                error={errors[id]}
                                onChange={(value) => set(index, key, value)}
                            />
                        );
                    })}
                </div>
            ))}
        </fieldset>
    );
}

export default function LandingPageSettings({
    settings,
    icons,
}: {
    settings: LandingContent;
    icons: string[];
}) {
    const { t } = useTranslation();
    const can = useCan();
    const form = useForm(settings);
    const [active, setActive] = useState<SectionKey>('hero');
    const errors = form.errors as Record<string, string | undefined>;
    const order: SectionKey[] = [...form.data.section_order, 'footer'];
    const section = form.data.sections[active] as unknown as SectionData;

    const setSection = (data: SectionData) =>
        form.setData('sections', {
            ...form.data.sections,
            [active]: data,
        });

    const move = (index: number, step: -1 | 1) => {
        const next = [...form.data.section_order];
        [next[index], next[index + step]] = [next[index + step], next[index]];
        form.setData('section_order', next);
    };

    const hasError = (key: SectionKey) =>
        Object.keys(errors).some((error) =>
            error.startsWith(`sections.${key}.`),
        );

    return (
        <>
            <Head title={t('Landing Page')} />
            <form
                noValidate
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(update(), { preserveScroll: true });
                }}
            >
                <PageHeader
                    title="Landing Page"
                    description="Edit the content, order and visibility of the public landing page sections."
                    action={
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <a href={home().url} target="_blank">
                                    <ExternalLink /> {t('View Landing Page')}
                                </a>
                            </Button>
                            {can('edit-landing-page') && (
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? <Spinner /> : <Save />}
                                    {t('Save Changes')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="grid items-start gap-6 lg:grid-cols-[18rem_1fr]">
                    <nav className="grid gap-1 rounded-xl border bg-card p-2 shadow-sm lg:sticky lg:top-4">
                        {order.map((key, index) => (
                            <div
                                key={key}
                                className={cn(
                                    'flex items-center gap-1 rounded-lg ps-3 hover:bg-accent',
                                    active === key && 'bg-accent',
                                )}
                            >
                                <button
                                    type="button"
                                    onClick={() => setActive(key)}
                                    className={cn(
                                        'flex-1 py-2 text-start text-sm font-medium',
                                        active === key && 'text-primary',
                                        hasError(key) && 'text-destructive',
                                        !form.data.section_visibility[key] &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    {t(TITLES[key])}
                                </button>
                                {key !== 'footer' && (
                                    <>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            aria-label={t('Move up')}
                                            disabled={index === 0}
                                            onClick={() => move(index, -1)}
                                        >
                                            <ArrowUp />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            aria-label={t('Move down')}
                                            disabled={
                                                index === order.length - 2
                                            }
                                            onClick={() => move(index, 1)}
                                        >
                                            <ArrowDown />
                                        </Button>
                                    </>
                                )}
                                <Switch
                                    className="me-2 scale-75"
                                    aria-label={t('Show :section', {
                                        section: t(TITLES[key]),
                                    })}
                                    checked={form.data.section_visibility[key]}
                                    onCheckedChange={(checked) =>
                                        form.setData('section_visibility', {
                                            ...form.data.section_visibility,
                                            [key]: checked,
                                        })
                                    }
                                />
                            </div>
                        ))}
                    </nav>

                    <div className="grid gap-6 rounded-xl border bg-card p-6 shadow-sm">
                        <h2 className="text-lg font-semibold">
                            {t(TITLES[active])}
                        </h2>

                        <div className="grid gap-4 md:grid-cols-2">
                            {Object.entries(section)
                                .filter(([, value]) => !Array.isArray(value))
                                .map(([name, value]) => (
                                    <TextField
                                        key={name}
                                        id={`${active}-${name}`}
                                        name={name}
                                        value={value as string}
                                        error={
                                            errors[`sections.${active}.${name}`]
                                        }
                                        onChange={(v) =>
                                            setSection({
                                                ...section,
                                                [name]: v,
                                            })
                                        }
                                    />
                                ))}
                        </div>

                        {Object.entries(section)
                            .filter(([, value]) => Array.isArray(value))
                            .map(([list, value]) => (
                                <ListEditor
                                    key={list}
                                    title={LISTS[list].title}
                                    keys={LISTS[list].keys}
                                    items={value as Item[]}
                                    icons={
                                        active === 'newsletter' ? null : icons
                                    }
                                    prefix={`sections.${active}.${list}`}
                                    errors={errors}
                                    onChange={(items) =>
                                        setSection({
                                            ...section,
                                            [list]: items,
                                        })
                                    }
                                />
                            ))}
                    </div>
                </div>
            </form>
        </>
    );
}

LandingPageSettings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Landing Page', href: settingsRoute() },
    ],
};
