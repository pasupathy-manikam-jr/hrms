import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/** "List | Kanban" style switch between a module's views, for the page header. */
export function ViewToggle({
    views,
    current,
}: {
    views: {
        label: string;
        href: NonNullable<InertiaLinkProps['href']>;
        icon: LucideIcon;
    }[];
    current: string;
}) {
    const { t } = useTranslation();

    return (
        <nav
            aria-label={t('Views')}
            className="flex overflow-hidden rounded-md border text-sm"
        >
            {views.map(({ label, href, icon: Icon }) => (
                <Link
                    key={label}
                    href={href}
                    aria-current={label === current ? 'page' : undefined}
                    className={cn(
                        'flex items-center gap-1.5 border-e px-3 py-1.5 last:border-e-0 hover:bg-muted',
                        label === current &&
                            'bg-primary text-primary-foreground hover:bg-primary',
                    )}
                >
                    <Icon className="size-4" />
                    {t(label)}
                </Link>
            ))}
        </nav>
    );
}
