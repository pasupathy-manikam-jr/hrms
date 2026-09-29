import { Briefcase } from 'lucide-react';
import { LookupPage } from '@/components/lookup-page';
import type { LookupRecord } from '@/components/lookup-page';
import { dashboard } from '@/routes';
import jobCategoryRoutes from '@/routes/hr/recruitment/job-categories';
import type { Paginated, TableFilters } from '@/types';

export default function JobCategories({
    jobCategories,
    filters,
}: {
    jobCategories: Paginated<LookupRecord>;
    filters: TableFilters;
}) {
    return (
        <LookupPage
            records={jobCategories}
            filters={filters}
            routes={jobCategoryRoutes}
            module="job-categories"
            title="Job Categories"
            description="Manage job categories used to classify open positions."
            singular="Job Category"
            nameLabel="Category Name"
            namePlaceholder="e.g., Engineering, Marketing, Finance"
            icon={Briefcase}
            iconClass="bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-300"
        />
    );
}

JobCategories.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: jobCategoryRoutes.index() },
        { title: 'Job Categories', href: jobCategoryRoutes.index() },
    ],
};
