import { Link } from '@inertiajs/react';
import type { LinkComponentBaseProps } from '@inertiajs/core';
import { ArrowLeft } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/** One tab of a detail page: its label, the panel heading (defaults to the label) and content. */
export type DetailTab = { label: string; heading?: string; content: ReactNode };

/**
 * The shared record page (as hr/employees/show): header with a Back button, a left
 * summary card and right-hand tabs.
 */
export function DetailPage({
    title,
    description,
    back,
    summary,
    tabs,
}: {
    title: string;
    description: string;
    back: LinkComponentBaseProps['href'];
    summary: ReactNode;
    tabs: DetailTab[];
}) {
    const { t } = useTranslation();
    const [active, setActive] = useState(0);
    const tab = tabs[active] ?? tabs[0];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={title}
                description={description}
                action={
                    <Button variant="outline" asChild>
                        <Link href={back}>
                            <ArrowLeft className="rtl:rotate-180" /> {t('Back')}
                        </Link>
                    </Button>
                }
            />

            <div className="grid items-start gap-6 rounded-xl border bg-muted/20 p-4 md:p-6 lg:grid-cols-[18rem_1fr]">
                <aside className="flex flex-col items-center rounded-xl border bg-card p-6 text-center shadow-sm">
                    {summary}
                </aside>

                <div className="grid min-w-0 gap-4">
                    {tabs.length > 1 && (
                        <div
                            role="tablist"
                            className="flex flex-wrap gap-1 rounded-lg bg-muted p-1"
                        >
                            {tabs.map((item, index) => (
                                <button
                                    key={item.label}
                                    type="button"
                                    role="tab"
                                    aria-selected={index === active}
                                    onClick={() => setActive(index)}
                                    className={cn(
                                        'flex-1 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap',
                                        index === active
                                            ? 'bg-card shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {t(item.label)}
                                </button>
                            ))}
                        </div>
                    )}
                    <section
                        role="tabpanel"
                        className="min-w-0 rounded-xl border bg-card p-6 shadow-sm"
                    >
                        <h3 className="mb-6 text-lg font-semibold">
                            {t(tab.heading ?? tab.label)}
                        </h3>
                        {tab.content}
                    </section>
                </div>
            </div>
        </div>
    );
}

/** The summary card body: avatar or icon, title, subtitle, status and key facts. */
export function Summary({
    media,
    title,
    subtitle,
    status,
    facts,
}: {
    media: ReactNode;
    title: string;
    subtitle?: ReactNode;
    status?: string | null;
    facts: [LucideIcon, ReactNode][];
}) {
    return (
        <>
            {media}
            <h2 className="mt-4 text-xl font-bold break-words">{title}</h2>
            {subtitle && <p className="text-muted-foreground">{subtitle}</p>}
            {status && (
                <div className="mt-2">
                    <StatusBadge status={status} />
                </div>
            )}
            <ul className="mt-5 grid w-full gap-3 text-start text-sm">
                {facts
                    .filter(([, value]) => value || value === 0)
                    .map(([Icon, value], index) => (
                        <li
                            key={index}
                            className="flex items-center gap-3 break-all"
                        >
                            <Icon className="size-4 shrink-0 text-muted-foreground" />
                            {value}
                        </li>
                    ))}
            </ul>
        </>
    );
}

/** A round icon tile for records without a person (postings, programs…). */
export function SummaryIcon({ icon: Icon }: { icon: LucideIcon }) {
    return (
        <div className="flex size-24 items-center justify-center rounded-full bg-primary/10 text-primary">
            <Icon className="size-12" />
        </div>
    );
}

/** Labelled two-column field grid; empty values show "-". */
export function Fields({ items }: { items: [string, ReactNode][] }) {
    const { t } = useTranslation();

    return (
        <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
            {items.map(([label, value]) => (
                <div key={label}>
                    <dt className="text-sm text-muted-foreground">
                        {t(label)}
                    </dt>
                    <dd className="mt-1 font-medium break-words">
                        {value || value === 0 ? value : '-'}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/** A long free-text field (description, notes…) under its label. */
export function TextBlock({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    const { t } = useTranslation();

    return (
        <div>
            <div className="text-sm text-muted-foreground">{t(label)}</div>
            <p className="mt-1 whitespace-pre-line">{value || '-'}</p>
        </div>
    );
}

/** A bordered list of related records, or an empty note. */
export function RecordList<T extends { id: number }>({
    items,
    empty,
    render,
}: {
    items: T[];
    empty: string;
    render: (item: T) => ReactNode;
}) {
    const { t } = useTranslation();

    if (items.length === 0) {
        return <p className="text-sm text-muted-foreground">{t(empty)}</p>;
    }

    return (
        <ul className="divide-y rounded-lg border">
            {items.map((item) => (
                <li
                    key={item.id}
                    className="flex flex-wrap items-center justify-between gap-4 p-3"
                >
                    {render(item)}
                </li>
            ))}
        </ul>
    );
}

/** A percentage bar with its value, e.g. onboarding or training completion. */
export function ProgressBar({ value }: { value: number }) {
    return (
        <div className="flex items-center gap-3">
            <div
                role="progressbar"
                aria-valuenow={value}
                aria-valuemin={0}
                aria-valuemax={100}
                className="h-2 flex-1 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full rounded-full bg-primary"
                    style={{ width: `${value}%` }}
                />
            </div>
            <span className="text-sm font-medium tabular-nums">{value}%</span>
        </div>
    );
}
