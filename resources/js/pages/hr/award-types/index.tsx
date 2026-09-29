import { Award } from 'lucide-react';
import { LookupPage } from '@/components/lookup-page';
import type { LookupRecord } from '@/components/lookup-page';
import { dashboard } from '@/routes';
import branchRoutes from '@/routes/hr/branches';
import awardTypeRoutes from '@/routes/hr/award-types';
import type { Paginated, TableFilters } from '@/types';

export default function AwardTypes({
    awardTypes,
    filters,
}: {
    awardTypes: Paginated<LookupRecord>;
    filters: TableFilters;
}) {
    return (
        <LookupPage
            records={awardTypes}
            filters={filters}
            routes={awardTypeRoutes}
            module="award-types"
            title="Award Types"
            description="Manage award types used for employee recognition."
            singular="Award Type"
            nameLabel="Award Type Name"
            namePlaceholder="e.g., Employee of the Month, Best Performer"
            icon={Award}
            iconClass="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300"
        />
    );
}

AwardTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organization Structure', href: branchRoutes.index() },
        { title: 'Award Types', href: awardTypeRoutes.index() },
    ],
};
