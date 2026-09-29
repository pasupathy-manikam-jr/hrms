import { Target } from 'lucide-react';
import { LookupPage } from '@/components/lookup-page';
import type { LookupRecord } from '@/components/lookup-page';
import { dashboard } from '@/routes';
import goalTypeRoutes from '@/routes/hr/performance/goal-types';
import type { Paginated, TableFilters } from '@/types';

export default function GoalTypes({
    goalTypes,
    filters,
}: {
    goalTypes: Paginated<LookupRecord>;
    filters: TableFilters;
}) {
    return (
        <LookupPage
            records={goalTypes}
            filters={filters}
            routes={goalTypeRoutes}
            module="goal-types"
            title="Goal Types"
            description="Manage goal types used to categorize employee objectives."
            singular="Goal Type"
            nameLabel="Goal Type Name"
            namePlaceholder="e.g., Personal, Professional, Team"
            icon={Target}
            iconClass="bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-300"
        />
    );
}

GoalTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Performance Management', href: goalTypeRoutes.index() },
        { title: 'Goal Types', href: goalTypeRoutes.index() },
    ],
};
