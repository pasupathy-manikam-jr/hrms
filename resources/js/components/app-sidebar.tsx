import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import HrmWordmark, { HrmMark } from '@/components/hrm-wordmark';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarInput,
} from '@/components/ui/sidebar';
import { navigation } from '@/lib/navigation';
import { dashboard } from '@/routes';
import { useTranslation } from '@/hooks/use-translation';

export function AppSidebar() {
    const [query, setQuery] = useState('');
    const { t, isRtl } = useTranslation();

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            side={isRtl ? 'right' : 'left'}
        >
            <SidebarHeader className="gap-3 pt-3">
                <Link
                    href={dashboard()}
                    prefetch
                    className="flex justify-center"
                >
                    <HrmWordmark className="text-white group-data-[collapsible=icon]:hidden dark:text-white" />
                    <HrmMark className="hidden text-2xl font-extrabold text-primary group-data-[collapsible=icon]:block" />
                </Link>
                <div className="relative group-data-[collapsible=icon]:hidden">
                    <Search className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <SidebarInput
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('Search menu...')}
                        aria-label={t('Search menu...')}
                        className="h-9 ps-8"
                    />
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavMain sections={navigation} query={query} />
            </SidebarContent>
        </Sidebar>
    );
}
