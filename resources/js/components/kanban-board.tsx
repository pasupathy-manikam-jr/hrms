import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { applyFilters } from '@/components/table-filters';
import { Input } from '@/components/ui/input';
import { SelectField } from '@/components/select-field';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { TableFilters } from '@/types';

type Item = { id: number; status: string };

/** Search box for a board's `search` filter; applies on Enter. */
export function KanbanSearch({
    url,
    filters,
}: {
    url: NonNullable<InertiaLinkProps['href']>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(String(filters.search ?? ''));

    return (
        <form
            noValidate
            role="search"
            onSubmit={(e) => {
                e.preventDefault();
                applyFilters(url, filters, { search });
            }}
        >
            <Input
                type="search"
                aria-label={t('Search')}
                placeholder={t('Search...')}
                className="w-56"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
            />
        </form>
    );
}

/**
 * One column per status. Movable cards can be dragged to another column (native HTML5
 * drag-and-drop) or moved with the status menu on the card (keyboard / touch). The page's
 * `onMove` saves the change through the module's own endpoint, so its rules still apply.
 */
export function KanbanBoard<T extends Item>({
    statuses,
    items,
    renderCard,
    canMove,
    onMove,
}: {
    statuses: readonly string[];
    items: T[];
    renderCard: (item: T) => ReactNode;
    canMove: (item: T) => boolean;
    onMove: (item: T, status: string) => void;
}) {
    const { t } = useTranslation();
    const [dragging, setDragging] = useState<T | null>(null);
    const [over, setOver] = useState<string | null>(null);

    const drop = (status: string) => {
        if (dragging && dragging.status !== status) {
            onMove(dragging, status);
        }

        setDragging(null);
        setOver(null);
    };

    return (
        <div className="flex gap-4 overflow-x-auto pb-2">
            {statuses.map((status) => {
                const cards = items.filter((item) => item.status === status);

                return (
                    <section
                        key={status}
                        aria-label={t(status)}
                        onDragOver={(e) => {
                            if (dragging) {
                                e.preventDefault();
                                setOver(status);
                            }
                        }}
                        onDragLeave={() => setOver(null)}
                        onDrop={(e) => {
                            e.preventDefault();
                            drop(status);
                        }}
                        className={cn(
                            'flex w-72 shrink-0 flex-col gap-3 rounded-xl border bg-muted/40 p-3 transition-colors',
                            over === status &&
                                dragging?.status !== status &&
                                'border-primary bg-primary/5',
                        )}
                    >
                        <header className="flex items-center justify-between gap-2">
                            <StatusBadge status={status} />
                            <span className="text-sm text-muted-foreground">
                                {cards.length}
                            </span>
                        </header>
                        {cards.map((item) => {
                            const movable = canMove(item);

                            return (
                                <article
                                    key={item.id}
                                    draggable={movable}
                                    onDragStart={(e) => {
                                        e.dataTransfer.effectAllowed = 'move';
                                        e.dataTransfer.setData(
                                            'text/plain',
                                            String(item.id),
                                        );
                                        setDragging(item);
                                    }}
                                    onDragEnd={() => {
                                        setDragging(null);
                                        setOver(null);
                                    }}
                                    className={cn(
                                        'grid gap-2 rounded-lg border bg-card p-3 text-sm shadow-sm',
                                        movable && 'cursor-grab',
                                        dragging?.id === item.id &&
                                            'opacity-50',
                                    )}
                                >
                                    {renderCard(item)}
                                    {movable && (
                                        <SelectField
                                            aria-label={t('Change status')}
                                            className="h-8 text-xs"
                                            value={item.status}
                                            onChange={(e) =>
                                                onMove(item, e.target.value)
                                            }
                                        >
                                            {statuses.map((option) => (
                                                <option
                                                    key={option}
                                                    value={option}
                                                >
                                                    {t(option)}
                                                </option>
                                            ))}
                                        </SelectField>
                                    )}
                                </article>
                            );
                        })}
                        {cards.length === 0 && (
                            <p className="py-6 text-center text-xs text-muted-foreground">
                                {t('No records')}
                            </p>
                        )}
                    </section>
                );
            })}
        </div>
    );
}
