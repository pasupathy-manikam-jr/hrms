import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    permission?: string;
    /** Opens in a new tab outside the app (e.g. the public careers site). */
    external?: boolean;
};

// A sidebar entry: a link (href) or a collapsible parent (children).
export type NavMenuItem = Omit<NavItem, 'href'> &
    (
        | { href: NavItem['href']; children?: never }
        | { href?: never; children: NavItem[] }
    );

export type NavSection = {
    title: string;
    items: NavMenuItem[];
};
