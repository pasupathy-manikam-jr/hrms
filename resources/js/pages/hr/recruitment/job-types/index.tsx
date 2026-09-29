import { Layers } from 'lucide-react';
import { LookupPage } from '@/components/lookup-page';
import type { LookupRecord } from '@/components/lookup-page';
import { dashboard } from '@/routes';
import jobTypeRoutes from '@/routes/hr/recruitment/job-types';
import type { Paginated, TableFilters } from '@/types';

export default function JobTypes({
    jobTypes,
    filters,
}: {
    jobTypes: Paginated<LookupRecord>;
    filters: TableFilters;
}) {
    return (
        <LookupPage
            records={jobTypes}
            filters={filters}
            routes={jobTypeRoutes}
            module="job-types"
            title="Job Types"
            description="Manage job types such as full-time, part-time, and contract."
            singular="Job Type"
            nameLabel="Job Type Name"
            namePlaceholder="e.g., Full-time, Part-time, Contract"
            icon={Layers}
            iconClass="bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-300"
        />
    );
}

JobTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: jobTypeRoutes.index() },
        { title: 'Job Types', href: jobTypeRoutes.index() },
    ],
};
