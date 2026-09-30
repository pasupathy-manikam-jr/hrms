import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavMenuItem, NavSection } from '@/types';
import { useTranslation } from '@/hooks/use-translation';
import { toUrl } from '@/lib/utils';

const matches = (title: string, query: string) =>
    title.toLowerCase().includes(query);

// Drops entries the user lacks permission for, and parents left without children.
function allowedSections(
    sections: NavSection[],
    permissions: string[],
): NavSection[] {
    const can = (permission?: string) =>
        !permission || permissions.includes(permission);

    return sections
        .map((section) => ({
            ...section,
            items: section.items.flatMap((item): NavMenuItem[] => {
                if (!item.children) {
                    return can(item.permission) ? [item] : [];
                }

                const children = item.children.filter((child) =>
                    can(child.permission),
                );

                return children.length ? [{ ...item, children }] : [];
            }),
        }))
        .filter((section) => section.items.length > 0);
}

// Keeps sections/items whose title (or any child title) matches the query.
function filterSections(sections: NavSection[], query: string): NavSection[] {
    if (!query) {
        return sections;
    }

    return sections
        .map((section) => ({
            ...section,
            items: section.items.flatMap((item): NavMenuItem[] => {
                if (matches(item.title, query) || !item.children) {
                    return matches(item.title, query) ? [item] : [];
                }

                const children = item.children.filter((child) =>
                    matches(child.title, query),
                );

                return children.length ? [{ ...item, children }] : [];
            }),
        }))
        .filter((section) => section.items.length > 0);
}

export function NavMain({
    sections,
    query = '',
}: {
    sections: NavSection[];
    query?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const search = query.trim().toLowerCase();
    const visible = filterSections(
        allowedSections(sections, auth.permissions),
        search,
    );
    // Accordion: one group open at a time, starting with the one holding the current page.
    const [openGroup, setOpenGroup] = useState<string | null>(
        () =>
            visible
                .flatMap((section) => section.items)
                .find((item) =>
                    item.children?.some((child) => isCurrentUrl(child.href)),
                )?.title ?? null,
    );

    // On load, scroll the sidebar so the current page's entry sits in the middle, not below the fold.
    useEffect(() => {
        const frame = requestAnimationFrame(() => {
            const active = document.querySelector<HTMLElement>(
                '[data-sidebar="content"] [data-active="true"]',
            );
            const container = active?.closest<HTMLElement>(
                '[data-sidebar="content"]',
            );

            if (active && container) {
                const offset =
                    active.getBoundingClientRect().top -
                    container.getBoundingClientRect().top;
                container.scrollTop +=
                    offset -
                    container.clientHeight / 2 +
                    active.offsetHeight / 2;
            }
        });

        return () => cancelAnimationFrame(frame);
    }, []);

    return visible.map((section) => (
        <SidebarGroup key={section.title} className="px-2 py-1">
            <SidebarGroupLabel className="text-[13px] font-semibold text-sidebar-foreground/70">
                {t(section.title)}
            </SidebarGroupLabel>
            <SidebarMenu>
                {section.items.map((item) =>
                    item.children ? (
                        <Collapsible
                            key={item.title}
                            asChild
                            // While searching every matching group is open; otherwise only one at a time.
                            open={!!search || openGroup === item.title}
                            onOpenChange={(open) =>
                                setOpenGroup(open ? item.title : null)
                            }
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        tooltip={{ children: t(item.title) }}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{t(item.title)}</span>
                                        <ChevronRight className="ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 rtl:rotate-180" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        {item.children.map((child) => (
                                            <SidebarMenuSubItem
                                                key={child.title}
                                            >
                                                <SidebarMenuSubButton
                                                    asChild
                                                    className="data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground"
                                                    isActive={isCurrentUrl(
                                                        child.href,
                                                    )}
                                                >
                                                    {child.external ? (
                                                        <a
                                                            href={toUrl(
                                                                child.href,
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <span>
                                                                {t(child.title)}
                                                            </span>
                                                        </a>
                                                    ) : (
                                                        <Link
                                                            href={child.href}
                                                            prefetch
                                                        >
                                                            <span>
                                                                {t(child.title)}
                                                            </span>
                                                        </Link>
                                                    )}
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                className="data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground"
                                tooltip={{ children: t(item.title) }}
                            >
                                {item.external ? (
                                    <a
                                        href={toUrl(item.href)}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{t(item.title)}</span>
                                    </a>
                                ) : (
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{t(item.title)}</span>
                                    </Link>
                                )}
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    ));
}
